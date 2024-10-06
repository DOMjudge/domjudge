<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Controller\BaseController;
use App\DataTransferObject\SubmissionRestriction;
use App\Entity\Contest;
use App\Entity\Judgehost;
use App\Entity\JudgeTask;
use App\Entity\Judging;
use App\Entity\JudgingRun;
use App\Entity\Language;
use App\Entity\Problem;
use App\Entity\Rejudging;
use App\Entity\Submission;
use App\Entity\Team;
use App\Entity\User;
use App\Form\Type\RejudgingType;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Service\RejudgingService;
use App\Service\SubmissionService;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query\Expr\Join;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_JURY')]
#[Route(path: '/jury/rejudgings')]
class RejudgingController extends BaseController
{
    public function __construct(
        EntityManagerInterface $em,
        protected readonly EventLogService $eventLogService,
        DOMJudgeService $dj,
        protected readonly ConfigurationService $config,
        protected readonly RejudgingService $rejudgingService,
        protected readonly RouterInterface $router,
        protected readonly RequestStack $requestStack,
        KernelInterface $kernel,
    ) {
        parent::__construct($em, $eventLogService, $dj, $kernel);
    }

    /**
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    #[Route(path: '', name: 'jury_rejudgings')]
    public function indexAction(): Response
    {
        $curContest = $this->dj->getCurrentContest();
        $queryBuilder = $this->em->createQueryBuilder()
            ->select('r')
            ->from(Rejudging::class, 'r');
        if ($curContest !== null) {
            $queryBuilder = $queryBuilder->leftJoin(Judging::class, 'j', Join::WITH, 'j.rejudging = r')
                ->andWhere('j.contest = :contest')
                ->setParameter('contest', $curContest->getCid())
                ->distinct();
        }
        /** @var Rejudging[] $rejudgings */
        $rejudgings = $queryBuilder->orderBy('r.rejudgingid', 'DESC')
            ->getQuery()->getResult();

        $table_fields = [
            'rejudgingid' => ['title' => 'ID', 'sort' => true],
            'reason' => ['title' => 'reason', 'sort' => true],
            'repetitions' => ['title' => 'repetitions', 'sort' => true],
            'startuser' => ['title' => 'startuser', 'sort' => true],
            'finishuser' => ['title' => 'finishuser', 'sort' => true],
            'starttime' => ['title' => 'starttime', 'sort' => true],
            'endtime' => ['title' => 'finishtime', 'sort' => true],
            'rejudging_status' => [
                'title' => 'status',
                'sort' => true,
                'default_sort' => true,
                'default_sort_order' => 'asc'
            ],
        ];

        $timeFormat       = (string)$this->config->get('time_format');
        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $rejudgings_table = [];
        foreach ($rejudgings as $rejudging) {
            $rejudgingdata = [];
            // Get whatever fields we can from the problem object itself.
            foreach ($table_fields as $k => $v) {
                if ($propertyAccessor->isReadable($rejudging, $k)) {
                    $rejudgingdata[$k] = ['value' => $propertyAccessor->getValue($rejudging, $k)];
                }
            }

            $rejudgingdata['repetitions']['value'] = $rejudging->getRepeat() ?? '-';

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
                $sort_order = 2;
            } elseif ($todo > 0) {
                $perc   = (int)(100 * ((double)$done / (double)($done + $todo)));
                $status = sprintf("%d%% done", $perc);
                $sort_order = 0;
            } else {
                $status = 'ready';
                $sort_order = 1;
            }

            $rejudgingdata['starttime']['value']  = Utils::printtime($rejudging->getStarttime(), $timeFormat);
            $rejudgingdata['endtime']['value']    = Utils::printtime($rejudging->getEndtime(), $timeFormat);
            $rejudgingdata['rejudging_status']['value']     = $status;
            $rejudgingdata['rejudging_status']['sortvalue'] = $sort_order;

            if ($rejudging->getEndtime() !== null) {
                $class = 'disabled';
            } else {
                $class = $todo > 0 ? '' : 'unseen';
            }

            // Save this to our list of rows.
            $rejudgings_table[] = [
                'data' => $rejudgingdata,
                'actions' => [],
                'link' => $this->generateUrl('jury_rejudging', ['rejudgingId' => $rejudging->getRejudgingid()]),
                'cssclass' => $class,
                'sort' => $sort_order,
                'rejudgingid' => $rejudging->getRejudgingid(),
                'repeat_rejudgingid' => $rejudging->getRepeatedRejudging()?->getRejudgingid(),
            ];
        }

        // Filter the table to include only the rejudgings without repetition and for rejudgings with repetition the one
        // with the maximal ID since that is the instance that can be cancelled / applied.
        $maxid_per_repeatid = [];
        foreach ($rejudgings_table as $row) {
            if ($row['repeat_rejudgingid'] === null) {
                continue;
            }
            $repeat_rejudgingid = $row['repeat_rejudgingid'];
            if (isset($maxid_per_repeatid[$repeat_rejudgingid])) {
                $maxid_per_repeatid[$repeat_rejudgingid] = max($maxid_per_repeatid[$repeat_rejudgingid], $row['rejudgingid']);
            } else {
                $maxid_per_repeatid[$repeat_rejudgingid] = $row['rejudgingid'];
            }
        }
        $filtered_table = [];
        foreach ($rejudgings_table as $row) {
            if ($row['repeat_rejudgingid'] === null || $maxid_per_repeatid[$row['repeat_rejudgingid']] === $row['rejudgingid']) {
                $filtered_table[] = $row;
            }
        }

        $twigData = [
            'rejudgings' => $filtered_table,
            'table_fields' => $table_fields,
            'refresh' => [
                'after' => 15,
                'url' => $this->generateUrl('jury_rejudgings'),
            ],
        ];

        return $this->render('jury/rejudgings.html.twig', $twigData);
    }

    /**
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    #[Route(path: '/{rejudgingId<\d+>}', name: 'jury_rejudging')]
    public function viewAction(
        Request $request,
        SubmissionService $submissionService,
        int $rejudgingId,
        #[MapQueryParameter(name: 'view')]
        ?string $viewFromRequest = null,
        #[MapQueryParameter]
        string $oldverdict = 'all',
        #[MapQueryParameter]
        string $newverdict = 'all',
        #[MapQueryParameter(name: 'show_statistics')]
        ?bool $showStatistics = null,
    ): Response {
        // Close the session, as this might take a while and we don't need the session below.
        $this->requestStack->getSession()->save();

        /** @var Rejudging|null $rejudging */
        $rejudging = $this->em->createQueryBuilder()
            ->from(Rejudging::class, 'r')
            ->leftJoin('r.start_user', 's')
            ->leftJoin('r.finish_user', 'f')
            ->select('r', 's', 'f')
            ->andWhere('r.rejudgingid = :rejudgingid')
            ->setParameter('rejudgingid', $rejudgingId)
            ->getQuery()
            ->getOneOrNullResult();

        $disabledProblems = [];
        $disabledLangs = [];
        foreach ($rejudging->getJudgings() as $judging) {
            $submission = $judging->getSubmission();
            $problem = $submission->getContestProblem();
            $language = $submission->getLanguage();

            if (!$problem->getAllowJudge()) {
                $disabledProblems[$submission->getProblemId()] = $submission->getProblem()->getName();
            }
            if (!$language->getAllowJudge()) {
                $disabledLangs[$submission->getLanguage()->getLangid()] = $submission->getLanguage()->getName();
            }
        }

        if (!$rejudging) {
            throw new NotFoundHttpException(sprintf('Rejudging with ID %s not found', $rejudgingId));
        }
        $todo = $this->rejudgingService->calculateTodo($rejudging)['todo'];

        $verdicts = $this->dj->getVerdicts();
        $verdicts[''] = 'JE'; /* happens for aborted judgings */
        $verdicts['aborted'] = 'JE'; /* happens for aborted judgings */

        $used         = [];
        $verdictTable = [];
        // Pre-fill $verdictTable to get a consistent ordering.
        foreach ($verdicts as $verdict => $abbrev) {
            foreach ($verdicts as $verdict2 => $abbrev2) {
                $verdictTable[$verdict][$verdict2] = [];
            }
        }

        /** @var Judging[] $originalVerdicts */
        $originalVerdicts = [];
        /** @var Judging[] $newVerdicts */
        $newVerdicts = [];

        $this->em->wrapInTransaction(function () use ($rejudging, &$originalVerdicts, &$newVerdicts) {
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
                ->setParameter('rejudging', $rejudging)
                ->getQuery()
                ->getResult();

            $newVerdicts = $this->em->createQueryBuilder()
                ->from(Judging::class, 'j')
                ->join('j.submission', 's')
                ->select('j, s')
                ->andWhere('j.rejudging = :rejudging')
                ->andWhere('j.endtime IS NOT NULL')
                ->setParameter('rejudging', $rejudging)
                ->getQuery()
                ->getResult();

            $getSubmissionId = fn(Judging $judging) => $judging->getSubmission()->getSubmitid();
            $originalVerdicts = Utils::reindex($originalVerdicts, $getSubmissionId);
            $newVerdicts = Utils::reindex($newVerdicts, $getSubmissionId);
        });

        // Helper function to add verdicts.
        $addVerdict = function ($unknownVerdict) use ($verdicts, &$verdictTable) {
            // Add column to existing rows.
            foreach ($verdicts as $verdict => $abbreviation) {
                $verdictTable[$verdict][$unknownVerdict] = [];
            }
            // Add verdict to known verdicts.
            $verdicts[$unknownVerdict] = $unknownVerdict;
            // Add row.
            $verdictTable[$unknownVerdict] = [];
            foreach ($verdicts as $verdict => $abbreviation) {
                $verdictTable[$unknownVerdict][$verdict] = [];
            }
        };

        // Build up the verdict matrix.
        foreach ($newVerdicts as $submitid => $newVerdict) {
            $originalVerdict = $originalVerdicts[$submitid];

            // Add verdicts to data structures if they are unknown up to now.
            foreach ([$newVerdict, $originalVerdict] as $verdict) {
                if (!array_key_exists($verdict->getResult(), $verdicts)) {
                    $addVerdict($verdict->getResult());
                }
            }

            // Mark them as used, so we can filter out unused cols/rows later.
            $used[$originalVerdict->getResult()] = true;
            $used[$newVerdict->getResult()]      = true;

            // Append submitid to list of orig->new verdicts.
            $verdictTable[$originalVerdict->getResult()][$newVerdict->getResult()][] = $submitid;
        }

        $viewTypes = [0 => 'newest', 1 => 'unverified', 2 => 'unjudged', 3 => 'diff', 4 => 'all'];
        $defaultView = 'diff';
        $onlyAHandfulOfSubmissions = $rejudging->getSubmissions()->count() <= 5;
        if ($onlyAHandfulOfSubmissions) {
            // Only a handful of submissions, display all of them right away.
            $defaultView = 'all';
        }
        $view = array_search($defaultView, $viewTypes);
        if ($viewFromRequest) {
            $index = array_search($viewFromRequest, $viewTypes);
            if ($index !== false) {
                $view = $index;
            }
        }

        $restrictions = new SubmissionRestriction(rejudgingId: $rejudgingId);
        if ($viewTypes[$view] == 'unverified') {
            $restrictions->verified = false;
        }
        if ($viewTypes[$view] == 'unjudged') {
            $restrictions->judged = false;
        }
        if ($viewTypes[$view] == 'diff') {
            $restrictions->rejudgingDifference = true;
        }
        if ($oldverdict !== 'all') {
            $restrictions->oldResult = $oldverdict;
        }
        if ($newverdict !== 'all') {
            $restrictions->result = $newverdict;
        }

        /** @var Submission[] $submissions */
        [$submissions, $submissionCounts] = $submissionService->getSubmissionList(
            $this->dj->getCurrentContests(honorCookie: true),
            $restrictions
        );

        $repetitions = $this->em->createQueryBuilder()
            ->from(Rejudging::class, 'r')
            ->select('r.rejudgingid')
            ->andWhere('r.repeatedRejudging = :repeat_rejudgingid')
            ->andWhere('r.rejudgingid != :rejudgingid')
            ->setParameter('repeat_rejudgingid', $rejudging->getRepeatedRejudging())
            ->setParameter('rejudgingid', $rejudging->getRejudgingid())
            ->orderBy('r.rejudgingid')
            ->getQuery()
            ->getScalarResult();

        // Only load the statistics if desired. The query is quite long and can result in much data, so only have it run
        // when needed or when we don't have a lot of data to load.
        $showStatistics = $showStatistics ?? $onlyAHandfulOfSubmissions;
        if ($showStatistics && count($repetitions) > 0) {
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
            'showContest' => count($this->dj->getCurrentContests(honorCookie: true)) > 1,
            'oldverdict' => $oldverdict,
            'newverdict' => $newverdict,
            'repetitions' => array_column($repetitions, 'rejudgingid'),
            'showStatistics' => $showStatistics,
            'showExternalResult' => $this->dj->shadowMode(),
            'stats' => $stats,
            'refresh' => [
                'after' => 15,
                'url' => $request->getRequestUri(),
                'ajax' => true,
            ],
            'disabledProbs' => $disabledProblems,
            'disabledLangs' => $disabledLangs,
        ];
        if ($request->isXmlHttpRequest()) {
            $data['ajax'] = true;
            return $this->render('jury/partials/rejudging_submissions.html.twig', $data);
        } else {
            return $this->render('jury/rejudging.html.twig', $data);
        }
    }

    /**
     * @throws NonUniqueResultException
     */
    #[Route(path: '/{rejudgingId<\d+>}/{action<cancel|apply>}', name: 'jury_rejudging_finish')]
    public function finishAction(
        Request $request,
        RejudgingService $rejudgingService,
        ?Profiler $profiler,
        int $rejudgingId,
        string $action
    ): Response {
        // Note: we use a XMLHttpRequest here as Symfony does not support streaming Twig output

        // Disable the profiler toolbar to avoid OOMs.
        $profiler?->disable();

        /** @var Rejudging $rejudging */
        $rejudging = $this->em->createQueryBuilder()
            ->from(Rejudging::class, 'r')
            ->select('r')
            ->andWhere('r.rejudgingid = :rejudgingid')
            ->setParameter('rejudgingid', $rejudgingId)
            ->getQuery()
            ->getOneOrNullResult();

        if ($request->isXmlHttpRequest()) {
            $progressReporter = function (int $progress, string $log, ?string $message = null) {
                echo $this->dj->jsonEncode(['progress' => $progress, 'log' => htmlspecialchars($log), 'message' => htmlspecialchars($message ?? '')]);
                ob_flush();
                flush();
            };
            return $this->streamResponse($this->requestStack, function () use ($progressReporter, $rejudging, $rejudgingService, $action) {
                $timeStart = microtime(true);
                if ($rejudgingService->finishRejudging($rejudging, $action, $progressReporter)) {
                    $timeEnd      = microtime(true);
                    $timeDiff     = sprintf('%.2f', $timeEnd - $timeStart);
                    $message      = sprintf(
                        'Rejudging r%d %s in %s seconds.',
                        $rejudging->getRejudgingid(),
                        $action == RejudgingService::ACTION_APPLY ? 'applied' : 'canceled', $timeDiff
                    );
                    $progressReporter(100, '', $message);
                }
            });
        }

        return $this->render('jury/rejudging_finish.html.twig', [
            'action' => $action,
            'rejudging' => $rejudging,
        ]);
    }

    #[Route(path: '/add', name: 'jury_rejudging_add')]
    public function addAction(Request $request, FormFactoryInterface $formFactory): Response
    {
        $isContestUpdateAjax   = $request->isXmlHttpRequest() && $request->request->getBoolean('refresh_form');
        $isCreateRejudgingAjax = $request->isMethod('POST') && $request->isXmlHttpRequest() && !$isContestUpdateAjax;
        $isNormalPost          = $request->isMethod('POST') && !$request->isXmlHttpRequest();
        $formBuilder           = $formFactory->createBuilder(RejudgingType::class);
        $formData              = [];
        if (!$request->isMethod('POST')) {
            $currentContest = $this->dj->getCurrentContest();
            $formData['contests'] = is_null($currentContest) ? [] : [$currentContest];
        }
        $verdicts             = $formBuilder->get('verdicts')->getOption('choices');
        $incorrectVerdicts    = array_filter($verdicts, fn($k) => $k != 'correct');
        $formData['verdicts'] = $incorrectVerdicts;

        $form = $formBuilder->setData($formData)->getForm();

        $form->handleRequest($request);

        if ($isNormalPost && $form->isSubmitted() && $form->isValid()) {
            $formData = $form->getData();
            $data = [
                'reason'     => $formData['reason'],
                'priority'   => JudgeTask::parsePriority($formData['priority']),
                'repeat'     => $formData['repeat'],
                'contests'   => array_map(
                    fn(Contest $contest) => $contest->getCid(),
                    $formData['contests'] ? $formData['contests']->toArray() : []
                ),
                'problems'   => array_map(
                    fn(Problem $problem) => $problem->getProbid(),
                    $formData['problems'] ? $formData['problems']->toArray() : []
                ),
                'languages'  => array_map(
                    fn(Language $language) => $language->getLangid(),
                    $formData['languages'] ? $formData['languages']->toArray() : []
                ),
                'teams'      => array_map(
                    fn(Team $team) => $team->getTeamid(),
                    $formData['teams'] ? $formData['teams']->toArray() : []
                ),
                'users'      => array_map(
                    fn(User $user) => $user->getUserid(),
                    $formData['users'] ? $formData['users']->toArray() : []
                ),
                'judgehosts' => array_map(
                    fn(Judgehost $judgehost) => $judgehost->getJudgehostid(),
                    $formData['judgehosts'] ? $formData['judgehosts']->toArray() : []
                ),
                'verdicts'   => array_values($formData['verdicts']),
                'before'     => $formData['before'],
                'after'      => $formData['after'],
                'referer'    => $request->headers->get('referer'),
                'overshoot'  => $formData['overshoot'],
            ];
            return $this->render('jury/rejudging_add.html.twig', [
                'data'    => http_build_query($data),
                'url'     => $this->generateUrl('jury_rejudging_add'),
            ]);
        }
        if ($isCreateRejudgingAjax) {
            $progressReporter = function (int $progress, string $log, ?string $redirect = null) {
                echo $this->dj->jsonEncode(['progress' => $progress, 'log' => htmlspecialchars($log), 'redirect' => $redirect]);
                ob_flush();
                flush();
            };
            return $this->streamResponse($this->requestStack, function () use ($request, $progressReporter) {
                $reason = $request->request->get('reason');
                $data   = $request->request->all();

                $queryBuilder = $this->em->createQueryBuilder()
                    ->from(Judging::class, 'j')
                    ->leftJoin('j.submission', 's')
                    ->leftJoin('s.rejudging', 'r')
                    ->leftJoin('s.team', 't')
                    ->select('j', 's', 'r', 't')
                    ->andWhere('j.valid = 1');

                /** @var int[] $contests */
                $contests = $data['contests'] ?? [];
                if (count($contests)) {
                    $queryBuilder
                        ->andWhere('j.contest IN (:contests)')
                        ->setParameter('contests', $contests);
                }
                /** @var int[] $problems */
                $problems = $data['problems'] ?? [];
                if (count($problems)) {
                    $queryBuilder
                        ->andWhere('s.problem IN (:problems)')
                        ->setParameter('problems', $problems);
                }
                /** @var int[] $languages */
                $languages = $data['languages'] ?? [];
                if (count($languages)) {
                    $queryBuilder
                        ->andWhere('s.language IN (:languages)')
                        ->setParameter('languages', $languages);
                }
                /** @var int[] $teams */
                $teams = $data['teams'] ?? [];
                if (count($teams)) {
                    $queryBuilder
                        ->andWhere('s.team IN (:teams)')
                        ->setParameter('teams', $teams);
                }
                /** @var int[] $users */
                $users = $data['users'] ?? [];
                if (count($users)) {
                    $queryBuilder
                        ->andWhere('s.user IN (:users)')
                        ->setParameter('users', $users);
                }
                /** @var int[] $judgehosts */
                $judgehosts = $data['judgehosts'] ?? [];
                if (count($judgehosts)) {
                    $queryBuilder
                        ->innerJoin('j.runs', 'jr')
                        ->innerJoin('jr.judgetask', 'jt')
                        ->andWhere('jt.judgehost IN (:judgehosts)')
                        ->setParameter('judgehosts', $judgehosts)
                        ->distinct();
                }
                /** @var string[] $verdicts */
                $verdicts = $data['verdicts'] ?? [];
                if (count($verdicts)) {
                    $queryBuilder
                        ->andWhere('j.result IN (:verdicts)')
                        ->setParameter('verdicts', $verdicts);
                }
                $before = $data['before'] ?? null;
                $after  = $data['after'] ?? null;
                if (!empty($before) || !empty($after)) {
                    if (count($contests) != 1) {
                        $this->addFlash('danger',
                            'Only allowed to set before/after restrictions with exactly one selected contest.');
                        $progressReporter(100, '', $this->generateUrl('jury_rejudging_add'));
                        return;
                    }
                    /** @var Contest $contest */
                    $contest = $this->em->getRepository(Contest::class)->find($contests[0]);
                    if (!empty($before)) {
                        $beforeTime = $contest->getAbsoluteTime($before);
                        $queryBuilder
                            ->andWhere('s.submittime <= :before')
                            ->setParameter('before', $beforeTime);
                    }
                    if (!empty($after)) {
                        $afterTime = $contest->getAbsoluteTime($after);
                        $queryBuilder
                            ->andWhere('s.submittime >= :after')
                            ->setParameter('after', $afterTime);
                    }
                }

                /** @var Judging[] $judgings */
                $judgings = $queryBuilder
                    ->getQuery()
                    ->getResult();
                if (empty($judgings)) {
                    $this->addFlash('danger', 'No judgings matched.');
                    $progressReporter(100, '', $this->generateUrl('jury_rejudging_add'));
                    return;
                }

                $skipped = [];
                $res     = $this->rejudgingService->createRejudging(
                    $reason, (int)$data['priority'], $judgings, false, (int)($data['repeat'] ?? 1), (int) ($data['overshoot'] ?? 0), null, $skipped, $progressReporter);
                $this->generateFlashMessagesForSkippedJudgings($skipped);

                if ($res === null) {
                    $prefix = sprintf('%s%s', $request->getSchemeAndHttpHost(), $request->getBasePath());
                    if ($this->isLocalRefererUrl($this->router, $data['referer'] ?? '', $prefix)) {
                        $redirect = $data['referer'];
                    } else {
                        $redirect = $this->generateUrl('jury_index');
                    }
                } else {
                    $redirect = $this->generateUrl('jury_rejudging', ['rejudgingId' => $res->getRejudgingid()]);
                }
                $progressReporter(100, '', $redirect);
            });
        }
        return $this->render('jury/rejudging_form.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route(path: '/create', methods: ['POST'], name: 'jury_create_rejudge')]
    public function createAction(Request $request): Response
    {
        $table      = $request->request->get('table');
        $id         = $request->request->get('id');
        $reason     = $request->request->get('reason') ?: sprintf('%s: %s', $table, $id);
        $includeAll = (bool)$request->request->get('include_all');
        $autoApply  = (bool)$request->request->get('auto_apply');
        $repeat     = (int)$request->request->get('repeat');
        $priority   = $request->request->get('priority') ?: 'default';
        $overshoot  = (int)$request->request->get('overshoot') ?: 0;

        if (empty($table) || empty($id)) {
            throw new BadRequestHttpException('No table or id passed for selection in rejudging');
        }

        if ($includeAll && !$this->dj->checkrole('admin')) {
            throw new BadRequestHttpException('Rejudging pending/correct submissions requires admin rights');
        }

        // Special case 'submission' for admin overrides.
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

        // These are the tables that we can deal with.
        $tablemap = [
            'contest' => 's.contest',
            'judgehost' => 'jt.judgehost',
            'language' => 's.language',
            'problem' => 's.problem',
            'submission' => 's.submitid',
            'team' => 's.team',
            'user' => 's.user',
            'rejudging' => 'j2.rejudging',
        ];

        if (!isset($tablemap[$table])) {
            throw new BadRequestHttpException(sprintf('unknown table %s in rejudging', $table));
        }

        if (!$request->isXmlHttpRequest()) {
            $data            = $request->request->all();
            $data['referer'] = $request->headers->get('referer');
            return $this->render('jury/rejudging_add.html.twig', [
                'data' => http_build_query($data),
                'url'  => $this->generateUrl('jury_create_rejudge'),
            ]);
        }

        $progressReporter = function (int $progress, string $log, ?string $redirect = null) {
            echo $this->dj->jsonEncode(['progress' => $progress, 'log' => htmlspecialchars($log), 'redirect' => $redirect]);
            ob_flush();
            flush();
        };

        return $this->streamResponse($this->requestStack, function () use ($priority, $progressReporter, $repeat, $reason, $overshoot, $request, $autoApply, $includeAll, $id, $table, $tablemap) {
            // Only rejudge submissions in active contests.
            $contests = $this->dj->getCurrentContests();

            $queryBuilder = $this->em->createQueryBuilder()
                ->from(Judging::class, 'j')
                ->leftJoin('j.submission', 's')
                ->leftJoin('s.rejudging', 'r')
                ->leftJoin('s.team', 't')
                ->leftJoin('j.runs', 'jr')
                ->leftJoin('jr.judgetask', 'jt')
                ->select('j', 's', 'r', 't')
                ->distinct()
                ->andWhere('j.contest IN (:contests)')
                ->andWhere('j.valid = 1')
                ->andWhere(sprintf('%s = :id', $tablemap[$table]))
                ->setParameter('contests', $contests)
                ->setParameter('id', $id);

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
                    ->setParameter('correct', 'correct');
            }

            /** @var Judging[] $judgings */
            $judgings = $queryBuilder
                ->getQuery()
                ->getResult();

            if (empty($judgings)) {
                $this->addFlash('danger', 'No judgings matched.');
                $prefix = sprintf('%s%s', $request->getSchemeAndHttpHost(), $request->getBasePath());
                if ($this->isLocalRefererUrl($this->router, $request->request->get('referer', ''), $prefix)) {
                    $redirect = $request->request->get('referer');
                } else {
                    $redirect = $this->generateUrl('jury_index');
                }
                $progressReporter(100, '', $redirect);
            }

            $skipped = [];
            $res     = $this->rejudgingService->createRejudging($reason, JudgeTask::parsePriority($priority), $judgings, $autoApply, $repeat, $overshoot, null, $skipped, $progressReporter);

            if ($res === null) {
                $prefix = sprintf('%s%s', $request->getSchemeAndHttpHost(), $request->getBasePath());
                if ($this->isLocalRefererUrl($this->router, $request->request->get('referer', ''), $prefix)) {
                    $redirect = $request->request->get('referer');
                } else {
                    $redirect = $this->generateUrl('jury_index');
                }
            } elseif ($res instanceof Rejudging) {
                $redirect = $this->generateUrl('jury_rejudging', ['rejudgingId' => $res->getRejudgingid()]);
            } else {
                $redirect = match ($table) {
                    'contest' => $this->generateUrl('jury_contest', ['contestId' => $id]),
                    'judgehost' => $this->generateUrl('jury_judgehost', ['judgehostid' => $id]),
                    'language' => $this->generateUrl('jury_language', ['langId' => $id]),
                    'problem' => $this->generateUrl('jury_problem', ['probId' => $id]),
                    'submission' => $this->generateUrl('jury_submission', ['submitId' => $id]),
                    'team' => $this->generateUrl('jury_team', ['teamId' => $id]),
                    // This case never happens, since we already check above.
                    // Add it here to silence linter warnings.
                    default => throw new BadRequestHttpException(sprintf('unknown table %s in rejudging', $table)),
                };
            }

            $progressReporter(100, '', $redirect);
        });
    }

    /**
     * @param Judging[] $skipped
     */
    private function generateFlashMessagesForSkippedJudgings(array $skipped): void
    {
        /** @var Judging $judging */
        foreach ($skipped as $judging) {
            $submission = $judging->getSubmission();
            $submitid = $submission->getSubmitid();
            $rejudgingid = $submission->getRejudging()->getRejudgingid();
            $msg = sprintf(
                'Skipping submission s%d since it is ' .
                'already part of rejudging r%d.',
                $submitid,
                $rejudgingid
            );
            $this->addFlash('danger', $msg);
        }
    }

    /**
     * @return array{'judging_runs_differ': int[], 'judging_runs_differ_overflow': int,
     *                'runtime_spread': array<array{'submitid': int, 'rank': int,
     *                                              'spread': float, 'count': int,
     *                                              'verdict': string}>,
     *                'judgehost_stats': array<string, array{'judgehost': string, 'njudged': int,
     *                                                       'avgrun': float, 'stddev': float,
     *                                                       'avgduration': float}>,
     *                'judgings': array<array{'rejudgingid': int, 'judgingid': int, 'submitid': int,
     *                                        'hostname': string, 'result': string, 'runtime_avg': float|null,
     *                                        'ntestcases': int, 'duration': float}>}
     */
    private function getStats(Rejudging $rejudging): array
    {
        $judgings = $this->em->createQueryBuilder()
            ->from(Judging::class, 'j')
            ->leftJoin('j.runs', 'jr')
            ->leftJoin('jr.judgetask', 'jt')
            ->leftJoin('j.rejudging', 'r')
            ->leftJoin('j.submission', 's')
            ->leftJoin('jt.judgehost', 'jh')
            ->select('r.rejudgingid, j.judgingid', 's.submitid', 'jh.hostname', 'j.result',
                'AVG(jr.runtime) AS runtime_avg', 'COUNT(jr.runtime) AS ntestcases',
                '(j.endtime - j.starttime) AS duration'
            )
            ->andWhere('r.repeatedRejudging = :repeat_rejudgingid')
            ->setParameter('repeat_rejudgingid', $rejudging->getRepeatedRejudging())
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
                if (!in_array($judging['result'], $results) && $judging['result'] != null) {
                    $results[] = $judging['result'];
                }
                $judging_runs = $this->em->createQueryBuilder()
                    ->from(JudgingRun::class, 'jr')
                    ->select('t.ranknumber', 'jr.runresult')
                    ->leftJoin('jr.testcase', 't')
                    ->andWhere('jr.judging = :judgingid')
                    ->setParameter('judgingid', $judging['judgingid'])
                    ->orderBy('t.ranknumber')
                    ->getQuery()
                    ->getArrayResult();
                if (!in_array($judging_runs, $runresults)) {
                    $runresults[] = $judging_runs;
                }
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

            // Check for variations in runtimes across judgings.
            $runtimes = $this->em->createQueryBuilder()
                ->from(JudgingRun::class, 'jr')
                ->select('t.ranknumber', 'MAX(jr.runtime) - MIN(jr.runtime) AS spread')
                ->leftJoin('jr.judging', 'j')
                ->leftJoin('jr.testcase', 't')
                ->andWhere('j.submission = :submitid')
                ->setParameter('submitid', $submitid)
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
        usort($runtime_spread, fn($a, $b) => $b['spread'] <=> $a['spread']);

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
                        ? implode(', ', $results ?? [])
                        : $submissions_to_result[$submitid]
                )
            ];
        }

        $judgehost_stats = [];
        foreach ($judgehosts as $judgehost => $host_judgings) {
            $totaltime = 0.0; // Actual time begin--end of judging
            $totalrun  = 0.0; // Time spent judging runs
            $sumsquare = 0.0;
            $njudged = 0;
            foreach ($host_judgings as $judging) {
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
            'judgehost_stats' => $judgehost_stats,
            'judgings' => $judgings
        ];
    }
}
