<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Entity\Contest;
use App\Entity\Team;
use App\Entity\User;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use App\Entity\Clarification;
use App\Entity\ContestProblem;
use App\Entity\Problem;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Exception\InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/jury/clarifications")
 * @IsGranted("ROLE_JURY")
 */
class ClarificationController extends AbstractController
{
    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * @var DOMJudgeService
     */
    protected $dj;

    /**
     * @var EventLogService
     */
    protected $eventLogService;

    /**
     * ClarificationController constructor.
     * @param EntityManagerInterface $em
     * @param DOMJudgeService        $dj
     * @param EventLogService        $eventLogService
     */
    public function __construct(
        EntityManagerInterface $em,
        DOMJudgeService $dj,
        EventLogService $eventLogService
    ) {
        $this->em              = $em;
        $this->dj              = $dj;
        $this->eventLogService = $eventLogService;
    }

    /**
     * @Route("", name="jury_clarifications")
     * @throws \Exception
     */
    public function indexAction(Request $request)
    {
        $contestIds = array_keys($this->dj->getCurrentContests());
        // cid -1 will never happen, but otherwise the array is empty and that is not supported
        if (empty($contestIds)) {
            $contestIds = [-1];
        }

        $currentFilter = $request->query->get('filter');
        if ($currentFilter === 'all') {
            $currentFilter = null;
        }

        $currentQueue = $request->query->get('queue');
        if ($currentQueue === 'all') {
            $currentQueue = null;
        }

        $queryBuilder = $this->em->createQueryBuilder()
            ->from(Clarification::class, 'clar')
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

        $queues = $this->dj->dbconfig_get('clar_queues');

        return $this->render('jury/clarifications.html.twig', [
            'newClarifications' => $newClarifications,
            'oldClarifications' => $oldClarifications,
            'generalClarifications' => $generalClarifications,
            'queues' => $queues,
            'showExternalId' => $this->eventLogService->externalIdFieldForEntity(Clarification::class),
            'currentQueue' => $currentQueue,
            'currentFilter' => $currentFilter,
        ]);
    }

    /**
     * @Route("/{id<\d+>}", name="jury_clarification")
     * @throws \Exception
     */
    public function viewAction(Request $request, int $id)
    {
        /** @var Clarification $clarification */
        $clarification = $this->em->getRepository(Clarification::class)->find($id);
        if (!$clarification) {
            throw new NotFoundHttpException(sprintf('Clarification with ID %s not found', $id));
        }

        $clardata = ['list'=>[]];
        $clardata['clarform'] = $this->getClarificationFormData($clarification->getSender());
        $clardata['showExternalId'] = $this->eventLogService->externalIdFieldForEntity(Clarification::class);

        $categories = $clardata['clarform']['subjects'];
        $queues     = $this->dj->dbconfig_get('clar_queues');
        $clar_answers = $this->dj->dbconfig_get('clar_answers', []);

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
                $juryuser = $this->em->getRepository(User::class)->findBy(['username'=>$jurymember]);
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

            $data['body'] = Utils::wrap_unquoted($clar->getBody(), 78);
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

        return $this->render('jury/clarification.html.twig',
            $clardata
        );
    }

    protected function getProblemShortName(int $probid, int $cid) : string
    {
        $cp = $this->em->getRepository(ContestProblem::class)->findBy(['probid'=>$probid, 'cid' => $cid]);
        if ( isset($cp[0]) ) {
            return "problem " . $cp[0]->getShortName();
        }
        return "unknown problem";
    }

    protected function getClarificationFormData(Team $team = null) : array
    {
        $em = $this->getDoctrine()->getManager();
        if ($team !== null) {
            $teamlist[$team->getTeamid()] = sprintf("%s (t%s)", $team->getName(), $team->getTeamid());
        } else {
            $teams = $em->getRepository(Team::class)->findAll();
            foreach ($teams as $team) {
                $teamlist[$team->getTeamid()] = sprintf("%s (t%s)", $team->getName(), $team->getTeamid());
            }
        }
        asort($teamlist, SORT_STRING | SORT_FLAG_CASE);
        $teamlist = ['domjudge-must-select' => '(select...)', '' => 'ALL'] + $teamlist;

        $data= ['teams' => $teamlist ];

        $subject_options = [];

        $categories = $this->dj->dbconfig_get('clar_categories');
        $contests = $this->dj->getCurrentContests();

        /** @var ContestProblem[] $contestproblems */
        $contestproblems = $this->em->createQueryBuilder()
            ->from(ContestProblem::class, 'cp')
            ->select('cp, partial p.{probid,externalid,name}')
            ->innerJoin('cp.problem', 'p')
            ->where('cp.contest IN (:contests)')
            ->setParameter(':contests', $contests)
            ->orderBy('cp.shortname')
            ->getQuery()->getResult();

        foreach($contests as $cid => $cdata) {
            $cshort = $cdata->getShortName();
            foreach($categories as $name => $desc) {
                $subject_options[$cshort]["$cid-$name"] = "$cshort - $desc";
            }

            foreach($contestproblems as $cp) {
                if ( $cp->getCid()!=$cid ) continue;
                $subject_options[$cshort]["$cid-" . $cp->getProbid()] =
                    $cshort . ' - ' .$cp->getShortname() . ': ' . $cp->getProblem()->getName();
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

        return $this->render('jury/clarification_new.html.twig', ['clarform' => $data]);
    }

    /**
     * @Route("/{clarId<\d+>}/claim", name="jury_clarification_claim")
     * @param Request $request
     * @param int     $clarId
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function toggleClaimAction(Request $request, int $clarId)
    {
        /** @var Clarification $clarification */
        $clarification = $this->em->getReference(Clarification::class, $clarId);
        if (!$clarification) {
            throw new NotFoundHttpException(sprintf('Clarification with ID %i not found', $clarId));
        }

        if($request->request->getBoolean('claimed')) {
            $clarification->setJuryMember($this->getUser()->getUsername());
            $this->em->flush();
            return $this->redirectToRoute('jury_clarification', ['id' => $clarId]);
        } else {
            $clarification->setJuryMember(null);
            $this->em->flush();
            return $this->redirectToRoute('jury_clarifications');
        }
    }

    /**
     * @Route("/{clarId<\d+>}/set-answered", name="jury_clarification_set_answered")
     * @param Request $request
     * @param int     $clarId
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function toggleAnsweredAction(Request $request, int $clarId)
    {
        /** @var Clarification $clarification */
        $clarification = $this->em->getReference(Clarification::class, $clarId);
        if (!$clarification) {
            throw new NotFoundHttpException(sprintf('Clarification with ID %i not found', $clarId));
        }

        $answered = $request->request->getBoolean('answered');
        $clarification->setAnswered($answered);
        $this->em->flush();

        if ( $answered ) {
            return $this->redirectToRoute('jury_clarifications');
        } else {
            return $this->redirectToRoute('jury_clarification', ['id' => $clarId]);
        }
    }

    /**
     * @Route("/{clarId<\d+>}/change-subject", name="jury_clarification_change_subject")
     * @param Request $request
     * @param int     $clarId
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function changeSubjectAction(Request $request, int $clarId)
    {
        /** @var Clarification $clarification */
        $clarification = $this->em->getReference(Clarification::class, $clarId);
        if (!$clarification) {
            throw new NotFoundHttpException(sprintf('Clarification with ID %i not found', $clarId));
        }

        $subject = $request->request->get('subject');
        list($cid, $probid) = explode('-', $subject);

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

    /**
     * @Route("/{clarId<\d+>}/change-queue", name="jury_clarification_change_queue")
     * @param Request $request
     * @param int     $clarId
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function changeQueueAction(Request $request, int $clarId)
    {
        /** @var Clarification $clarification */
        $clarification = $this->em->getReference(Clarification::class, $clarId);
        if (!$clarification) {
            throw new NotFoundHttpException(sprintf('Clarification with ID %i not found', $clarId));
        }

        $queue = $request->request->get('queue');
        if ( $queue === "" ) {
            $queue = null;
        }
        $clarification->setQueue($queue);
        $this->em->flush();

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
            $respclar = $this->em->getRepository(Clarification::class)->find($respid);
            $clarification->setInReplyTo($respclar);
        }

        $sendto = $request->request->get('sendto');
        if (empty($sendto)) {
            $sendto = null;
        } elseif ($sendto === 'domjudge-must-select') {
            $message = 'You must select somewhere to send the clarification to.';
            $this->addFlash('danger', $message);
            return $this->redirectToRoute('jury_clarification_send');
        } else {
            $clarification->setRecipientId($sendto);
            $team = $this->em->getReference(Team::class, $sendto);
            $clarification->setRecipient($team);
        }

        $problem = $request->request->get('problem');
        list($cid, $probid) = explode('-', $problem);

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

        if($respid) {
            $queue = $respclar->getQueue();
        } else {
            $queue = $this->dj->dbconfig_get('clar_default_problem_queue');
            if ($queue === "") {
                $queue = null;
            }
        }
        $clarification->setQueue($queue);

        $clarification->setJuryMember($this->getUser()->getUsername());
        $clarification->setAnswered(true);
        $clarification->setBody($request->request->get('bodytext'));
        $clarification->setSubmittime(Utils::now());

        $this->em->persist($clarification);
        if ($respid) {
            $respclar->setAnswered(true);
            $respclar->setJuryMember($this->getUser()->getUsername());
            $this->em->persist($respclar);
        }
        $this->em->flush();

        $clarId = $clarification->getClarId();
        $this->dj->auditlog('clarification', $clarId, 'added', null, null, $cid);
        $this->eventLogService->log('clarification', $clarId, 'create', $cid);
        // Reload clarification to make sure we have a fresh one after calling the event log service
        $clarification = $this->em->getRepository(Clarification::class)->find($clarId);

        if($sendto) {
            $team = $this->em->getRepository(Team::class)->find($sendto);
            $team->addUnreadClarification($clarification);
        } else {
            $teams = $this->em->getRepository(Team::class)->findAll();
            foreach($teams as $team) {
                $team->addUnreadClarification($clarification);
            }
        }
        $this->em->flush();

        return $this->redirectToRoute('jury_clarification', ['id' => $clarId]);
    }
}
