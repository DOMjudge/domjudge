<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Controller\BaseController;
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
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_CLARIFICATION_RW')]
#[Route(path: '/jury')]
class ClarificationController extends BaseController
{

    public function __construct(
        EntityManagerInterface $em,
        DOMJudgeService $dj,
        protected readonly ConfigurationService $config,
        EventLogService $eventLogService,
        KernelInterface $kernel,
    ) {
        parent::__construct($em, $eventLogService, $dj, $kernel);
    }

    // Legacy route - redirects to contest-scoped route
    #[Route(path: '/clarifications', name: 'jury_clarifications_legacy')]
    public function legacyIndexAction(Request $request): Response
    {
        $contest = $this->dj->getCurrentContest();
        if (!$contest) {
            $this->addFlash('warning', 'Please select a contest first to view clarifications.');
            return $this->redirectToRoute('jury_index');
        }
        return $this->redirectToRoute('jury_clarifications', array_merge(
            ['contestId' => $contest->getExternalid()],
            $request->query->all()
        ));
    }

    #[Route(path: '/contests/{contestId}/clarifications', name: 'jury_clarifications')]
    public function indexAction(
        string $contestId,
        #[MapQueryParameter(name: 'filter')]
        ?string $currentFilter = null,
        #[MapQueryParameter(name: 'queue')]
        string $currentQueue = 'all',
    ): Response {
        $contest = $this->dj->getContestByExternalId($contestId);
        $categories = $this->config->get('clar_categories');

        if ($currentFilter === 'all') {
            $currentFilter = null;
        }

        $queryBuilder = $this->em->createQueryBuilder()
            ->from(Clarification::class, 'clar')
            ->leftJoin('clar.problem', 'p')
            ->innerJoin('clar.contest', 'c')
            ->leftJoin('p.contest_problems', 'cp', Join::WITH, 'cp.contest = clar.contest')
            ->select('clar', 'p', 'cp')
            ->andWhere('c.externalid = :contestId')
            ->setParameter('contestId', $contestId)
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

        $clarifications = $queryBuilder
            ->getQuery()
            ->getResult();

        /** @var Clarification[] $newClarifications */
        $newClarifications = [];
        /** @var Clarification[] $oldClarifications */
        $oldClarifications = [];
        /** @var Clarification[] $generalClarifications */
        $generalClarifications = [];

        foreach ($clarifications as $clar) {
            if ($clar->getSender() !== null) {
                if ($clar->getAnswered()) {
                    $oldClarifications[] = $clar;
                } else {
                    $newClarifications[] = $clar;
                }
            } elseif ($clar->getInReplyTo() === null) {
                $generalClarifications[] = $clar;
            }
        }

        $queues = $this->config->get('clar_queues');

        return $this->render('jury/clarifications.html.twig', [
            'contestId' => $contestId,
            'newClarifications' => $newClarifications,
            'oldClarifications' => $oldClarifications,
            'generalClarifications' => $generalClarifications,
            'queues' => $queues,
            'currentQueue' => $currentQueue,
            'currentFilter' => $currentFilter,
            'categories' => $categories,
        ]);
    }

    // Legacy route - redirects to contest-scoped route
    #[Route(path: '/clarifications/{id}', name: 'jury_clarification_legacy')]
    public function legacyViewAction(Request $request, string $id): Response
    {
        // If "no contest" is explicitly selected, redirect to index
        if ($this->dj->getCurrentContestCookie() === '-1') {
            $this->addFlash('warning', 'Please select a contest first to view clarifications.');
            return $this->redirectToRoute('jury_index');
        }

        $clarification = $this->em->getRepository(Clarification::class)->findOneBy(['externalid' => $id]);
        if (!$clarification) {
            throw new NotFoundHttpException(sprintf('Clarification with ID %s not found', $id));
        }
        return $this->redirectToRoute('jury_clarification', array_merge(
            [
                'contestId' => $clarification->getContest()->getExternalid(),
                'id' => $id,
            ],
            $request->query->all()
        ));
    }

    #[Route(path: '/contests/{contestId}/clarifications/{id}', name: 'jury_clarification')]
    public function viewAction(Request $request, string $contestId, string $id): Response
    {
        $contest = $this->dj->getContestByExternalId($contestId);
        $clarification = $this->em->getRepository(Clarification::class)->findOneBy([
            'externalid' => $id,
            'contest' => $contest,
        ]);
        if (!$clarification) {
            throw new NotFoundHttpException(sprintf('Clarification with ID %s not found in contest %s', $id, $contestId));
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

        if ($clarification->getProblem()?->getExternalid()) {
            $subject = sprintf('%s%s%s', $clarification->getContest()->getExternalid(), Clarification::PROBLEM_BASED_SEPARATOR, $clarification->getProblem()->getExternalid());
        } else {
            $subject = sprintf('%s%s%s', $clarification->getContest()->getExternalid(), Clarification::CATEGORY_BASED_SEPARATOR, $clarification->getCategory());
        }
        $formData = [
            'recipient' => JuryClarificationType::RECIPIENT_MUST_SELECT,
            'subject' => $subject,
        ];
        if ($clarification->getRecipient()) {
            $formData['recipient'] = $clarification->getRecipient()->getTeamid();
        }

        /** @var Clarification $lastClarification */
        $lastClarification = end($clarificationList);
        $formData['message'] = "> " . str_replace("\n", "\n> ", Utils::wrapUnquoted($lastClarification->getBody())) . "\n\n";

        $form = $this->createForm(JuryClarificationType::class, $formData, ['limit_to_team' => $clarification->getSender(), 'clarid' => $id]);

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
                if ($clar->getContestProblem()) {
                    $concernssubject = $contest->getExternalid() . Clarification::PROBLEM_BASED_SEPARATOR . $clar->getProblem()->getExternalid();
                } else {
                    // Very special case, this problem is unlinked.
                    $concernssubject = "";
                }
                $data['subjectlink'] = $this->generateUrl('jury_problem', ['probId' => $clar->getProblem()->getExternalid()]);
            } elseif ($clar->getCategory()) {
                $concernssubject = $contest->getExternalid() . Clarification::CATEGORY_BASED_SEPARATOR . $clar->getCategory();
            } else {
                $concernssubject = "";
            }
            if ($concernssubject !== "") {
                $data['subject'] = $categories[$concernssubject];
            } else {
                $data['subject'] = $clarcontest;
            }
            $data['categoryid'] = $concernssubject;
            $data['queue'] = $queues[$clar->getQueue() ?? ''] ?? 'Unassigned issues';
            $data['queueid'] = $clar->getQueue() ?? '';

            $data['answered'] = $clar->getAnswered();

            $data['body'] = $clar->getBody();
            $parameters['list'][] = $data;
        }

        $parameters['queues'] = $queues;
        $parameters['answers'] = $clarificationAnswers;
        $parameters['jurymember'] = $this->em->createQueryBuilder()
            ->select('clar.jury_member')
            ->from(Clarification::class, 'clar')
            ->where('clar.clarid = :clarid')
            ->setParameter('clarid', $clarification->getClarid())
            ->getQuery()
            ->getSingleResult()['jury_member'];

        $parameters['contestId'] = $contestId;
        $parameters['previousNext'] = $this->getPreviousAndNextObjectIds(
            Clarification::class,
            $clarification->getExternalid(),
            filterOnContest: true,
        );

        return $this->render('jury/clarification.html.twig', $parameters);
    }

    #[Route(path: '/contests/{contestId}/clarifications/send', name: 'jury_clarification_new', priority: 1)]
    public function composeClarificationAction(
        Request $request,
        string $contestId,
        #[MapQueryParameter]
        ?string $teamto = null,
    ): Response {
        $contest = $this->dj->getContestByExternalId($contestId);
        $formData = ['recipient' => JuryClarificationType::RECIPIENT_MUST_SELECT];

        if ($teamto !== null) {
            $formData['recipient'] = $teamto;
        }

        $form = $this->createForm(JuryClarificationType::class, $formData);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            return $this->processSubmittedClarification($form);
        }

        return $this->render('jury/clarification_new.html.twig', ['contestId' => $contestId, 'form' => $form->createView()]);
    }

    #[Route(path: '/contests/{contestId}/clarifications/{clarId}/claim', name: 'jury_clarification_claim')]
    public function toggleClaimAction(Request $request, string $contestId, string $clarId): Response
    {
        $contest = $this->dj->getContestByExternalId($contestId);
        $clarification = $this->em->getRepository(Clarification::class)->findOneBy([
            'externalid' => $clarId,
            'contest' => $contest,
        ]);
        if (!$clarification) {
            throw new NotFoundHttpException(sprintf('Clarification with ID %s not found in contest %s', $clarId, $contestId));
        }

        if ($request->request->getBoolean('claimed')) {
            $clarification->setJuryMember($this->getUser()->getUserIdentifier());
            $this->em->flush();
            return $this->redirectToRoute('jury_clarification', ['contestId' => $contestId, 'id' => $clarId]);
        } else {
            $clarification->setJuryMember(null);
            $this->em->flush();
            return $this->redirectToRoute('jury_clarifications', ['contestId' => $contestId]);
        }
    }

    #[Route(path: '/contests/{contestId}/clarifications/{clarId}/set-answered', name: 'jury_clarification_set_answered')]
    public function toggleAnsweredAction(Request $request, string $contestId, string $clarId): Response
    {
        $contest = $this->dj->getContestByExternalId($contestId);
        $clarification = $this->em->getRepository(Clarification::class)->findOneBy([
            'externalid' => $clarId,
            'contest' => $contest,
        ]);
        if (!$clarification) {
            throw new NotFoundHttpException(sprintf('Clarification with ID %s not found in contest %s', $clarId, $contestId));
        }

        $answered = $request->request->getBoolean('answered');
        $clarification->setAnswered($answered);
        $this->em->flush();

        if ($answered) {
            return $this->redirectToRoute('jury_clarifications', ['contestId' => $contestId]);
        } else {
            return $this->redirectToRoute('jury_clarification', ['contestId' => $contestId, 'id' => $clarId]);
        }
    }

    #[Route(path: '/contests/{contestId}/clarifications/{clarId}/change-subject', name: 'jury_clarification_change_subject')]
    public function changeSubjectAction(Request $request, string $contestId, string $clarId): Response
    {
        $contest = $this->dj->getContestByExternalId($contestId);
        $clarification = $this->em->getRepository(Clarification::class)->findOneBy([
            'externalid' => $clarId,
            'contest' => $contest,
        ]);
        if (!$clarification) {
            throw new NotFoundHttpException(sprintf('Clarification with ID %s not found in contest %s', $clarId, $contestId));
        }

        $subject = $request->request->get('subject');
        $problemId = null;
        $category = null;
        if (str_contains($subject, Clarification::CATEGORY_BASED_SEPARATOR)) {
            [$cid, $category] = explode(Clarification::CATEGORY_BASED_SEPARATOR, $subject);
        } else {
            [$cid, $problemId] = explode(Clarification::PROBLEM_BASED_SEPARATOR, $subject);
        }

        $newContest = $this->em->getRepository(Contest::class)->findOneBy(['externalid' => $cid]);
        if (!$newContest) {
            throw new NotFoundHttpException(sprintf('Contest with ID \'%s\' not found', $cid));
        }
        $clarification->setContest($newContest);

        if ($problemId) {
            $problem = $this->em->getRepository(Problem::class)->findOneBy(['externalid' => $problemId]);
            if (!$problem) {
                throw new NotFoundHttpException(sprintf('Problem with ID \'%s\' not found', $problemId));
            }
            $clarification->setProblem($problem);
            $clarification->setCategory(null);
        } else {
            $clarification->setProblem(null);
            $clarification->setCategory($category);
        }

        $this->em->flush();

        // Redirect to the new contest if it changed
        return $this->redirectToRoute('jury_clarification', ['contestId' => $newContest->getExternalid(), 'id' => $clarId]);
    }

    #[Route(path: '/contests/{contestId}/clarifications/{clarId}/change-queue', name: 'jury_clarification_change_queue')]
    public function changeQueueAction(Request $request, string $contestId, string $clarId): Response
    {
        $contest = $this->dj->getContestByExternalId($contestId);
        $clarification = $this->em->getRepository(Clarification::class)->findOneBy([
            'externalid' => $clarId,
            'contest' => $contest,
        ]);
        if (!$clarification) {
            throw new NotFoundHttpException(sprintf('Clarification with ID %s not found in contest %s', $clarId, $contestId));
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
        return $this->redirectToRoute('jury_clarification', ['contestId' => $contestId, 'id' => $clarId]);
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
            $team = $this->em->getRepository(Team::class)->findByExternalId($recipient);
            $clarification->setRecipient($team);
        }


        $subject = $formData['subject'];
        $problemId = null;
        $category = null;
        if (str_contains($subject, Clarification::CATEGORY_BASED_SEPARATOR)) {
            [$cid, $category] = explode(Clarification::CATEGORY_BASED_SEPARATOR, $subject);
        } else {
            [$cid, $problemId] = explode(Clarification::PROBLEM_BASED_SEPARATOR, $subject);
        }

        $contest = $this->em->getRepository(Contest::class)->findByExternalId($cid);
        $clarification->setContest($contest);

        if ($problemId) {
            $problem = $this->em->getRepository(Problem::class)->findByExternalId($problemId);
            $clarification->setProblem($problem);
            $clarification->setCategory(null);
        } else {
            $clarification->setProblem(null);
            if ($category !== "") {
                $clarification->setCategory($category);
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
        $this->dj->auditlog('clarification', $clarification->getExternalid(), 'added', null, null, $contest->getExternalid());
        $this->eventLog->log('clarification', $clarId, 'create', $contest->getCid());
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

        return $this->redirectToRoute('jury_clarification', ['contestId' => $contest->getExternalid(), 'id' => $clarification->getExternalid()]);
    }
}
