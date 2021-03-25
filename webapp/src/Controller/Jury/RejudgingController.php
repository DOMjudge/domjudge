<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Controller\BaseController;
use App\Entity\Contest;
use App\Entity\Judging;
use App\Entity\JudgingRun;
use App\Entity\Rejudging;
use App\Entity\Submission;
use App\Form\Type\RejudgingType;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\RejudgingService;
use App\Service\SubmissionService;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query;
use Exception;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;

/**
 * @Route("/jury/rejudgings")
 * @IsGranted("ROLE_JURY")
 */
class RejudgingController extends BaseController
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
     * @var ConfigurationService
     */
    protected $config;

    /**
     * @var RejudgingService
     */
    protected $rejudgingService;

    /**
     * @var RouterInterface
     */
    protected $router;

    /**
     * @var SessionInterface
     */
    protected $session;

    public function __construct(
        EntityManagerInterface $em,
        DOMJudgeService $dj,
        ConfigurationService $config,
        RejudgingService $rejudgingService,
        RouterInterface $router,
        SessionInterface $session
    ) {
        $this->em               = $em;
        $this->dj               = $dj;
        $this->config           = $config;
        $this->rejudgingService = $rejudgingService;
        $this->router           = $router;
        $this->session          = $session;
    }

    /**
     * @Route("", name="jury_rejudgings")
     */
    public function indexAction(Request $request): Response
    {
        /** @var Rejudging[] $rejudgings */
        $rejudgings = $this->em->createQueryBuilder()
            ->select('r')
            ->from(Rejudging::class, 'r')
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

        $timeFormat       = (string)$this->config->get('time_format');
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
            if ($rejudging->getEndtime() !== null) {
                $rejudgingdata['finishuser']['value'] = $rejudging->getFinishUser() !== null
                    ? $rejudging->getFinishUser()->getName() : (
                        $rejudging->getRepeat() > 1 ? "part of repeated rejudging" : "automatically applied");
            }

            $todoAndDone = $this->rejudgingService->calculateTodo($rejudging);
            $todo = $todoAndDone['todo'];
            $done = $todoAndDone['done'];

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

        return $this->render('jury/rejudgings.html.twig', $twigData);
    }

    /**
     * @Route("/{rejudgingId<\d+>}", name="jury_rejudging")
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function viewAction(
        Request $request,
        SubmissionService $submissionService,
        int $rejudgingId
    ): Response {
        // Close the session, as this might take a while and we don't need the session below
        $this->session->save();

        /** @var Rejudging $rejudging */
        $rejudging = $this->em->createQueryBuilder()
            ->from(Rejudging::class, 'r')
            ->leftJoin('r.start_user', 's')
            ->leftJoin('r.finish_user', 'f')
            ->select('r', 's', 'f')
            ->andWhere('r.rejudgingid = :rejudgingid')
            ->setParameter(':rejudgingid', $rejudgingId)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$rejudging) {
            throw new NotFoundHttpException(sprintf('Rejudging with ID %s not found', $rejudgingId));
        }
        $todo = $this->rejudgingService->calculateTodo($rejudging)['todo'];

        $verdictsConfig = $this->dj->getDomjudgeEtcDir() . '/verdicts.php';
        $verdicts       = include $verdictsConfig;

        $used         = [];
        $verdictTable = [];
        // pre-fill $verdictTable to get a consistent ordering
        foreach ($verdicts as $verdict => $abbrev) {
            foreach ($verdicts as $verdict2 => $abbrev2) {
                $verdictTable[$verdict][$verdict2] = [];
            }
        }

        /** @var Judging[] $originalVerdicts */
        /** @var Judging[] $newVerdicts */
        $originalVerdicts = [];
        $newVerdicts      = [];

        $this->em->transactional(function () use ($rejudging, &$originalVerdicts, &$newVerdicts) {
            $expr             = $this->em->getExpressionBuilder();
            $originalVerdicts = $this->em->createQueryBuilder()
                ->from(Judging::class, 'j')
                ->join('j.submission', 's')
                ->select('j, s')
                ->where(
                    $expr->in('j.judgingid',
                              $this->em->createQueryBuilder()
                                  ->from(Judging::class, 'j2')
                                  ->join('j2.original_judging', 'jo')
                                  ->select('jo.judgingid')
                                  ->andWhere('j2.rejudging = :rejudging')
                                  ->andWhere('j2.endtime IS NOT NULL')
                                  ->getDQL()
                    )
                )
                ->setParameter(':rejudging', $rejudging)
                ->getQuery()
                ->getResult();

            $newVerdicts = $this->em->createQueryBuilder()
                ->from(Judging::class, 'j')
                ->join('j.submission', 's')
                ->select('j, s')
                ->andWhere('j.rejudging = :rejudging')
                ->andWhere('j.endtime IS NOT NULL')
                ->setParameter(':rejudging', $rejudging)
                ->getQuery()
                ->getResult();

            $getSubmissionId = function (Judging $judging) {
                return $judging->getSubmission()->getSubmitid();
            };
            $originalVerdicts = Utils::reindex($originalVerdicts, $getSubmissionId);
            $newVerdicts = Utils::reindex($newVerdicts, $getSubmissionId);
        });

        // Helper function to add verdicts
        $addVerdict = function ($unknownVerdict) use ($verdicts, &$verdictTable) {
            // add column to existing rows
            foreach ($verdicts as $verdict => $abbreviation) {
                $verdictTable[$verdict][$unknownVerdict] = [];
            }
            // add verdict to known verdicts
            $verdicts[$unknownVerdict] = $unknownVerdict;
            // add row
            $verdictTable[$unknownVerdict] = [];
            foreach ($verdicts as $verdict => $abbreviation) {
                $verdictTable[$unknownVerdict][$verdict] = [];
            }
        };

        // Build up the verdict matrix
        foreach ($newVerdicts as $submitid => $newVerdict) {
            $originalVerdict = $originalVerdicts[$submitid];

            // add verdicts to data structures if they are unknown up to now
            foreach ([$newVerdict, $originalVerdict] as $verdict) {
                if (!array_key_exists($verdict->getResult(), $verdicts)) {
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
        $view      = array_search('diff', $viewTypes);
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
        [$submissions, $submissionCounts] = $submissionService->getSubmissionList(
            $this->dj->getCurrentContests(),
            $restrictions
        );

        $repetitions = $this->em->createQueryBuilder()
            ->from(Rejudging::class, 'r')
            ->select('r.rejudgingid')
            ->andWhere('r.repeatedRejudging = :repeat_rejudgingid')
            ->andWhere('r.rejudgingid != :rejudgingid')
            ->setParameter(':repeat_rejudgingid', $rejudging->getRepeatedRejudging())
            ->setParameter(':rejudgingid', $rejudging->getRejudgingid())
            ->orderBy('r.rejudgingid')
            ->getQuery()
            ->getScalarResult();

        if (count($repetitions) > 0) {
            $stats = $this->getStats($rejudging);
        } else {
            $stats = null;
        }

        $data = [
            'rejudging' => $rejudging,
            'todo' => $todo,
            'verdicts' => $verdicts,
            'used' => $used,
            'verdictTable' => $verdictTable,
            'viewTypes' => $viewTypes,
            'view' => $view,
            'submissions' => $submissions,
            'submissionCounts' => $submissionCounts,
            'oldverdict' => $request->query->get('oldverdict', 'all'),
            'newverdict' => $request->query->get('newverdict', 'all'),
            'repetitions' => array_column($repetitions, 'rejudgingid'),
            'showExternalResult' => $this->config->get('data_source') ==
                DOMJudgeService::DATA_SOURCE_CONFIGURATION_AND_LIVE_EXTERNAL,
            'stats' => $stats,
            'refresh' => [
                'after' => 15,
                'url' => $request->getRequestUri(),
                'ajax' => true,
            ],
        ];
        if ($request->isXmlHttpRequest()) {
            $data['ajax'] = true;
            return $this->render('jury/partials/rejudging_submissions.html.twig', $data);
        } else {
            return $this->render('jury/rejudging.html.twig', $data);
        }
    }

    /**
     * @Route(
     *     "/{rejudgingId<\d+>}/{action<cancel|apply>}",
     *     name="jury_rejudging_finish"
     * )
     * @return Response|StreamedResponse
     * @throws NonUniqueResultException
     */
    public function finishAction(Request $request, RejudgingService $rejudgingService, ?Profiler $profiler, int $rejudgingId, string $action)
    {
        // Note: we use a XMLHttpRequest here as Symfony does not support streaming Twig output

        // Disable the profiler toolbar to avoid OOMs.
        if ($profiler) {
            $profiler->disable();
        }

        /** @var Rejudging $rejudging */
        $rejudging = $this->em->createQueryBuilder()
            ->from(Rejudging::class, 'r')
            ->select('r')
            ->andWhere('r.rejudgingid = :rejudgingid')
            ->setParameter(':rejudgingid', $rejudgingId)
            ->getQuery()
            ->getOneOrNullResult();

        if ($request->isXmlHttpRequest()) {
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
                    $rejudgingUrl = $this->generateUrl(
                        'jury_rejudging',
                        ['rejudgingId' => $rejudging->getRejudgingid()]
                    );
                    echo sprintf(
                        '<br/><br/><p>Rejudging <a href="%s">r%d</a> %s in %s seconds.</p>',
                        $rejudgingUrl, $rejudging->getRejudgingid(),
                        $action == RejudgingService::ACTION_APPLY ? 'applied' : 'canceled', $timeDiff
                    );
                }
            });

            return $response;
        } else {
            return $this->render('jury/rejudging_finish.html.twig', [
                'action' => $action,
                'rejudging' => $rejudging,
            ]);
        }
    }

    /**
     * @Route("/add", name="jury_rejudging_add")
     * @throws Exception
     */
    public function addAction(Request $request, FormFactoryInterface $formFactory): Response
    {
        $formBuilder = $formFactory->createBuilder(RejudgingType::class);
        $formData    = [];
        if (!$request->isXmlHttpRequest()) {
            $currentContest = $this->dj->getCurrentContest();
            $formData['contests'] = is_null($currentContest) ? [] : [$currentContest];
        }
        $verdicts             = $formBuilder->get('verdicts')->getOption('choices');
        $incorrectVerdicts    = array_filter($verdicts, function ($k) {
            return $k != 'correct';
        });
        $formData['verdicts'] = $incorrectVerdicts;

        $form = $formBuilder->setData($formData)->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && !$request->isXmlHttpRequest()) {
            $formData = $form->getData();
            $reason   = $formData['reason'];

            $queryBuilder = $this->em->createQueryBuilder()
                ->from(Judging::class, 'j')
                ->leftJoin('j.submission', 's')
                ->leftJoin('s.rejudging', 'r')
                ->leftJoin('s.team', 't')
                ->select('j', 's', 'r', 't')
                ->andWhere('j.valid = 1');

            $contests = $formData['contests'];
            if (count($contests)) {
                $queryBuilder
                    ->andWhere('j.contest IN (:contests)')
                    ->setParameter(':contests', $contests);
            }
            $problems = $formData['problems'];
            if (count($problems)) {
                $queryBuilder
                    ->andWhere('s.problem IN (:problems)')
                    ->setParameter(':problems', $problems);
            }
            $languages = $formData['languages'];
            if (count($languages)) {
                $queryBuilder
                    ->andWhere('s.language IN (:languages)')
                    ->setParameter(':languages', $languages);
            }
            $teams = $formData['teams'];
            if (count($teams)) {
                $queryBuilder
                    ->andWhere('s.team IN (:teams)')
                    ->setParameter(':teams', $teams);
            }
            $judgehosts = $formData['judgehosts'];
            if (count($judgehosts)) {
                $queryBuilder
                    ->andWhere('j.judgehost IN (:judgehosts)')
                    ->setParameter(':judgehosts', $judgehosts);
            }
            $verdicts = $formData['verdicts'];
            if (count($verdicts)) {
                $queryBuilder
                    ->andWhere('j.result IN (:verdicts)')
                    ->setParameter(':verdicts', $verdicts);
            }
            $before = $formData['before'];
            $after  = $formData['after'];
            if (!empty($before) || !empty($after)) {
                if (count($contests) != 1) {
                    $this->addFlash('danger',
                                    'Only allowed to set before/after restrictions with exactly one selected contest.');
                    return $this->render('jury/rejudging_form.html.twig', [
                        'form' => $form->createView(),
                    ]);
                }
                /** @var Contest $contest */
                $contest = $contests[0];
                if (!empty($before)) {
                    $beforeTime = $contest->getAbsoluteTime($before);
                    $queryBuilder
                        ->andWhere('s.submittime <= :before')
                        ->setParameter(':before', $beforeTime);
                }
                if (!empty($after)) {
                    $afterTime = $contest->getAbsoluteTime($after);
                    $queryBuilder
                        ->andWhere('s.submittime >= :after')
                        ->setParameter(':after', $afterTime);
                }
            }

            /** @var array[] $judgings */
            $judgings = $queryBuilder
                ->getQuery()
                ->getResult();
            if (empty($judgings)) {
                $this->addFlash('danger', 'No judgings matched.');
                return $this->render('jury/rejudging_form.html.twig', [
                    'form' => $form->createView(),
                ]);
            }
            $skipped = [];
            $res = $this->rejudgingService->createRejudging($reason, $judgings, false, 1, null, $skipped);
            $this->generateFlashMessagesForSkippedJudgings($skipped);

            if ($res === null) {
                return $this->redirectToLocalReferrer($this->router, $request,
                                                      $this->generateUrl('jury_index'));
            }
            return $this->redirectToRoute('jury_rejudging', ['rejudgingId' => $res->getRejudgingid()]);
        }
        return $this->render('jury/rejudging_form.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/create", methods={"POST"}, name="jury_create_rejudge")
     */
    public function createAction(Request $request): RedirectResponse
    {
        $table      = $request->request->get('table');
        $id         = $request->request->get('id');
        $reason     = $request->request->get('reason') ?: sprintf('%s: %s', $table, $id);
        $includeAll = (bool)$request->request->get('include_all');
        $autoApply  = (bool)$request->request->get('auto_apply');
        $repeat     = (int)$request->request->get('repeat');

        if (empty($table) || empty($id)) {
            throw new BadRequestHttpException('No table or id passed for selection in rejudging');
        }

        if ($includeAll && !$this->dj->checkrole('admin')) {
            throw new BadRequestHttpException('Rejudging pending/correct submissions requires admin rights');
        }

        // Special case 'submission' for admin overrides
        if ($this->dj->checkrole('admin') && ($table == 'submission')) {
            $includeAll = true;
        } elseif ($table === 'rejudging') {
            $rejudging = $this->em->getRepository(Rejudging::class)->find($id);
            if ($rejudging === null) {
                throw new NotFoundHttpException(sprintf('Rejudging with ID %s not found', $id));
            }
            $includeAll = true;
            $autoApply  = false;
            $reason     = $rejudging->getReason();
        }

        /* These are the tables that we can deal with. */
        $tablemap = [
            'contest' => 's.contest',
            'judgehost' => 'j.judgehost',
            'language' => 's.language',
            'problem' => 's.problem',
            'submission' => 's.submitid',
            'team' => 's.team',
            'rejudging' => 'j2.rejudging',
        ];

        if (!isset($tablemap[$table])) {
            throw new BadRequestHttpException(sprintf('unknown table %s in rejudging', $table));
        }

        // Only rejudge submissions in active contests.
        $contests = $this->dj->getCurrentContests();

        $queryBuilder = $this->em->createQueryBuilder()
            ->from(Judging::class, 'j')
            ->leftJoin('j.submission', 's')
            ->leftJoin('s.rejudging', 'r')
            ->leftJoin('s.team', 't')
            ->select('j', 's', 'r', 't')
            ->andWhere('j.contest IN (:contests)')
            ->andWhere('j.valid = 1')
            ->andWhere(sprintf('%s = :id', $tablemap[$table]))
            ->setParameter(':contests', $contests)
            ->setParameter(':id', $id);

        if ($table === 'rejudging') {
            $queryBuilder->join('s.judgings', 'j2');
        }

        if ($includeAll && !$autoApply) {
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
            ->getResult();

        if (empty($judgings)) {
            $this->addFlash('danger', 'No judgings matched.');
            return $this->redirectToLocalReferrer($this->router, $request,
                                                  $this->generateUrl('jury_index'));
        }

        $skipped = [];
        $res = $this->rejudgingService->createRejudging($reason, $judgings, $autoApply, $repeat, null, $skipped);
        $this->generateFlashMessagesForSkippedJudgings($skipped);

        if ($res === null) {
            return $this->redirectToLocalReferrer($this->router, $request,
                                                  $this->generateUrl('jury_index'));
        }

        if ($res instanceof Rejudging) {
            return $this->redirectToRoute('jury_rejudging', ['rejudgingId' => $res->getRejudgingid()]);
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
                default:
                    // This case never happens, since we already check above. Add it here to silence linter warnings.
                    throw new BadRequestHttpException(sprintf('unknown table %s in rejudging', $table));
            }
        }
    }

    private function generateFlashMessagesForSkippedJudgings(array $skipped): void
    {
        /** @var Judging $judging */
        foreach ($skipped as $judging) {
            $submission = $judging->getSubmission();
            $submitid = $submission->getSubmitid();
            $rejudgingid = $submission->getRejudging()->getRejudgingid();
            $msg = sprintf(
                'Skipping submission <a href="%s">s%d</a> since it is ' .
                'already part of rejudging <a href="%s">r%d</a>.',
                $this->generateUrl('jury_submission', ['submitId' => $submitid]),
                $submitid,
                $this->generateUrl('jury_rejudging', ['rejudgingId' => $rejudgingid]),
                $rejudgingid
            );
            $this->addFlash('danger', $msg);
        }
    }

    private function getStats(Rejudging $rejudging): array
    {
        $judgings = $this->em->createQueryBuilder()
            ->from(Judging::class, 'j')
            ->leftJoin('j.runs', 'jr')
            ->leftJoin('j.rejudging', 'r')
            ->leftJoin('j.submission', 's')
            ->leftJoin('j.judgehost', 'jh')
            ->select('j.judgingid', 's.submitid', 'jh.hostname', 'j.result',
                'AVG(jr.runtime) AS runtime_avg', 'COUNT(jr.runtime) AS ntestcases',
                '(j.endtime - j.starttime) AS duration'
            )
            ->andWhere('r.repeatedRejudging = :repeat_rejudgingid')
            ->setParameter(':repeat_rejudgingid', $rejudging->getRepeatedRejudging())
            ->groupBy('j.judgingid')
            ->orderBy('j.judgingid')
            ->getQuery()
            ->getResult();

        $submissions = [];
        $judgehosts = [];
        foreach ($judgings as $judging) {
            $submissions[$judging['submitid']][] = $judging;
            $judgehosts[$judging['hostname']][] = $judging;
        }
        ksort($submissions);

        $judging_runs_differ = [];
        $runtime_spread = [];
        $submissions_to_result = [];
        foreach ($submissions as $submitid => $curJudgings) {
            // Check for different results:
            $results = [];
            $runresults = [];
            foreach ($curJudgings as $judging) {
                if (!in_array($judging['result'], $results) && $judging['result'] != NULL) {
                    $results[] = $judging['result'];
                }
                $judging_runs = $this->em->createQueryBuilder()
                    ->from(JudgingRun::class, 'jr')
                    ->select('t.ranknumber', 'jr.runresult')
                    ->leftJoin('jr.testcase', 't')
                    ->andWhere('jr.judging = :judgingid')
                    ->setParameter(':judgingid', $judging['judgingid'])
                    ->orderBy('t.ranknumber')
                    ->getQuery()
                    ->getArrayResult();
                if (!in_array($judging_runs, $runresults)) {
                    $runresults[] = $judging_runs;
                }
                $judging_results[$judging['judgingid']] = $judging['result'];
            }
            // If there are diffs on the judging level, then they will show up in the matrix anyway.
            if (count($results) == 1) {
                $submissions_to_result[$submitid] = $results[0];
                if (count($runresults)!=1) {
                    // Only report differences in judging runs if the final
                    // results were the same.
                    $judging_runs_differ[] = $submitid;
                }
            }

            // Check for variations in runtimes across judgings
            $runtimes = $this->em->createQueryBuilder()
                ->from(JudgingRun::class, 'jr')
                ->select('t.ranknumber', 'MAX(jr.runtime) - MIN(jr.runtime) AS spread')
                ->leftJoin('jr.judging', 'j')
                ->leftJoin('jr.testcase', 't')
                ->andWhere('j.submission = :submitid')
                ->setParameter(':submitid', $submitid)
                ->groupBy('jr.testcase')
                ->getQuery()
                ->getArrayResult();
            $current_spread = [
                'spread' => -1,
                'rank' => -1
            ];
            foreach ($runtimes as $runtime) {
                $spread = (float) $runtime['spread'];
                if ($spread > $current_spread['spread']) {
                    $current_spread['spread'] = $spread;
                    $current_spread['rank'] = $runtime['ranknumber'];
                    $current_spread['submitid'] = $submitid;
                }
            }
            if (isset($current_spread['submitid'])) {
                $runtime_spread[$submitid] = $current_spread;
            }
        }
        sort($judging_runs_differ);
        usort($runtime_spread,
            function ($a, $b) {
                return $b['spread'] <=> $a['spread'];
            }
        );

        $max_list_len = 10;
        $runtime_spread_list = [];
        $i = 0;
        foreach ($runtime_spread as $value) {
            if ($i >= $max_list_len) {
                break;
            }
            $i++;
            $submitid = $value['submitid'];
            $runtime_spread_list[] = [
                'submitid' => $submitid,
                'rank' => $value['rank'],
                'spread' => $value['spread'],
                'count' => count($submissions[$submitid]),
                'verdict' => (
                    !array_key_exists($submitid, $submissions_to_result)
                        ? '<span title="' . join(', ', $results) . '">*multiple*</span>'
                        : $submissions_to_result[$submitid]
                )
            ];
        }

        $judgehost_stats = [];
        foreach ($judgehosts as $judgehost => $judgings) {
            $totaltime = 0.0; // Actual time begin--end of judging
            $totalrun  = 0.0; // Time spent judging runs
            $sumsquare = 0.0;
            $njudged = 0;
            foreach ($judgings as $judging) {
                $runtime = $judging['runtime_avg']*$judging['ntestcases'];
                $totaltime += $judging['duration'];
                $totalrun  += $runtime;
                $sumsquare += $runtime*$runtime;
                $njudged++;
            }
            $avgtime = $totaltime / $njudged;
            $avgrun  = $totalrun  / $njudged;
            // FIXME: variance over all judgings from different problems
            // doesn't make sense.
            $variance = $sumsquare / $njudged - $avgrun*$avgrun;
            $judgehost_stats[$judgehost] = [
                'judgehost' => $judgehost,
                'njudged' => $njudged,
                'avgrun' => $avgrun,
                'stddev' => sqrt($variance),
                'avgduration' => $avgtime
            ];
        }

        return [
            'judging_runs_differ' => array_slice($judging_runs_differ, 0, $max_list_len),
            'judging_runs_differ_overflow' => count($judging_runs_differ) - $max_list_len,
            'runtime_spread' => $runtime_spread_list,
            'judgehost_stats' => $judgehost_stats
        ];
    }
}
