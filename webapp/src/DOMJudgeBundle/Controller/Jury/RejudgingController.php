<?php declare(strict_types=1);

namespace DOMJudgeBundle\Controller\Jury;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use DOMJudgeBundle\Entity\Contest;
use DOMJudgeBundle\Entity\Judging;
use DOMJudgeBundle\Entity\Problem;
use DOMJudgeBundle\Entity\Rejudging;
use DOMJudgeBundle\Entity\Submission;
use DOMJudgeBundle\Entity\Team;
use DOMJudgeBundle\Form\Type\RejudgingType;
use DOMJudgeBundle\Service\DOMJudgeService;
use DOMJudgeBundle\Service\RejudgingService;
use DOMJudgeBundle\Service\ScoreboardService;
use DOMJudgeBundle\Service\SubmissionService;
use DOMJudgeBundle\Utils\Utils;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/jury")
 * @Security("has_role('ROLE_JURY')")
 */
class RejudgingController extends Controller
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
     * @var SessionInterface
     */
    protected $session;

    public function __construct(
        EntityManagerInterface $entityManager,
        DOMJudgeService $DOMJudgeService,
        SessionInterface $session
    ) {
        $this->entityManager   = $entityManager;
        $this->DOMJudgeService = $DOMJudgeService;
        $this->session         = $session;
    }

    /**
     * @Route("/rejudgings/", name="jury_rejudgings")
     */
    public function indexAction(Request $request)
    {
        /** @var Rejudging[] $rejudgings */
        $rejudgings = $this->entityManager->createQueryBuilder()
            ->select('r')
            ->from('DOMJudgeBundle:Rejudging', 'r')
            ->leftJoin('r.start_user', 's')
            ->leftJoin('r.finish_user', 'f')
            ->orderBy('r.rejudgingid', 'DESC')
            ->getQuery()->getResult();

        $table_fields = [
            'rejudgingid' => [
                'title' => 'ID',
                'sort' => true,
                'default_sort' => true,
                'default_sort_order' => 'desc'
            ],
            'reason' => ['title' => 'reason', 'sort' => true],
            'startuser' => ['title' => 'startuser', 'sort' => true],
            'finishuser' => ['title' => 'finishuser', 'sort' => true],
            'starttime' => ['title' => 'starttime', 'sort' => true],
            'endtime' => ['title' => 'finishtime', 'sort' => true],
            'status' => ['title' => 'status', 'sort' => true],
        ];

        $timeFormat       = (string)$this->DOMJudgeService->dbconfig_get('time_format', '%H:%M');
        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $rejudgings_table = [];
        foreach ($rejudgings as $rejudging) {
            $rejudgingdata = [];
            // Get whatever fields we can from the problem object itself
            foreach ($table_fields as $k => $v) {
                if ($propertyAccessor->isReadable($rejudging, $k)) {
                    $rejudgingdata[$k] = ['value' => $propertyAccessor->getValue($rejudging, $k)];
                }
            }

            if ($rejudging->getStartUser()) {
                $rejudgingdata['startuser']['value'] = $rejudging->getStartUser()->getName();
            }
            if ($rejudging->getFinishUser()) {
                $rejudgingdata['finishuser']['value'] = $rejudging->getFinishUser()->getName();
            }

            $todo = $this->entityManager->createQueryBuilder()
                ->from('DOMJudgeBundle:Submission', 's')
                ->select('COUNT(s)')
                ->andWhere('s.rejudging = :rejudging')
                ->setParameter(':rejudging', $rejudging)
                ->getQuery()
                ->getSingleScalarResult();

            $done = $this->entityManager->createQueryBuilder()
                ->from('DOMJudgeBundle:Judging', 'j')
                ->select('COUNT(j)')
                ->andWhere('j.rejudging = :rejudging')
                ->andWhere('j.endtime IS NOT NULL')
                ->setParameter(':rejudging', $rejudging)
                ->getQuery()
                ->getSingleScalarResult();

            $todo -= $done;

            if ($rejudging->getEndtime() !== null) {
                $status = $rejudging->getValid() ? 'applied' : 'canceled';
            } elseif ($todo > 0) {
                $perc   = (int)(100 * ((double)$done / (double)($done + $todo)));
                $status = sprintf("%d%% done", $perc);
            } else {
                $status = 'ready';
            }

            $rejudgingdata['starttime']['value'] = Utils::printtime($rejudging->getStarttime(), $timeFormat);
            $rejudgingdata['endtime']['value']   = Utils::printtime($rejudging->getEndtime(), $timeFormat);
            $rejudgingdata['status']['value']    = $status;

            if ($rejudging->getEndtime() !== null) {
                $class = 'disabled';
            } else {
                $class = $todo > 0 ? '' : 'unseen';
            }

            // Save this to our list of rows
            $rejudgings_table[] = [
                'data' => $rejudgingdata,
                'actions' => [],
                'link' => $this->generateUrl('jury_rejudging', ['rejudgingId' => $rejudging->getRejudgingid()]),
                'cssclass' => $class,
            ];
        }

        $twigData = [
            'rejudgings' => $rejudgings_table,
            'table_fields' => $table_fields,
            'refresh' => [
                'after' => 15,
                'url' => $this->generateUrl('jury_rejudgings'),
            ],
        ];

        return $this->render('@DOMJudge/jury/rejudgings.html.twig', $twigData);
    }

    /**
     * @Route("/rejudgings/{rejudgingId}", name="jury_rejudging", requirements={"rejudgingId": "\d+"})
     * @param Request           $request
     * @param SubmissionService $submissionService
     * @param int               $rejudgingId
     * @return Response
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function viewAction(
        Request $request,
        SubmissionService $submissionService,
        int $rejudgingId
    ) {
        // Close the session, as this might take a while and we don't need the session below
        $this->session->save();

        /** @var Rejudging $rejudging */
        $rejudging = $this->entityManager->createQueryBuilder()
            ->from('DOMJudgeBundle:Rejudging', 'r')
            ->leftJoin('r.start_user', 's')
            ->leftJoin('r.finish_user', 'f')
            ->select('r', 's', 'f')
            ->andWhere('r.rejudgingid = :rejudgingid')
            ->setParameter(':rejudgingid', $rejudgingId)
            ->getQuery()
            ->getOneOrNullResult();

        $todo = $this->entityManager->createQueryBuilder()
            ->from('DOMJudgeBundle:Submission', 's')
            ->select('COUNT(s)')
            ->andWhere('s.rejudging = :rejudging')
            ->setParameter(':rejudging', $rejudging)
            ->getQuery()
            ->getSingleScalarResult();

        $done = $this->entityManager->createQueryBuilder()
            ->from('DOMJudgeBundle:Judging', 'j')
            ->select('COUNT(j)')
            ->andWhere('j.rejudging = :rejudging')
            ->andWhere('j.endtime IS NOT NULL')
            ->setParameter(':rejudging', $rejudging)
            ->getQuery()
            ->getSingleScalarResult();

        $todo -= $done;

        global $VERDICTS;
        $commonConfig = $this->DOMJudgeService->getDomjudgeEtcDir() . '/common-config.php';
        require_once $commonConfig;
        /** @var string[] $VERDICTS */

        $used         = [];
        $verdictTable = [];
        // pre-fill $verdictTable to get a consistent ordering
        foreach ($VERDICTS as $verdict => $abbrev) {
            foreach ($VERDICTS as $verdict2 => $abbrev2) {
                $verdictTable[$verdict][$verdict2] = array();
            }
        }

        /** @var Judging[] $originalVerdicts */
        /** @var Judging[] $newVerdicts */
        $originalVerdicts = [];
        $newVerdicts      = [];

        $this->entityManager->transactional(function () use ($rejudging, &$originalVerdicts, &$newVerdicts) {
            $expr             = $this->entityManager->getExpressionBuilder();
            $originalVerdicts = $this->entityManager->createQueryBuilder()
                ->from('DOMJudgeBundle:Judging', 'j', 'j.submitid')
                ->select('j')
                ->where(
                    $expr->in('j.judgingid',
                              $this->entityManager->createQueryBuilder()
                                  ->from('DOMJudgeBundle:Judging', 'j2')
                                  ->select('j2.prevjudgingid')
                                  ->andWhere('j2.rejudging = :rejudging')
                                  ->andWhere('j2.endtime IS NOT NULL')
                                  ->getDQL()
                    )
                )
                ->setParameter(':rejudging', $rejudging)
                ->getQuery()
                ->getResult();

            $newVerdicts = $this->entityManager->createQueryBuilder()
                ->from('DOMJudgeBundle:Judging', 'j', 'j.submitid')
                ->select('j')
                ->andWhere('j.rejudging = :rejudging')
                ->andWhere('j.endtime IS NOT NULL')
                ->setParameter(':rejudging', $rejudging)
                ->getQuery()
                ->getResult();
        });

        // Helper function to add verdicts
        $addVerdict = function ($unknownVerdict) use ($VERDICTS, &$verdictTable) {
            // add column to existing rows
            foreach ($VERDICTS as $verdict => $abbreviation) {
                $verdictTable[$verdict][$unknownVerdict] = [];
            }
            // add verdict to known verdicts
            $verdicts[$unknownVerdict] = $unknownVerdict;
            // add row
            $verdictTable[$unknownVerdict] = [];
            foreach ($VERDICTS as $verdict => $abbreviation) {
                $verdictTable[$unknownVerdict][$verdict] = [];
            }
        };

        // Build up the verdict matrix
        foreach ($newVerdicts as $submitid => $newVerdict) {
            $originalVerdict = $originalVerdicts[$submitid];

            // add verdicts to data structures if they are unknown up to now
            foreach ([$newVerdict, $originalVerdict] as $verdict) {
                if (!array_key_exists($verdict->getResult(), $VERDICTS)) {
                    $addVerdict($verdict->getResult());
                }
            }

            // mark them as used, so we can filter out unused cols/rows later
            $used[$originalVerdict->getResult()] = true;
            $used[$newVerdict->getResult()]      = true;

            // append submitid to list of orig->new verdicts
            $verdictTable[$originalVerdict->getResult()][$newVerdict->getResult()][] = $submitid;
        }

        $viewTypes = [0 => 'newest', 1 => 'unverified', 2 => 'unjudged', 3 => 'diff', 4 => 'all'];
        $view      = 3;
        if ($request->query->has('view')) {
            $index = array_search($request->query->get('view'), $viewTypes);
            if ($index !== false) {
                $view = $index;
            }
        }

        $restrictions = ['rejudgingid' => $rejudgingId];
        if ($viewTypes[$view] == 'unverified') {
            $restrictions['verified'] = 0;
        }
        if ($viewTypes[$view] == 'unjudged') {
            $restrictions['judged'] = 0;
        }
        if ($viewTypes[$view] == 'diff') {
            $restrictions['rejudgingdiff'] = 1;
        }
        if ($request->query->get('oldverdict', 'all') !== 'all') {
            $restrictions['old_result'] = $request->query->get('oldverdict');
        }
        if ($request->query->get('newverdict', 'all') !== 'all') {
            $restrictions['result'] = $request->query->get('newverdict');
        }

        /** @var Submission[] $submissions */
        list($submissions, $submissionCounts) = $submissionService->getSubmissionList(
            $this->DOMJudgeService->getCurrentContests(),
            $restrictions
        );

        $data = [
            'rejudging' => $rejudging,
            'todo' => $todo,
            'verdicts' => $VERDICTS,
            'used' => $used,
            'verdictTable' => $verdictTable,
            'viewTypes' => $viewTypes,
            'view' => $view,
            'submissions' => $submissions,
            'submissionCounts' => $submissionCounts,
            'oldverdict' => $request->query->get('oldverdict', 'all'),
            'newverdict' => $request->query->get('newverdict', 'all'),
            'refresh' => [
                'after' => 15,
                'url' => $request->getRequestUri(),
                'ajax' => true,
            ],
        ];
        if ($request->isXmlHttpRequest()) {
            $data['ajax'] = true;
            return $this->render('@DOMJudge/jury/partials/rejudging_submissions.html.twig', $data);
        } else {
            return $this->render('@DOMJudge/jury/rejudging.html.twig', $data);
        }
    }

    /**
     * @Route(
     *     "/rejudgings/{rejudgingId}/{action}",
     *     name="jury_rejudging_finish",
     *     requirements={"action": "cancel|apply"}
     * )
     * @param Request          $request
     * @param RejudgingService $rejudgingService
     * @param int              $rejudgingId
     * @param string           $action
     * @return Response|StreamedResponse
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function finishAction(Request $request, RejudgingService $rejudgingService, int $rejudgingId, string $action)
    {
        // Note: we use a XMLHttpRequest here as Symfony does not support streaming Twig outpit

        // Disable the profiler toolbar to avoid OOMs.
        if ($this->container->has('profiler')) {
            $this->container->get('profiler')->disable();
        }

        /** @var Rejudging $rejudging */
        $rejudging = $this->entityManager->createQueryBuilder()
            ->from('DOMJudgeBundle:Rejudging', 'r')
            ->select('r')
            ->andWhere('r.rejudgingid = :rejudgingid')
            ->setParameter(':rejudgingid', $rejudgingId)
            ->getQuery()
            ->getOneOrNullResult();

        if ($request->isXmlHttpRequest()) {
            $response = new StreamedResponse();
            $response->headers->set('X-Accel-Buffering', 'no');
            $progressReporter = function (string $data, bool $isError = false) {
                if ($isError) {
                    echo sprintf('<div class="alert alert-danger">%s</div>', $data);
                } else {
                    echo $data;
                }
                ob_flush();
                flush();
            };
            $response         = new StreamedResponse();
            $response->headers->set('X-Accel-Buffering', 'no');
            $response->setCallback(function () use ($progressReporter, $rejudging, $rejudgingService, $action) {
                $timeStart = microtime(true);
                if ($rejudgingService->finishRejudging($rejudging, $action, $progressReporter)) {
                    $timeEnd      = microtime(true);
                    $timeDiff     = sprintf('%.2f', $timeEnd - $timeStart);
                    $rejudgingUrl = $this->generateUrl('jury_rejudging',
                                                       ['rejudgingId' => $rejudging->getRejudgingid()]);
                    echo sprintf(
                        '<br/><br/><p>Rejudging <a href="%s">r%d</a> %s in %s seconds.</p>',
                        $rejudgingUrl, $rejudging->getRejudgingid(),
                        $action == RejudgingService::ACTION_APPLY ? 'applied' : 'canceled', $timeDiff
                    );
                }
            });

            return $response;
        } else {
            return $this->render('@DOMJudge/jury/rejudging_finish.html.twig', [
                'action' => $action,
                'rejudging' => $rejudging,
            ]);
        }
    }

    /**
     * @Route("/rejudgings/add", name="jury_rejudging_add")
     * @param Request $request
     * @param ScoreboardService $scoreboardService
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    public function addAction(Request $request, ScoreboardService $scoreboardService)
    {
        $rejudging = new Rejudging();
        $form = $this->createForm(RejudgingType::class, $rejudging);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $formData = $form->all();
            $reason = $formData['reason']->getViewData();

            $queryBuilder = $this->entityManager->createQueryBuilder()
                ->from('DOMJudgeBundle:Judging', 'j')
                ->leftJoin('j.submission', 's')
                ->select('j', 's')
                ->andWhere('j.valid = 1');

            $contests = $formData['contest']->getData();
            if (count($contests)) {
                $queryBuilder
                    ->andWhere('j.contest IN (:contests)')
                    ->setParameter(':contests', $contests);
            }
            $problems = $formData['problem']->getData();
            if (count($problems)) {
                $queryBuilder
                    ->andWhere('s.problem IN (:problems)')
                    ->setParameter(':problems', $problems);
            }
            $languages = $formData['language']->getData();
            if (count($languages)) {
                $queryBuilder
                    ->andWhere('s.language IN (:languages)')
                    ->setParameter(':languages', $languages);
            }
            $teams = $formData['team']->getData();
            if (count($teams)) {
                $queryBuilder
                    ->andWhere('s.team IN (:teams)')
                    ->setParameter(':teams', $teams);
            }
            $judgehosts = $formData['judgehost']->getData();
            if (count($judgehosts)) {
                $queryBuilder
                    ->andWhere('j.judgehost IN (:judgehosts)')
                    ->setParameter(':judgehosts', $judgehosts);
            }
            $verdicts = $formData['verdict']->getViewData();
            if (count($verdicts)) {
                $queryBuilder
                    ->andWhere('j.result IN (:verdicts)')
                    ->setParameter(':verdicts', $verdicts);
            }

            /** @var array[] $judgings */
            $judgings = $queryBuilder
                ->getQuery()
                ->getResult(Query::HYDRATE_ARRAY);
            if (empty($judgings)) {
                throw new BadRequestHttpException('No judgings matched.');
            }
            $rejudging = $this->createRejudging($reason, $judgings, true, $scoreboardService);
            return $this->redirectToRoute('jury_rejudging', ['rejudgingId' => $rejudging->getRejudgingid()]);
        }
        return $this->render('@DOMJudge/jury/rejudging_form.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/rejudge/", methods={"POST"}, name="jury_create_rejudge")
     * @param Request           $request
     * @param ScoreboardService $scoreboardService
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function createAction(Request $request, ScoreboardService $scoreboardService)
    {
        $table       = $request->request->get('table');
        $id          = $request->request->get('id');
        $reason      = $request->request->get('reason') ?: sprintf('%s: %s', $table, $id);
        $includeAll  = (bool)$request->request->get('include_all');
        $fullRejudge = (bool)$request->request->get('full_rejudge');

        if (empty($table) || empty($id)) {
            throw new BadRequestHttpException('No table or id passed for selection in rejudging');
        }

        if ($includeAll && !$this->DOMJudgeService->checkrole('admin')) {
            throw new BadRequestHttpException('Rejudging pending/correct submissions requires admin rights');
        }

        // Special case 'submission' for admin overrides
        if ($this->DOMJudgeService->checkrole('admin') && ($table == 'submission')) {
            $includeAll = true;
        }

        /* These are the tables that we can deal with. */
        $tablemap = [
            'contest' => 's.cid',
            'judgehost' => 'j.judgehost',
            'language' => 's.langid',
            'problem' => 's.probid',
            'submission' => 's.submitid',
            'team' => 's.teamid'
        ];

        if (!isset($tablemap[$table])) {
            throw new BadRequestHttpException(sprintf('unknown table %s in rejudging', $table));
        }

        // Only rejudge submissions in active contests.
        $contests = $this->DOMJudgeService->getCurrentContests();

        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->from('DOMJudgeBundle:Judging', 'j')
            ->leftJoin('j.submission', 's')
            ->select('j', 's')
            ->andWhere('j.contest IN (:contests)')
            ->andWhere('j.valid = 1')
            ->andWhere(sprintf('%s = :id', $tablemap[$table]))
            ->setParameter(':contests', $contests)
            ->setParameter(':id', $id);

        if ($includeAll && $fullRejudge) {
            $queryBuilder
                ->andWhere('j.result IS NOT NULL')
                ->andWhere('j.valid = 1');
        } elseif (!$includeAll) {
            $queryBuilder
                ->andWhere('j.result != :correct')
                ->setParameter(':correct', 'correct');
        }

        /** @var array[] $judgings */
        $judgings = $queryBuilder
            ->getQuery()
            ->getResult(Query::HYDRATE_ARRAY);

        if (empty($judgings)) {
            throw new BadRequestHttpException('No judgings matched.');
        }

        $rejudging = $this->createRejudging($reason, $judgings, $fullRejudge, $scoreboardService);

        if ($rejudging) {
            return $this->redirectToRoute('jury_rejudging', ['rejudgingId' => $rejudging->getRejudgingid()]);
        } else {
            switch ($table) {
                case 'contest':
                    return $this->redirectToRoute('jury_contest', ['contestId' => $id]);
                case 'judgehost':
                    return $this->redirectToRoute('jury_judgehost', ['hostname' => $id]);
                case 'language':
                    return $this->redirectToRoute('jury_language', ['langId' => $id]);
                case 'problem':
                    return $this->redirectToRoute('jury_problem', ['probId' => $id]);
                case 'submission':
                    return $this->redirectToRoute('jury_submission', ['submitId' => $id]);
                case 'team':
                    return $this->redirectToRoute('jury_team', ['teamId' => $id]);
            }
        }
    }

    public function createRejudging($reason, $judgings, $fullRejudge, $scoreboardService) {
        /** @var Rejudging|null $rejudging */
        $rejudging = null;
        if ($fullRejudge) {
            $rejudging = new Rejudging();
            $rejudging
                ->setStartUser($this->DOMJudgeService->getUser())
                ->setStarttime(Utils::now())
                ->setReason($reason);
            $this->entityManager->persist($rejudging);
            $this->entityManager->flush();
        }

        $singleJudging = count($judgings) == 1;
        foreach ($judgings as $judging) {
            $submission = $judging['submission'];
            if ($submission['rejudgingid'] !== null) {
                // Already associated rejudging
                if ($singleJudging) {
                    // Clean up before throwing an error
                    if ($rejudging) {
                        $this->entityManager->remove($rejudging);
                        $this->entityManager->flush();
                    }
                    throw new BadRequestHttpException(sprintf('Submission is already part of rejudging r%d',
                                                              $submission['rejudgingid']));
                } else {
                    // silently skip that submission
                    continue;
                }
            }

            $this->entityManager->transactional(function () use (
                $singleJudging,
                $fullRejudge,
                $judging,
                $submission,
                $rejudging,
                $scoreboardService
            ) {
                if (!$fullRejudge) {
                    $this->entityManager->getConnection()->executeUpdate('UPDATE judging SET valid = false WHERE judgingid = :judgingid',
                                                                         [
                                                                             ':judgingid' => $judging['judgingid'],
                                                                         ]);
                }

                $this->entityManager->getConnection()->executeUpdate('UPDATE submission SET judgehost = null WHERE submitid = :submitid AND rejudgingid IS NULL',
                                                                     [
                                                                         ':submitid' => $submission['submitid'],
                                                                     ]);
                if ($rejudging !== null) {
                    $this->entityManager->getConnection()->executeUpdate('UPDATE submission SET rejudgingid = :rejudgingid WHERE submitid = :submitid AND rejudgingid IS NULL',
                                                                         [
                                                                             ':rejudgingid' => $rejudging->getRejudgingid(),
                                                                             ':submitid' => $submission['submitid'],
                                                                         ]);
                }

                if ($singleJudging) {
                    $teamid = $submission['teamid'];
                    if ($teamid) {
                        $this->entityManager->getConnection()->executeUpdate('UPDATE team SET judging_last_started = null WHERE teamid = :teamid',
                                                                             [
                                                                                 ':teamid' => $teamid,
                                                                             ]);
                    }
                }

                if (!$fullRejudge) {
                    // Clear entity manager to get fresh data
                    $this->entityManager->clear();
                    $contest = $this->entityManager->getRepository(Contest::class)
                        ->find($submission['cid']);
                    $team    = $this->entityManager->getRepository(Team::class)
                        ->find($submission['teamid']);
                    $problem = $this->entityManager->getRepository(Problem::class)
                        ->find($submission['probid']);
                    $scoreboardService->calculateScoreRow($contest, $team, $problem);
                }
            });

            if (!$fullRejudge) {
                $this->DOMJudgeService->auditlog('judging', $judging['judgingid'], 'mark invalid', '(rejudge)');
            }
        }
        return $rejudging;
    }
}
