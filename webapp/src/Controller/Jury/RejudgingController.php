<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Controller\BaseController;
use App\Entity\Contest;
use App\Entity\Judging;
use App\Entity\Problem;
use App\Entity\Rejudging;
use App\Entity\Submission;
use App\Entity\Team;
use App\Form\Type\RejudgingType;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\RejudgingService;
use App\Service\ScoreboardService;
use App\Service\SubmissionService;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\Form\FormFactoryInterface;
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
    public function indexAction(Request $request)
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
                $verdictTable[$verdict][$verdict2] = array();
            }
        }

        /** @var Judging[] $originalVerdicts */
        /** @var Judging[] $newVerdicts */
        $originalVerdicts = [];
        $newVerdicts      = [];

        $this->em->transactional(function () use ($rejudging, &$originalVerdicts, &$newVerdicts) {
            $expr             = $this->em->getExpressionBuilder();
            $originalVerdicts = $this->em->createQueryBuilder()
                ->from(Judging::class, 'j', 'j.submitid')
                ->select('j')
                ->where(
                    $expr->in('j.judgingid',
                              $this->em->createQueryBuilder()
                                  ->from(Judging::class, 'j2')
                                  ->select('j2.prevjudgingid')
                                  ->andWhere('j2.rejudging = :rejudging')
                                  ->andWhere('j2.endtime IS NOT NULL')
                                  ->getDQL()
                    )
                )
                ->setParameter(':rejudging', $rejudging)
                ->getQuery()
                ->getResult();

            $newVerdicts = $this->em->createQueryBuilder()
                ->from(Judging::class, 'j', 'j.submitid')
                ->select('j')
                ->andWhere('j.rejudging = :rejudging')
                ->andWhere('j.endtime IS NOT NULL')
                ->setParameter(':rejudging', $rejudging)
                ->getQuery()
                ->getResult();
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
        list($submissions, $submissionCounts) = $submissionService->getSubmissionList(
            $this->dj->getCurrentContests(),
            $restrictions
        );

        $repetitions = $this->em->createQueryBuilder()
            ->from(Rejudging::class, 'r')
            ->select('r.rejudgingid')
            ->andWhere('r.repeat_rejudgingid = :repeat_rejudgingid')
            ->andWhere('r.rejudgingid != :rejudgingid')
            ->setParameter(':repeat_rejudgingid', $rejudging->getRepeatRejudgingId())
            ->setParameter(':rejudgingid', $rejudging->getRejudgingid())
            ->orderBy('r.rejudgingid')
            ->getQuery()
            ->getScalarResult();

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
     * @param Request          $request
     * @param RejudgingService $rejudgingService
     * @param Profiler|null    $profiler
     * @param int              $rejudgingId
     * @param string           $action
     * @return Response|StreamedResponse
     * @throws \Doctrine\ORM\NonUniqueResultException
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
     * @param Request              $request
     * @param FormFactoryInterface $formFactory
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    public function addAction(Request $request, FormFactoryInterface $formFactory)
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
                ->select('j', 's')
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
                ->getResult(Query::HYDRATE_ARRAY);
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
     * @param Request           $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function createAction(Request $request)
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
            'contest' => 's.cid',
            'judgehost' => 'j.judgehost',
            'language' => 's.langid',
            'problem' => 's.probid',
            'submission' => 's.submitid',
            'team' => 's.teamid',
            'rejudging' => 'j2.rejudgingid',
        ];

        if (!isset($tablemap[$table])) {
            throw new BadRequestHttpException(sprintf('unknown table %s in rejudging', $table));
        }

        // Only rejudge submissions in active contests.
        $contests = $this->dj->getCurrentContests();

        $queryBuilder = $this->em->createQueryBuilder()
            ->from(Judging::class, 'j')
            ->leftJoin('j.submission', 's')
            ->select('j', 's')
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
            ->getResult(Query::HYDRATE_ARRAY);

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
            }
        }
    }

    private function generateFlashMessagesForSkippedJudgings(array $skipped): void
    {
        foreach ($skipped as $judging) {
            $submission = $judging['submission'];
            $msg = sprintf(
                'Skipping submission <a href="%s">s%d</a> since it is ' .
                'already part of rejudging <a href="%s">r%d</a>.',
                $this->generateUrl('jury_submission', ['submitId' => $submission['submitid']]),
                $submission['submitid'],
                $this->generateUrl('jury_rejudging', ['rejudgingId' => $submission['rejudgingid']]),
                $submission['rejudgingid']
            );
            $this->addFlash('danger', $msg);
        }
    }
}
