<?php declare(strict_types=1);

namespace DOMJudgeBundle\Controller\Jury;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use DOMJudgeBundle\Entity\Clarification;
use DOMJudgeBundle\Entity\ContestProblem;
use DOMJudgeBundle\Entity\Contest;
use DOMJudgeBundle\Entity\Problem;
use DOMJudgeBundle\Entity\User;
use DOMJudgeBundle\Entity\Team;
use DOMJudgeBundle\Service\DOMJudgeService;
use DOMJudgeBundle\Service\EventLogService;
use DOMJudgeBundle\Utils\Utils;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Form\Exception\InvalidArgumentException;

/**
 * @Route("/jury/clarifications")
 * @Security("has_role('ROLE_JURY')")
 */
class ClarificationController extends Controller
{
    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var DOMJudgeService
     */
    protected $DOMJudgeService;

    /**
     * @var EventLogService
     */
    protected $eventLogService;

    /**
     * ClarificationController constructor.
     * @param EntityManagerInterface $entityManager
     * @param DOMJudgeService        $DOMJudgeService
     * @param EventLogService        $eventLogService
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        DOMJudgeService $DOMJudgeService,
        EventLogService $eventLogService
    ) {
        $this->entityManager   = $entityManager;
        $this->DOMJudgeService = $DOMJudgeService;
        $this->eventLogService = $eventLogService;
    }

    /**
     * @Route("", name="jury_clarifications")
     * @throws \Exception
     */
    public function indexAction(Request $request)
    {
        $contestIds = array_keys($this->DOMJudgeService->getCurrentContests());
        // cid -1 will never happen, but otherwise the array is empty and that is not supported
        if (empty($contestIds)) {
            $contestIds = [-1];
        }

        $currentQueue = $request->query->get('queue');
        if ($currentQueue === 'all') {
            $currentQueue = null;
        }

        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->from('DOMJudgeBundle:Clarification', 'clar')
            ->leftJoin('clar.problem', 'p')
            ->leftJoin('p.contest_problems', 'cp', Join::WITH, 'cp.contest = clar.contest')
            ->select('clar', 'p', 'cp')
            ->andWhere('clar.cid in (:contestIds)')
            ->setParameter(':contestIds', $contestIds)
            ->orderBy('clar.submittime', 'DESC')
            ->addOrderBy('clar.clarid', 'DESC');

        if ($currentQueue !== null) {
            if ($currentQueue === '') {
                $queryBuilder->andWhere('clar.queue IS NULL');
            } else {
                $queryBuilder
                    ->andWhere('clar.queue = :queue')
                    ->setParameter(':queue', $currentQueue);
            }
        }

        /**
         * @var Clarification[] $newClarifications
         * @var Clarification[] $oldClarifications
         * @var Clarification[] $generalClarifications
         */
        $newClarifications = $oldClarifications = $generalClarifications = [];
        $wheres            = [
            'new' => 'clar.sender IS NOT NULL AND clar.answered = 0',
            'old' => 'clar.sender IS NOT NULL AND clar.answered != 0',
            'general' => 'clar.sender IS NULL AND (clar.respid IS NULL OR clar.recipient IS NULL)',
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

        $queues = $this->DOMJudgeService->dbconfig_get('clar_queues');


        return $this->render('@DOMJudge/jury/clarifications.html.twig', [
            'newClarifications' => $newClarifications,
            'oldClarifications' => $oldClarifications,
            'generalClarifications' => $generalClarifications,
            'queues' => $queues,
            'showExternalId' => $this->eventLogService->externalIdFieldForEntity(Clarification::class),
            'currentQueue' => $currentQueue,
        ]);
    }

    /**
     * @Route("/{id}", name="jury_clarification", requirements={"id": "\d+"})
     * @throws \Exception
     */
    public function viewAction(Request $request, int $id)
    {
        /** @var Clarification $clarification */
        $clarification = $this->entityManager->getRepository(Clarification::class)->find($id);
        if (!$clarification) {
            throw new NotFoundHttpException(sprintf('Clarification with ID %s not found', $id));
        }

        $clardata = ['list'=>[]];
        $clardata['clarform'] = $this->getClarificationFormData();
        $clardata['showExternalId'] = $this->eventLogService->externalIdFieldForEntity(Clarification::class);

        $categories = $clardata['clarform']['subjects'];
        $queues     = $this->DOMJudgeService->dbconfig_get('clar_queues');
        $clar_answers = $this->DOMJudgeService->dbconfig_get('clar_answers', []);

        if ( $irt = $clarification->getInReplyTo() ) {
            $clarlist = [$irt];
            $replies = $irt->getReplies() ?? [];
            foreach($replies as $clar_reply) { $clarlist[] = $clar_reply; }
        } else {
            $clarlist = [$clarification];
            $replies = $clarification->getReplies() ?? [];
            foreach($replies as $clar_reply) { $clarlist[] = $clar_reply; }
        }

        $concernsteam = null;
        foreach($clarlist as $k => $clar) {
            $data = ['clarid' => $clar->getClarid(), 'externalid' => $clar->getExternalid()];
            $data['time'] = $clar->getSubmittime();

            $jurymember = $clar->getJuryMember();
            if ( !empty($jurymember) ) {
                $juryuser = $this->entityManager->getRepository(User::class)->findBy(['username'=>$jurymember]);
                $data['from_jurymember'] = $juryuser[0]->getName();
                $data['jurymember_is_me'] = $juryuser[0] == $this->getUser();
            }

            if ( $fromteam = $clar->getSender() ) {
                $data['from_teamname'] = $fromteam->getName();
                $data['from_teamid'] = $fromteam->getTeamid();
                $concernsteam = $fromteam->getTeamid();
            }
            if ( $toteam = $clar->getRecipient() ) {
                $data['to_teamname'] = $toteam->getName();
                $data['to_teamid'] = $toteam->getTeamid();
            }

            $contest = $clar->getContest();
            $data['contest'] = $contest;
            $clarcontest = $contest->getShortname();
            if ( $clar->getProbId() ) {
                $concernssubject = $contest->getCid() . "-" . $clar->getProbId();
            } elseif ( $clar->getCategory() ) {
                $concernssubject = $contest->getCid() . "-" . $clar->getCategory();
            } else {
                $concernssubject = "";
            }
            if ($concernssubject !== "") {
                $data['subject'] = $categories[$clarcontest][$concernssubject];
            } else {
                $data['subject'] = $clarcontest;
            }
            $data['categoryid'] = $concernssubject;
            $data['queue'] = $queues[$clar->getQueue()] ?? 'Unassigned issues';
            $data['queueid'] = $clar->getQueue() ?? '';

            $data['answered'] = $clar->getAnswered();

            $data['body'] = $clar->getBody();
            $clardata['list'][] = $data;
        }
    
        if ( $concernsteam ) {
            $clardata['clarform']['toteam'] = $concernsteam;
        }
        if ( $concernssubject ) {
            $clardata['clarform']['onsubject'] = $concernssubject;
        }
    
        $clardata['clarform']['quotedtext'] = "> " . str_replace("\n", "\n> ", Utils::wrap_unquoted($data['body'])) . "\n\n";
        $clardata['clarform']['queues'] = $queues;
        $clardata['clarform']['answers'] = $clar_answers;
    
        return $this->render('@DOMJudge/jury/clarification.html.twig',
            $clardata
        );
    }

    protected function getProblemShortName(int $probid, int $cid) : string
    {
        $cp = $this->entityManager->getRepository(ContestProblem::class)->findBy(['probid'=>$probid, 'cid' => $cid]);
        if ( isset($cp[0]) ) {
            return "problem " . $cp[0]->getShortName();
        }
        return "unknown problem";
    }

    protected function getClarificationFormData() : array
    {
        $em = $this->getDoctrine()->getManager();
        $teams = $em->getRepository('DOMJudgeBundle:Team')->findAll();
        foreach ($teams as $team) {
            $teamlist[$team->getTeamid()] = sprintf("%s (t%s)", $team->getName(), $team->getTeamid());
        }
        asort($teamlist, SORT_STRING | SORT_FLAG_CASE);
        $teamlist = ['domjudge-must-select' => '(select...)', '' => 'ALL'] + $teamlist;

        $data= ['teams' => $teamlist ];

        $subject_options = [];

        $categories = $this->DOMJudgeService->dbconfig_get('clar_categories');
        $contests = $this->DOMJudgeService->getCurrentContests();
        foreach($contests as $cid => $cdata) {
            $cshort = $cdata->getShortName();
            foreach($categories as $name => $desc) {
                $subject_options[$cshort]["$cid-$name"] = "$cshort - $desc";
            }

            $queryBuilder = $this->entityManager->createQueryBuilder()
                ->from('DOMJudgeBundle:ContestProblem', 'cp', 'cp.probid')
                ->select('cp, p')
                ->innerJoin('cp.problem', 'p')
                ->where('cp.cid = :cid')
                ->setParameter(':cid', $cdata->getCid())
                ->orderBy('cp.shortname');

            $contestproblems = $queryBuilder->getQuery()->getResult();
            foreach($contestproblems as $cp) {
                $subject_options[$cshort]["$cid-" . $cp->getProbid() ] = $cshort . ' - ' .$cp->getShortname() . ': ' . $cp->getProblem()->getName();
            }
        }

        $data['subjects'] = $subject_options;

        return $data;
    }

    /**
     * @Route("/send", methods={"GET"}, name="jury_clarification_new")
     * @throws \Exception
     */
    public function composeClarificationAction(Request $request)
    {
        // TODO: use proper Symfony form for this

        $data = $this->getClarificationFormData();

        if ( $toteam = $request->query->get('teamto') ) {
            $data['toteam'] = $toteam;
        }

        return $this->render('@DOMJudge/jury/clarification_new.html.twig', ['clarform' => $data]);
    }

    /**
     * @Route("/{clarId}/claim", name="jury_clarification_claim")
     * @param Request $request
     * @param int     $clarId
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function toggleClaimAction(Request $request, int $clarId)
    {
        /** @var Clarification $clarification */
        $clarification = $this->entityManager->getReference(Clarification::class, $clarId);
        if (!$clarification) {
            throw new NotFoundHttpException(sprintf('Clarification with ID %i not found', $clarId));
        }

        if($request->request->getBoolean('claimed')) {
            $clarification->setJuryMember($this->getUser()->getUsername());
            $this->entityManager->flush();
            return $this->redirectToRoute('jury_clarification', ['id' => $clarId]);
        } else {
            $clarification->setJuryMember(null);
            $this->entityManager->flush();
            return $this->redirectToRoute('jury_clarifications');
        }
    }

    /**
     * @Route("/{clarId}/set-answered", name="jury_clarification_set_answered")
     * @param Request $request
     * @param int     $clarId
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function toggleAnsweredAction(Request $request, int $clarId)
    {
        /** @var Clarification $clarification */
        $clarification = $this->entityManager->getReference(Clarification::class, $clarId);
        if (!$clarification) {
            throw new NotFoundHttpException(sprintf('Clarification with ID %i not found', $clarId));
        }

        $answered = $request->request->getBoolean('answered');
        $clarification->setAnswered($answered);
        $this->entityManager->flush();

        if ( $answered ) {
            return $this->redirectToRoute('jury_clarifications');
        } else {
            return $this->redirectToRoute('jury_clarification', ['id' => $clarId]);
        }
    }

    /**
     * @Route("/{clarId}/change-subject", name="jury_clarification_change_subject")
     * @param Request $request
     * @param int     $clarId
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function changeSubjectAction(Request $request, int $clarId)
    {
        /** @var Clarification $clarification */
        $clarification = $this->entityManager->getReference(Clarification::class, $clarId);
        if (!$clarification) {
            throw new NotFoundHttpException(sprintf('Clarification with ID %i not found', $clarId));
        }

        $subject = $request->request->get('subject');
        list($cid, $probid) = explode('-', $subject);

        $contest = $this->entityManager->getReference(Contest::class, $cid);
        $clarification->setContest($contest);

        if (ctype_digit($probid)) {
            $problem = $this->entityManager->getReference(Problem::class, $probid);
            $clarification->setProblem($problem);
            $clarification->setCategory(null);
        } else {
            $clarification->setProblem(null);
            $clarification->setCategory($probid);
        }

        $this->entityManager->flush();

        return $this->redirectToRoute('jury_clarification', ['id' => $clarId]);
    }

    /**
     * @Route("/{clarId}/change-queue", name="jury_clarification_change_queue")
     * @param Request $request
     * @param int     $clarId
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function changeQueueAction(Request $request, int $clarId)
    {
        /** @var Clarification $clarification */
        $clarification = $this->entityManager->getReference(Clarification::class, $clarId);
        if (!$clarification) {
            throw new NotFoundHttpException(sprintf('Clarification with ID %i not found', $clarId));
        }

        $queue = $request->request->get('queue');
        if ( $queue === "" ) {
            $queue = null;
        }
        $clarification->setQueue($queue);
        $this->entityManager->flush();

        if($request->isXmlHttpRequest()) {
            return $this->json(true);
        }
        return $this->redirectToRoute('jury_clarification', ['id' => $clarId]);
    }

    /**
     * @Route("/send", methods={"POST"}, name="jury_clarification_send")
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function sendAction(Request $request)
    {
        $clarification = new Clarification();

        if($respid = $request->request->get('id')) {
            $respclar = $this->entityManager->getRepository(Clarification::class)->find($respid);
            $clarification->setInReplyTo($respclar);
        }

        $sendto = $request->request->get('sendto');
        if (empty($sendto)) {
            $sendto = null;
        } elseif ($sendto === 'domjudge-must-select') {
            throw new InvalidArgumentException('You must select somewhere to send the clarification to.');
        } else {
            $clarification->setRecipientId($sendto);
            $team = $this->entityManager->getReference(Team::class, $sendto);
            $clarification->setRecipient($team);
        }

        $problem = $request->request->get('problem');
        list($cid, $probid) = explode('-', $problem);

        $contest = $this->entityManager->getReference(Contest::class, $cid);
        $clarification->setContest($contest);

        if (ctype_digit($probid)) {
            $problem = $this->entityManager->getReference(Problem::class, $probid);
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

        if($respid) {
            $queue = $respclar->getQueue();
        } else {
            $queue = $this->DOMJudgeService->dbconfig_get('clar_default_problem_queue');
            if ($queue === "") {
                $queue = null;
            }
        }
        $clarification->setQueue($queue);

        $clarification->setJuryMember($this->getUser()->getUsername());
        $clarification->setAnswered(true);
        $clarification->setBody($request->request->get('bodytext'));
        $clarification->setSubmittime(Utils::now());

        $this->entityManager->persist($clarification);
        if ($respid) {
            $respclar->setAnswered(true);
            $respclar->setJuryMember($this->getUser()->getUsername());
            $this->entityManager->persist($respclar);
        }
        $this->entityManager->flush();

        $clarId = $clarification->getClarId();
        $this->DOMJudgeService->auditlog('clarification', $clarId, 'added', null, null, $cid);
        $this->eventLogService->log('clarification', $clarId, 'create', $cid);
        // Reload clarification to make sure we have a fresh one after calling the event log service
        $clarification = $this->entityManager->getRepository(Clarification::class)->find($clarId);

        if($sendto) {
            $team = $this->entityManager->getRepository(Team::class)->find($sendto);
            $team->addUnreadClarification($clarification);
        } else {
            $teams = $this->entityManager->getRepository(Team::class)->findAll();
            foreach($teams as $team) {
                $team->addUnreadClarification($clarification);
            }
        }
        $this->entityManager->flush();

        return $this->redirectToRoute('jury_clarification', ['id' => $clarId]);
    }
}
