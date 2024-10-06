<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Entity\Clarification;
use App\Entity\Contest;
use App\Entity\Problem;
use App\Entity\Team;
use App\Entity\User;
use App\Form\Type\JuryClarificationType;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_CLARIFICATION_RW')]
#[Route(path: '/jury/clarifications')]
class ClarificationController extends AbstractController
{
    public function __construct(
        protected readonly EntityManagerInterface $em,
        protected readonly DOMJudgeService $dj,
        protected readonly ConfigurationService $config,
        protected readonly EventLogService $eventLogService
    ) {}

    #[Route(path: '', name: 'jury_clarifications')]
    public function indexAction(
        #[MapQueryParameter(name: 'filter')]
        ?string $currentFilter = null,
        #[MapQueryParameter(name: 'queue')]
        string $currentQueue = 'all',
    ): Response {
        $categories = $this->config->get('clar_categories');
        if ($contest = $this->dj->getCurrentContest()) {
            $contestIds = [$contest->getCid()];
        } else {
            $contestIds = array_keys($this->dj->getCurrentContests());
            // cid -1 will never happen, but otherwise the array is empty and that is not supported.
            if (empty($contestIds)) {
                $contestIds = [-1];
            }
        }

        if ($currentFilter === 'all') {
            $currentFilter = null;
        }

        $queryBuilder = $this->em->createQueryBuilder()
            ->from(Clarification::class, 'clar')
            ->leftJoin('clar.problem', 'p')
            ->leftJoin('p.contest_problems', 'cp', Join::WITH, 'cp.contest = clar.contest')
            ->select('clar', 'p', 'cp')
            ->andWhere('clar.contest in (:contestIds)')
            ->setParameter('contestIds', $contestIds)
            ->orderBy('clar.submittime', 'DESC')
            ->addOrderBy('clar.clarid', 'DESC');

        if ($currentQueue === "unassigned") {
            $queryBuilder->andWhere($queryBuilder->expr()->orX(
                $queryBuilder->expr()->isNull('clar.queue'),
                $queryBuilder->expr()->eq('clar.queue', ':queue')
            ))
                ->setParameter('queue', $currentQueue);
        } elseif ($currentQueue !== "all") {
            $queryBuilder->andWhere('clar.queue = :queue')
                ->setParameter('queue', $currentQueue);
        }

        /** @var Clarification[] $newClarifications */
        $newClarifications = [];
        /** @var Clarification[] $oldClarifications */
        $oldClarifications = [];
        /** @var Clarification[] $generalClarifications */
        $generalClarifications = [];
        $wheres            = [
            'new' => 'clar.sender IS NOT NULL AND clar.answered = 0',
            'old' => 'clar.sender IS NOT NULL AND clar.answered != 0',
            'general' => 'clar.sender IS NULL AND clar.in_reply_to IS NULL',
        ];
        foreach ($wheres as $type => $where) {
            $clarifications = (clone $queryBuilder)
                ->andWhere($where)
                ->getQuery()
                ->getResult();

            switch ($type) {
                case 'new':
                    $newClarifications = $clarifications;
                    break;
                case 'old':
                    $oldClarifications = $clarifications;
                    break;
                case 'general':
                    $generalClarifications = $clarifications;
                    break;
            }
        }

        $queues = $this->config->get('clar_queues');

        return $this->render('jury/clarifications.html.twig', [
            'newClarifications' => $newClarifications,
            'oldClarifications' => $oldClarifications,
            'generalClarifications' => $generalClarifications,
            'queues' => $queues,
            'currentQueue' => $currentQueue,
            'currentFilter' => $currentFilter,
            'categories' => $categories,
        ]);
    }

    #[Route(path: '/{id<\d+>}', name: 'jury_clarification')]
    public function viewAction(Request $request, int $id): Response
    {
        $clarification = $this->em->getRepository(Clarification::class)->find($id);
        if (!$clarification) {
            throw new NotFoundHttpException(sprintf('Clarification with ID %s not found', $id));
        }

        if ($inReplyTo = $clarification->getInReplyTo()) {
            $clarification = $inReplyTo;
        }
        $clarificationList = [$clarification];
        $replies = $clarification->getReplies();
        foreach ($replies as $reply) {
            $clarificationList[] = $reply;
        }

        $parameters = ['list' => []];

        $formData = [
            'recipient' => JuryClarificationType::RECIPIENT_MUST_SELECT,
            'subject' => sprintf('%s-%s', $clarification->getContest()->getCid(), $clarification->getProblem()?->getProbid() ?? $clarification->getCategory()),
        ];
        if ($clarification->getRecipient()) {
            $formData['recipient'] = $clarification->getRecipient()->getTeamid();
        }

        /** @var Clarification $lastClarification */
        $lastClarification = end($clarificationList);
        $formData['message'] = "> " . str_replace("\n", "\n> ", Utils::wrapUnquoted($lastClarification->getBody())) . "\n\n";

        $form = $this->createForm(JuryClarificationType::class, $formData, ['limit_to_team' => $clarification->getSender()]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            return $this->processSubmittedClarification($form, $clarification);
        }

        $parameters['form'] = $form->createView();

        $categories = array_flip($form->get('subject')->getConfig()->getOptions()['choices']);
        $groupedCategories = [];
        foreach ($categories as $key => $value) {
            if ($this->dj->getCurrentContest()) {
                $groupedCategories[$this->dj->getCurrentContest()->getShortname()][$key] = $value;
            } else {
                [$group] = explode(' - ', $value, 2);
                $groupedCategories[$group][$key] = $value;
            }
        }
        $parameters['subjects'] = $groupedCategories;
        $queues = $this->config->get('clar_queues');
        $clarificationAnswers = $this->config->get('clar_answers');

        foreach ($clarificationList as $clar) {
            $data = ['clarid' => $clar->getClarid(), 'externalid' => $clar->getExternalid()];
            $data['time'] = $clar->getSubmittime();

            $jurymember = $clar->getJuryMember();
            if (!empty($jurymember)) {
                $juryuser = $this->em->getRepository(User::class)->findBy(['username'=>$jurymember]);
                $data['from_jurymember'] = $juryuser[0]->getName();
                $data['jurymember_is_me'] = $juryuser[0] == $this->getUser();
            }

            if ($fromteam = $clar->getSender()) {
                $data['from_teamname'] = $fromteam->getEffectiveName();
                $data['from_team'] = $fromteam;
            }
            if ($toteam = $clar->getRecipient()) {
                $data['to_teamname'] = $toteam->getEffectiveName();
                $data['to_team'] = $toteam;
            }

            $contest = $clar->getContest();
            $data['contest'] = $contest;
            $clarcontest = $contest->getShortname();
            $data['subjectlink'] = null;
            if ($clar->getProblem()) {
                $concernssubject = $contest->getCid() . "-" . $clar->getProblem()->getProbid();
                $data['subjectlink'] = $this->generateUrl('jury_problem', ['probId' => $clar->getProblem()->getProbid()]);
            } elseif ($clar->getCategory()) {
                $concernssubject = $contest->getCid() . "-" . $clar->getCategory();
            } else {
                $concernssubject = "";
            }
            if ($concernssubject !== "") {
                $data['subject'] = $categories[$concernssubject];
            } else {
                $data['subject'] = $clarcontest;
            }
            $data['categoryid'] = $concernssubject;
            $data['queue'] = $queues[$clar->getQueue()] ?? 'Unassigned issues';
            $data['queueid'] = $clar->getQueue() ?? '';

            $data['answered'] = $clar->getAnswered();

            $data['body'] = $clar->getBody();
            $parameters['list'][] = $data;
        }

        $parameters['queues'] = $queues;
        $parameters['answers'] = $clarificationAnswers;

        return $this->render('jury/clarification.html.twig', $parameters);
    }

    #[Route(path: '/send', name: 'jury_clarification_new')]
    public function composeClarificationAction(
        Request $request,
        #[MapQueryParameter]
        ?string $teamto = null,
    ): Response {
        $formData = ['recipient' => JuryClarificationType::RECIPIENT_MUST_SELECT];

        if ($teamto !== null) {
            $formData['recipient'] = $teamto;
        }

        $form = $this->createForm(JuryClarificationType::class, $formData);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            return $this->processSubmittedClarification($form);
        }

        return $this->render('jury/clarification_new.html.twig', ['form' => $form->createView()]);
    }

    #[Route(path: '/{clarId<\d+>}/claim', name: 'jury_clarification_claim')]
    public function toggleClaimAction(Request $request, int $clarId): Response
    {
        $clarification = $this->em->getReference(Clarification::class, $clarId);
        if (!$clarification) {
            throw new NotFoundHttpException(sprintf('Clarification with ID %d not found', $clarId));
        }

        if ($request->request->getBoolean('claimed')) {
            $clarification->setJuryMember($this->getUser()->getUserIdentifier());
            $this->em->flush();
            return $this->redirectToRoute('jury_clarification', ['id' => $clarId]);
        } else {
            $clarification->setJuryMember(null);
            $this->em->flush();
            return $this->redirectToRoute('jury_clarifications');
        }
    }

    #[Route(path: '/{clarId<\d+>}/set-answered', name: 'jury_clarification_set_answered')]
    public function toggleAnsweredAction(Request $request, int $clarId): Response
    {
        $clarification = $this->em->getReference(Clarification::class, $clarId);
        if (!$clarification) {
            throw new NotFoundHttpException(sprintf('Clarification with ID %d not found', $clarId));
        }

        $answered = $request->request->getBoolean('answered');
        $clarification->setAnswered($answered);
        $this->em->flush();

        if ($answered) {
            return $this->redirectToRoute('jury_clarifications');
        } else {
            return $this->redirectToRoute('jury_clarification', ['id' => $clarId]);
        }
    }

    #[Route(path: '/{clarId<\d+>}/change-subject', name: 'jury_clarification_change_subject')]
    public function changeSubjectAction(Request $request, int $clarId): Response
    {
        $clarification = $this->em->getReference(Clarification::class, $clarId);
        if (!$clarification) {
            throw new NotFoundHttpException(sprintf('Clarification with ID %d not found', $clarId));
        }

        $subject = $request->request->get('subject');
        [$cid, $probid] = explode('-', $subject);

        $contest = $this->em->getReference(Contest::class, $cid);
        $clarification->setContest($contest);

        if (ctype_digit($probid)) {
            $problem = $this->em->getReference(Problem::class, $probid);
            $clarification->setProblem($problem);
            $clarification->setCategory(null);
        } else {
            $clarification->setProblem(null);
            $clarification->setCategory($probid);
        }

        $this->em->flush();

        return $this->redirectToRoute('jury_clarification', ['id' => $clarId]);
    }

    #[Route(path: '/{clarId<\d+>}/change-queue', name: 'jury_clarification_change_queue')]
    public function changeQueueAction(Request $request, int $clarId): Response
    {
        $clarification = $this->em->getReference(Clarification::class, $clarId);
        if (!$clarification) {
            throw new NotFoundHttpException(sprintf('Clarification with ID %d not found', $clarId));
        }

        $queue = $request->request->get('queue');
        if ($queue === "") {
            $queue = null;
        }

        // Find the original clarification if this is a reply, and then update
        // the queue of the original clarification and all replies.
        // Replies are not threaded, so no recursive search is needed.
        $curClarification = $clarification->getInReplyTo() ?? $clarification;
        foreach ($curClarification->getReplies() as $reply) {
            $reply->setQueue($queue);
        }
        $curClarification->setQueue($queue);
        $this->em->flush();

        if ($request->isXmlHttpRequest()) {
            return $this->json(true);
        }
        return $this->redirectToRoute('jury_clarification', ['id' => $clarId]);
    }

    protected function processSubmittedClarification(
        FormInterface $form,
        ?Clarification $inReplTo = null
    ): Response {
        $formData = $form->getData();
        $clarification = new Clarification();
        $clarification->setInReplyTo($inReplTo);

        $recipient = $formData['recipient'];
        if (empty($recipient)) {
            $recipient = null;
        } else {
            $team = $this->em->getReference(Team::class, $recipient);
            $clarification->setRecipient($team);
        }


        $subject = $formData['subject'];
        [$cid, $probid] = explode('-', $subject);

        $contest = $this->em->getReference(Contest::class, $cid);
        $clarification->setContest($contest);

        if (ctype_digit($probid)) {
            $problem = $this->em->getReference(Problem::class, $probid);
            $clarification->setProblem($problem);
            $clarification->setCategory(null);
        } else {
            $clarification->setProblem(null);
            if ($probid !== "") {
                $clarification->setCategory($probid);
            } else {
                $clarification->setCategory(null);
            }
        }

        if ($inReplTo) {
            $queue = $inReplTo->getQueue();
        } else {
            $queue = $this->config->get('clar_default_problem_queue');
            if ($queue === "") {
                $queue = null;
            }
        }
        $clarification->setQueue($queue);

        $clarification->setJuryMember($this->getUser()->getUserIdentifier());
        $clarification->setAnswered(true);
        $clarification->setBody($formData['message']);
        $clarification->setSubmittime(Utils::now());

        $this->em->persist($clarification);
        if ($inReplTo) {
            $inReplTo->setAnswered(true);
            $inReplTo->setJuryMember($this->getUser()->getUserIdentifier());
        }
        $this->em->flush();

        $clarId = $clarification->getClarId();
        $this->dj->auditlog('clarification', $clarId, 'added', null, null, $cid);
        $this->eventLogService->log('clarification', $clarId, 'create', (int)$cid);
        // Reload clarification to make sure we have a fresh one after calling the event log service.
        $clarification = $this->em->getRepository(Clarification::class)->find($clarId);

        if ($clarification->getRecipient()) {
            $clarification->getRecipient()->addUnreadClarification($clarification);
        } else {
            $teams = $this->em->getRepository(Team::class)->findAll();
            foreach ($teams as $team) {
                $team->addUnreadClarification($clarification);
            }
        }
        $this->em->flush();

        return $this->redirectToRoute('jury_clarification', ['id' => $clarId]);
    }
}
