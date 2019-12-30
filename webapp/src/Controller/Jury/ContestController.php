<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Controller\BaseController;
use App\Entity\Clarification;
use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\RemovedInterval;
use App\Entity\Submission;
use App\Entity\Team;
use App\Form\Type\ContestType;
use App\Form\Type\FinalizeContestType;
use App\Form\Type\RemovedIntervalType;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/jury/contests")
 * @IsGranted("ROLE_JURY")
 */
class ContestController extends BaseController
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
     * @var KernelInterface
     */
    protected $kernel;

    /**
     * @var EventLogService
     */
    protected $eventLogService;

    /**
     * TeamCategoryController constructor.
     * @param EntityManagerInterface $em
     * @param DOMJudgeService        $dj
     * @param KernelInterface        $kernel
     * @param EventLogService        $eventLogService
     */
    public function __construct(
        EntityManagerInterface $em,
        DOMJudgeService $dj,
        KernelInterface $kernel,
        EventLogService $eventLogService
    ) {
        $this->em              = $em;
        $this->dj              = $dj;
        $this->eventLogService = $eventLogService;
        $this->kernel          = $kernel;
    }

    /**
     * @Route("", name="jury_contests")
     * @param Request         $request
     * @param KernelInterface $kernel
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function indexAction(Request $request, KernelInterface $kernel)
    {
        $em = $this->em;

        if ($doNow = (array)$request->request->get('donow')) {
            $times         = ['activate', 'start', 'freeze', 'end',
                              'unfreeze', 'finalize', 'deactivate'];
            $start_actions = ['delay_start', 'resume_start'];
            $actions       = array_merge($times, $start_actions);

            if (!$this->isGranted('ROLE_ADMIN')) {
                throw new AccessDeniedHttpException();
            }
            /** @var Contest $contest */
            $contest = $em->getRepository(Contest::class)->find($request->request->get('contest'));
            if (!$contest) {
                throw new NotFoundHttpException('Contest not found');
            }

            $time = key($doNow);
            if (!in_array($time, $actions, true)) {
                throw new BadRequestHttpException(
                    sprintf("Unknown value '%s' for timetype", $time)
                );
            }

            if ($time === 'finalize') {
                return $this->redirectToRoute(
                    'jury_contest_finalize',
                    ['contestId' => $contest->getCid()]
                );
            }

            $now       = (int)floor(Utils::now());
            $nowstring = strftime('%Y-%m-%d %H:%M:%S ', $now) . date_default_timezone_get();
            $this->dj->auditlog('contest', $contest->getCid(), $time . ' now', $nowstring);

            // Special case delay/resume start (only sets/unsets starttime_undefined).
            $maxSeconds = Contest::STARTTIME_UPDATE_MIN_SECONDS_BEFORE;
            if (in_array($time, $start_actions, true)) {
                $enabled = $time === 'delay_start' ? 0 : 1;
                if (Utils::difftime((float)$contest->getStarttime(false), $now) <= $maxSeconds) {
                    $this->addFlash(
                        'error',
                        sprintf("Cannot %s less than %d seconds before contest start.",
                                $time, $maxSeconds)
                    );
                    return $this->redirectToRoute('jury_contests');
                }
                $contest->setStarttimeEnabled($enabled);
                $em->flush();
                $this->eventLogService->log(
                    'contest',
                    $contest->getCid(),
                    EventLogService::ACTION_UPDATE,
                    $contest->getCid()
                );
                $this->addFlash('scoreboard_refresh',
                                'After changing the contest start time, it may be ' .
                                'necessary to recalculate any cached scoreboards.');
                return $this->redirectToRoute('jury_contests');
            }

            $juryTimeData = $contest->getJuryTimeData();
            if (!$juryTimeData[$time]['show_button']) {
                throw new BadRequestHttpException(
                    sprintf('Cannot update %s time at this moment', $time)
                );
            }

            // starttime is special because other, relative times depend on it.
            if ($time == 'start') {
                if ($contest->getStarttimeEnabled() &&
                    Utils::difftime((float)$contest->getStarttime(false),
                                    $now) <= $maxSeconds) {
                    $this->addFlash(
                        'danger',
                        sprintf("Cannot update starttime less than %d seconds before contest start.",
                                $maxSeconds)
                    );
                    return $this->redirectToRoute('jury_contests');
                }
                $contest
                    ->setStarttime($now)
                    ->setStarttimeString($nowstring)
                    ->setStarttimeEnabled(true);
                $em->flush();

                $this->eventLogService->log(
                    'contest',
                    $contest->getCid(),
                    EventLogService::ACTION_UPDATE,
                    $contest->getCid()
                );
                $this->addFlash('scoreboard_refresh',
                                'After changing the contest start time, it may be ' .
                                'necessary to recalculate any cached scoreboards.');
                return $this->redirectToRoute('jury_contests');
            } else {
                $method = sprintf('set%stimeString', $time);
                $contest->{$method}($nowstring);
                $em->flush();
                $this->eventLogService->log(
                    'contest',
                    $contest->getCid(),
                    EventLogService::ACTION_UPDATE,
                    $contest->getCid()
                );
                return $this->redirectToRoute('jury_contests');
            }
        }

        $contests = $em->createQueryBuilder()
            ->select('c')
            ->from(Contest::class, 'c')
            ->orderBy('c.starttime', 'DESC')
            ->groupBy('c.cid')
            ->getQuery()->getResult();

        $table_fields = [
            'cid'          => ['title' => 'CID', 'sort' => true],
            'shortname'    => ['title' => 'shortname', 'sort' => true],
            'name'         => ['title' => 'name', 'sort' => true],
            'activatetime' => ['title' => 'activate', 'sort' => true],
            'starttime'    => ['title' => 'start', 'sort' => true,
                               'default_sort' => true, 'default_sort_order' => 'desc'],
            'endtime'      => ['title' => 'end', 'sort' => true],
        ];

        $currentContests = $this->dj->getCurrentContests();

        $timeFormat = (string)$this->dj->dbconfig_get('time_format', '%H:%M');

        $etcDir = $this->dj->getDomjudgeEtcDir();
        require_once $etcDir . '/domserver-config.php';

        if (ALLOW_REMOVED_INTERVALS) {
            $table_fields['num_removed_intervals'] = ['title' => '# removed<br/>intervals', 'sort' => true];
            $removedIntervals                      = $em->createQueryBuilder()
                ->from(RemovedInterval::class, 'i', 'i.cid')
                ->select('COUNT(i.intervalid) AS num_removed_intervals', 'i.cid')
                ->groupBy('i.cid')
                ->getQuery()
                ->getResult();
        } else {
            $removedIntervals = [];
        }

        $problemData = $em->createQueryBuilder()
            ->from(ContestProblem::class, 'cp')
            ->select('COUNT(cp.problem) AS num_problems', 'c.cid')
            ->join('cp.contest', 'c')
            ->groupBy('cp.contest')
            ->getQuery()
            ->getResult();

        $problems = [];
        foreach ($problemData as $data) {
            $problems[$data['cid']] = $data['num_problems'];
        }

        $table_fields = array_merge($table_fields, [
            'process_balloons' => ['title' => 'process<br/>balloons?', 'sort' => true],
            'public'           => ['title' => 'public?', 'sort' => true],
            'num_teams'        => ['title' => '# teams', 'sort' => true],
            'num_problems'     => ['title' => '# problems', 'sort' => true],
        ]);

        // Insert external ID field when configured to use it
        if ($externalIdField = $this->eventLogService->externalIdFieldForEntity(Contest::class)) {
            $table_fields = array_slice($table_fields, 0, 1, true) +
                [$externalIdField => ['title' => 'external<br/>ID', 'sort' => true]] +
                array_slice($table_fields, 1, null, true);
        }

        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $contests_table   = [];
        foreach ($contests as $contest) {
            $contestdata    = [];
            $contestactions = [];
            // Get whatever fields we can from the contest object itself
            foreach ($table_fields as $k => $v) {
                if ($propertyAccessor->isReadable($contest, $k)) {
                    $contestdata[$k] = ['value' => $propertyAccessor->getValue($contest, $k)];
                }
            }

            if ($this->isGranted('ROLE_ADMIN')) {
                $contestactions[] = [
                    'icon' => 'edit',
                    'title' => 'edit this contest',
                    'link' => $this->generateUrl('jury_contest_edit', [
                        'contestId' => $contest->getCid(),
                    ])
                ];
                $contestactions[] = [
                    'icon' => 'trash-alt',
                    'title' => 'delete this contest',
                    'link' => $this->generateUrl('jury_contest_delete', [
                        'contestId' => $contest->getCid(),
                    ]),
                    'ajaxModal' => true,
                ];
            }

            $contestdata['process_balloons'] = [
                'value' => $contest->getProcessBalloons() ? 'yes' : 'no'
            ];
            $contestdata['public'] = ['value' => $contest->getPublic() ? 'yes' : 'no'];
            if ($contest->isOpenToAllTeams()) {
                $contestdata['num_teams'] = ['value' => '<i>all</i>'];
            } else {
                $teamCount = $em
                    ->createQueryBuilder()
                    ->select('COUNT(t.teamid) AS cnt')
                    ->from(Team::class, 't')
                    ->leftJoin('t.contests', 'c')
                    ->join('t.category', 'cat')
                    ->leftJoin('cat.contests', 'cc')
                    ->andWhere('c.cid = :cid OR cc.cid = :cid')
                    ->setParameter(':cid', $contest->getCid())
                    ->getQuery()
                    ->getSingleScalarResult();
                $contestdata['num_teams'] = ['value' => $teamCount];
            }

            if (ALLOW_REMOVED_INTERVALS) {
                $contestdata['num_removed_intervals'] = [
                    'value' => $removedIntervals[$contest->getCid()]['num_removed_intervals'] ?? 0
                ];
            }
            $contestdata['num_problems'] = ['value' => $problems[$contest->getCid()] ?? 0];

            $timeFields = [
                'activate',
                'start',
                'end',
            ];
            foreach ($timeFields as $timeField) {
                $time = $contestdata[$timeField . 'time']['value'];
                if (!$contest->getStarttimeEnabled() && $timeField != 'activate') {
                    $time      = null;
                    $timeTitle = null;
                }
                if ($time === null) {
                    $timeValue = '-';
                    $timeTitle = '-';
                } else {
                    $timeValue = Utils::printtime($time, $timeFormat);
                    $timeTitle = Utils::printtime($time, '%Y-%m-%d %H:%M:%S (%Z)');
                }
                $contestdata[$timeField . 'time']['value']     = $timeValue;
                $contestdata[$timeField . 'time']['sortvalue'] = $time;
                $contestdata[$timeField . 'time']['title']     = $timeTitle;
            }

            $styles = [];
            if (!$contest->getEnabled()) {
                $styles[] = 'disabled';
            }
            if (in_array($contest->getCid(), array_keys($currentContests))) {
                $styles[] = 'highlight';
            }
            $contests_table[] = [
                'data' => $contestdata,
                'actions' => $contestactions,
                'link' => $this->generateUrl('jury_contest', ['contestId' => $contest->getCid()]),
                'cssclass' => implode(' ', $styles),
            ];
        }

        /** @var Contest $upcomingContest */
        $upcomingContest = $em->createQueryBuilder()
            ->from(Contest::class, 'c')
            ->select('c')
            ->andWhere('c.activatetime > :now')
            ->andWhere('c.enabled = 1')
            ->setParameter(':now', Utils::now())
            ->orderBy('c.activatetime')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $this->render('jury/contests.html.twig', [
            'upcoming_contest' => $upcomingContest,
            'contests_table' => $contests_table,
            'table_fields' => $table_fields,
            'num_actions' => $this->isGranted('ROLE_ADMIN') ? 2 : 0,
        ]);
    }

    /**
     * @Route("/{contestId<\d+>}", name="jury_contest")
     * @param Request $request
     * @param int     $contestId
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function viewAction(Request $request, int $contestId)
    {
        /** @var Contest $contest */
        $contest = $this->em->getRepository(Contest::class)->find($contestId);
        if (!$contest) {
            throw new NotFoundHttpException(sprintf('Contest with ID %s not found', $contestId));
        }

        $etcDir = $this->dj->getDomjudgeEtcDir();
        require_once $etcDir . '/domserver-config.php';

        $newRemovedInterval = new RemovedInterval();
        $newRemovedInterval->setContest($contest);
        $contest->addRemovedInterval($newRemovedInterval);
        $form = $this->createForm(RemovedIntervalType::class, $newRemovedInterval);
        $form->handleRequest($request);
        if ($this->isGranted('ROLE_ADMIN') && $form->isSubmitted() && $form->isValid()) {
            $this->em->persist($newRemovedInterval);
            $this->em->flush();

            return $this->redirectToRoute('jury_contest', ['contestId' => $contestId]);
        }

        /** @var RemovedInterval[] $removedIntervals */
        $removedIntervals = $this->em->createQueryBuilder()
            ->from(RemovedInterval::class, 'i')
            ->select('i')
            ->andWhere('i.contest = :contest')
            ->setParameter(':contest', $contest)
            ->orderBy('i.starttime')
            ->getQuery()
            ->getResult();

        /** @var ContestProblem[] $problems */
        $problems = $this->em->createQueryBuilder()
            ->from(ContestProblem::class, 'cp')
            ->join('cp.problem', 'p')
            ->select('cp', 'partial p.{probid,externalid,name,timelimit,memlimit}')
            ->andWhere('cp.contest = :contest')
            ->setParameter(':contest', $contest)
            ->orderBy('cp.shortname')
            ->getQuery()
            ->getResult();

        return $this->render('jury/contest.html.twig', [
            'contest' => $contest,
            'isActive' => isset($this->dj->getCurrentContests()[$contest->getCid()]),
            'allowRemovedIntervals' => ALLOW_REMOVED_INTERVALS,
            'removedIntervalForm' => $form->createView(),
            'removedIntervals' => $removedIntervals,
            'problems' => $problems,
        ]);
    }

    /**
     * @Route("/{contestId<\d+>}/remove-interval/{intervalId}",
     *        name="jury_contest_remove_interval", methods={"POST"})
     * @param int $contestId
     * @param int $intervalId
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function removeIntervalAction(int $contestId, int $intervalId)
    {
        /** @var Contest $contest */
        $contest = $this->em->getRepository(Contest::class)->find($contestId);
        if (!$contest) {
            throw new NotFoundHttpException(sprintf('Contest with ID %s not found', $contestId));
        }

        /** @var RemovedInterval $removedInterval */
        $removedInterval = $this->em->getRepository(RemovedInterval::class)->find($intervalId);
        if (!$contest) {
            throw new NotFoundHttpException(
                sprintf('Removed interval with ID %s not found', $intervalId)
            );
        }

        if ($removedInterval->getContest()->getCid() !== $contest->getCid()) {
            throw new NotFoundHttpException('Removed interval is of wrong contest');
        }

        $contest->removeRemovedInterval($removedInterval);
        $this->em->remove($removedInterval);
        // Recalculate timing
        $contest->setStarttimeString($contest->getStarttimeString());
        $this->em->flush();

        return $this->redirectToRoute('jury_contest', ['contestId' => $contest->getCid()]);
    }

    /**
     * @Route("/{contestId<\d+>}/edit", name="jury_contest_edit")
     * @IsGranted("ROLE_ADMIN")
     * @param Request $request
     * @param int     $contestId
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    public function editAction(Request $request, int $contestId)
    {
        /** @var Contest $contest */
        $contest = $this->em->getRepository(Contest::class)->find($contestId);
        if (!$contest) {
            throw new NotFoundHttpException(sprintf('Contest with ID %s not found', $contestId));
        }

        $form = $this->createForm(ContestType::class, $contest);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // We need to explicitly assign the contest on all problems, because
            // otherwise we can not save new problems on the contest.
            /** @var ContestProblem[] $problems */
            $problems = $contest->getProblems()->toArray();
            foreach ($problems as $problem) {
                $problem->setContest($contest);
            }

            $this->saveEntity($this->em, $this->eventLogService, $this->dj, $contest,
                              $contest->getCid(), false);
            return $this->redirect($this->generateUrl(
                'jury_contest',
                ['contestId' => $contest->getcid()]
            ));
        }

        $this->em->refresh($contest);

        return $this->render('jury/contest_edit.html.twig', [
            'contest' => $contest,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{contestId<\d+>}/delete", name="jury_contest_delete")
     * @IsGranted("ROLE_ADMIN")
     * @param Request $request
     * @param int     $contestId
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    public function deleteAction(Request $request, int $contestId)
    {
        /** @var Contest $contest */
        $contest = $this->em->getRepository(Contest::class)->find($contestId);
        if (!$contest) {
            throw new NotFoundHttpException(sprintf('Contest with ID %s not found', $contestId));
        }

        return $this->deleteEntity($request, $this->em, $this->dj, $this->kernel, $contest,
                                   $contest->getName(), $this->generateUrl('jury_contests'));
    }

    /**
     * @Route("/{contestId<\d+>}/problems/{probId<\d+>}/delete", name="jury_contest_problem_delete")
     * @IsGranted("ROLE_ADMIN")
     * @param Request $request
     * @param int     $contestId
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    public function deleteProblemAction(Request $request, int $contestId, int $probId)
    {
        /** @var ContestProblem $contestProblem */
        $contestProblem = $this->em->getRepository(ContestProblem::class)->find([
            'contest' => $contestId,
            'problem' => $probId
        ]);
        if (!$contestProblem) {
            throw new NotFoundHttpException(
                sprintf('Contest problem with contest ID %s and problem ID %s not found',
                        $contestId, $probId)
            );
        }

        return $this->deleteEntity($request, $this->em, $this->dj, $this->kernel,
                                   $contestProblem, $contestProblem->getShortname(),
                                   $this->generateUrl('jury_contest', ['contestId' => $contestId]));
    }

    /**
     * @Route("/add", name="jury_contest_add")
     * @IsGranted("ROLE_ADMIN")
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function addAction(Request $request)
    {
        $contest = new Contest();
        // Set default activate time
        $contest->setActivatetimeString(strftime('%Y-%m-%d %H:%M:00 ') . date_default_timezone_get());

        $form = $this->createForm(ContestType::class, $contest);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->transactional(function () use ($contest) {
                // A little 'hack': we need to first persist and save the
                // contest, before we can persist and save the problem,
                // because we need a contest ID
                /** @var ContestProblem[] $problems */
                $problems = $contest->getProblems()->toArray();
                foreach ($contest->getProblems() as $problem) {
                    $contest->removeProblem($problem);
                }
                $this->em->persist($contest);
                $this->em->flush();

                // Now we can assign the problems to the contest and persist them
                foreach ($problems as $problem) {
                    $problem->setContest($contest);
                    $this->em->persist($problem);
                }
                $this->saveEntity($this->em, $this->eventLogService, $this->dj, $contest,
                                  $contest->getCid(), true);
            });
            return $this->redirect($this->generateUrl(
                'jury_contest',
                ['contestId' => $contest->getcid()]
            ));
        }

        return $this->render('jury/contest_add.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{contestId<\d+>}/finalize", name="jury_contest_finalize")
     * @IsGranted("ROLE_ADMIN")
     * @param Request $request
     * @param int     $contestId
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function finalizeAction(Request $request, int $contestId)
    {
        /** @var Contest $contest */
        $contest  = $this->em->getRepository(Contest::class)->find($contestId);
        $blockers = [];
        if (Utils::difftime((float)$contest->getEndtime(), Utils::now()) > 0) {
            $blockers[] = sprintf('Contest not ended yet (will end at %s)',
                                  Utils::printtime($contest->getEndtime(), '%Y-%m-%d %H:%M:%S (%Z)'));
        }

        /** @var int[] $submissionIds */
        $submissionIds = array_map(function (array $data) {
            return $data['submitid'];
        }, $this->em->createQueryBuilder()
               ->from(Submission::class, 's')
               ->join('s.judgings', 'j', Join::WITH, 'j.valid = 1')
               ->select('s.submitid')
               ->andWhere('s.contest = :contest')
               ->andWhere('s.valid = true')
               ->andWhere('j.result IS NULL')
               ->setParameter(':contest', $contest)
               ->orderBy('s.submitid')
               ->getQuery()
               ->getResult()
        );

        if (count($submissionIds) > 0) {
            $blockers[] = 'Unjudged submissions found: s' . implode(', s', $submissionIds);
        }

        /** @var int[] $clarificationIds */
        $clarificationIds = array_map(function (array $data) {
            return $data['clarid'];
        }, $this->em->createQueryBuilder()
               ->from(Clarification::class, 'c')
               ->select('c.clarid')
               ->andWhere('c.contest = :contest')
               ->andWhere('c.answered = false')
               ->setParameter(':contest', $contest)
               ->getQuery()
               ->getResult()
        );
        if (count($clarificationIds) > 0) {
            $blockers[] = 'Unanswered clarifications found: ' . implode(', ', $clarificationIds);
        }

        if (empty($contest->getFinalizecomment())) {
            $contest->setFinalizecomment(sprintf('Finalized by: %s', $this->dj->getUser()->getName()));
        }
        $form = $this->createForm(FinalizeContestType::class, $contest);

        if (empty($blockers)) {
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $contest->setFinalizetime(Utils::now());
                $this->em->flush();
                $this->dj->auditlog('contest', $contest->getCid(), 'finalized',
                                                 $contest->getFinalizecomment());
                return $this->redirectToRoute('jury_contest', ['contestId' => $contest->getCid()]);
            }
        }

        return $this->render('jury/contest_finalize.html.twig', [
            'contest' => $contest,
            'blockers' => $blockers,
            'form' => $form->createView(),
        ]);
    }
}
