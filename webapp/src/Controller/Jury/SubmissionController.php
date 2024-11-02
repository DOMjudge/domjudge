<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Controller\BaseController;
use App\DataTransferObject\SubmissionRestriction;
use App\Doctrine\DBAL\Types\JudgeTaskType;
use App\Entity\Contest;
use App\Entity\DebugPackage;
use App\Entity\Executable;
use App\Entity\ExternalJudgement;
use App\Entity\Judgehost;
use App\Entity\JudgeTask;
use App\Entity\Judging;
use App\Entity\JudgingRun;
use App\Entity\Language;
use App\Entity\Problem;
use App\Entity\Submission;
use App\Entity\SubmissionFile;
use App\Entity\Team;
use App\Entity\TeamAffiliation;
use App\Entity\TeamCategory;
use App\Entity\Testcase;
use App\Form\Type\SubmissionsFilterType;
use App\Service\BalloonService;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Service\ScoreboardService;
use App\Service\SubmissionService;
use App\Utils\Utils;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query\Expr\Join;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_JURY')]
#[Route(path: '/jury/submissions')]
class SubmissionController extends BaseController
{
    use JudgeRemainingTrait;

    public function __construct(
        EntityManagerInterface $em,
        protected readonly EventLogService $eventLogService,
        DOMJudgeService $dj,
        protected readonly ConfigurationService $config,
        protected readonly SubmissionService $submissionService,
        protected readonly RouterInterface $router,
        KernelInterface $kernel,
    ) {
        parent::__construct($em, $eventLogService, $dj, $kernel);
    }

    #[Route(path: '', name: 'jury_submissions')]
    public function indexAction(
        Request $request,
        #[MapQueryParameter(name: 'view')]
        ?string $viewFromRequest = null,
    ): Response {
        $viewTypes = [0 => 'newest', 1 => 'unverified', 2 => 'unjudged', 3 => 'judging', 4 => 'all'];
        $view      = 0;
        if (($submissionViewCookie = $this->dj->getCookie('domjudge_submissionview')) &&
            isset($viewTypes[$submissionViewCookie])) {
            $view = $submissionViewCookie;
        }

        if ($viewFromRequest) {
            $index = array_search($viewFromRequest, $viewTypes);
            if ($index !== false) {
                $view = $index;
            }
        }

        $response = $this->dj->setCookie('domjudge_submissionview', (string)$view);

        $refresh = [
            'after' => 15,
            'url' => $this->generateUrl('jury_submissions', ['view' => $viewTypes[$view]]),
            'ajax' => true,
        ];

        $restrictions = new SubmissionRestriction();
        if ($viewTypes[$view] == 'unverified') {
            $restrictions->verified = false;
        }
        if ($viewTypes[$view] == 'unjudged') {
            $restrictions->judged = false;
        }
        if ($viewTypes[$view] == 'judging') {
            $restrictions->judging = true;
        }

        $contests = $this->dj->getCurrentContests();
        if ($contest = $this->dj->getCurrentContest()) {
            $contests = [$contest->getCid() => $contest];
        }

        $latestCount = 50;

        $limit = $viewTypes[$view] == 'newest' ? $latestCount : 0;

        /** @var Submission[] $submissions */
        [$submissions, $submissionCounts] =
            $this->submissionService->getSubmissionList($contests, $restrictions, $limit);
        $disabledProblems = [];
        $disabledLangs = [];
        foreach ($submissions as $submission) {
            if (!$submission->getContestProblem()->getAllowJudge()) {
                $disabledProblems[$submission->getProblemId()] = $submission->getProblem()->getName();
            }
            if (!$submission->getLanguage()->getAllowJudge()) {
                $disabledLangs[$submission->getLanguage()->getLangid()] = $submission->getLanguage()->getName();
            }
        }

        // Load preselected filters
        $filters = $this->dj->jsonDecode((string)$this->dj->getCookie('domjudge_submissionsfilter') ?: '[]');

        $results = array_keys($this->dj->getVerdicts());
        $results[] = 'judging';
        $results[] = 'queued';

        $data = [
            'refresh' => $refresh,
            'viewTypes' => $viewTypes,
            'view' => $view,
            'submissions' => $submissions,
            'submissionCounts' => $submissionCounts,
            'showContest' => count($contests) > 1,
            'hasFilters' => !empty($filters),
            'results' => $results,
            'showExternalResult' => $this->dj->shadowMode(),
            'showTestcases' => count($submissions) <= $latestCount,
            'disabledProbs' => $disabledProblems,
            'disabledLangs' => $disabledLangs,
        ];

        // For ajax requests, only return the submission list partial.
        if ($request->isXmlHttpRequest()) {
            return $this->render('jury/partials/submission_list.html.twig', $data);
        }

        // Build the filter form.
        $filtersForForm                = $filters;
        $filtersForForm['problem-id']  = $this->em->getRepository(Problem::class)->findBy(['probid' => $filtersForForm['problem-id'] ?? []]);
        $filtersForForm['language-id'] = $this->em->getRepository(Language::class)->findBy(['langid' => $filtersForForm['language-id'] ?? []]);
        $filtersForForm['team-id']     = $this->em->getRepository(Team::class)->findBy(['teamid' => $filtersForForm['team-id'] ?? []]);
        $filtersForForm['category-id'] = $this->em->getRepository(TeamCategory::class)->findBy(['categoryid' => $filtersForForm['category-id'] ?? []]);
        $filtersForForm['affiliation-id'] = $this->em->getRepository(TeamAffiliation::class)->findBy(['affilid' => $filtersForForm['affiliation-id'] ?? []]);
        $form = $this->createForm(SubmissionsFilterType::class, array_merge($filtersForForm, [
            "contests" => $contests,
        ]));
        $data["form"] = $form->createView();

        return $this->render('jury/submissions.html.twig', $data, $response);
    }

    /**
     * @throws NonUniqueResultException
     */
    #[Route(path: '/{submitId<\d+>}', name: 'jury_submission')]
    public function viewAction(
        Request $request,
        int $submitId,
        #[MapQueryParameter(name: 'jid')]
        ?int $judgingId = null,
        #[MapQueryParameter(name: 'rejudgingid')]
        ?int $rejudgingId = null,
    ): Response {
        if (isset($judgingId, $rejudgingId)) {
            throw new BadRequestHttpException("You cannot specify jid and rejudgingid at the same time.");
        }

        // If judging ID is not set but rejudging ID is, try to deduce the judging ID from the database.
        if (!isset($judgingId) && isset($rejudgingId)) {
            $judging = $this->em->getRepository(Judging::class)
                ->findOneBy([
                                'submission' => $submitId,
                                'rejudging' => $rejudgingId
                            ]);
            if ($judging) {
                $judgingId = $judging->getJudgingid();
            }
        }

        /** @var Submission|null $submission */
        $submission = $this->em->createQueryBuilder()
            ->from(Submission::class, 's')
            ->join('s.team', 't')
            ->join('s.problem', 'p')
            ->join('s.language', 'l')
            ->join('s.contest', 'c')
            ->leftJoin('s.files', 'f')
            ->leftJoin('s.external_judgements', 'ej', Join::WITH, 'ej.valid = 1')
            ->leftJoin('s.contest_problem', 'cp')
            ->select('s', 't', 'p', 'l', 'c', 'f', 'cp', 'ej')
            ->andWhere('s.submitid = :submitid')
            ->setParameter('submitid', $submitId)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$submission) {
            throw new NotFoundHttpException(sprintf('No submission found with ID %d', $submitId));
        }

        $judgingData = $this->em->createQueryBuilder()
            ->from(Judging::class, 'j', 'j.judgingid')
            ->leftJoin('j.runs', 'jr')
            ->leftJoin('j.rejudging', 'r')
            ->select('j', 'r', 'MAX(jr.runtime) AS max_runtime')
            ->andWhere('j.contest = :contest')
            ->andWhere('j.submission = :submission')
            ->setParameter('contest', $submission->getContest())
            ->setParameter('submission', $submission)
            ->groupBy('j.judgingid')
            ->orderBy('j.judgingid')
            ->getQuery()
            ->getResult();

        // These three arrays are indexed by judgingid
        /** @var Judging[] $judgings */
        $judgings    = array_map(fn($data) => $data[0], $judgingData);
        $maxRunTimes = array_map(fn($data) => $data['max_runtime'], $judgingData);
        $timelimits  = [];

        if ($judgings) {
            $judgeTasks = $this->em->createQueryBuilder()
                ->from(JudgeTask::class, 'jt', 'jt.jobid')
                ->select('jt')
                ->andWhere('jt.jobid IN (:jobIds)')
                ->andWhere('jt.type = :type')
                ->setParameter(
                    'jobIds',
                    array_map(static fn(Judging $judging) => $judging->getJudgingid(), $judgings)
                )
                ->setParameter('type', JudgeTaskType::JUDGING_RUN)
                ->getQuery()
                ->getResult();
            $timelimits = array_map(function (JudgeTask $task) {
                return $this->dj->jsonDecode($task->getRunConfig())['time_limit'];
            }, $judgeTasks);
        }

        $selectedJudging = null;
        // Find the selected judging.
        if ($judgingId !== null) {
            $selectedJudging = $judgings[$judgingId] ?? null;
        } else {
            foreach ($judgings as $judging) {
                if ($judging->getValid()) {
                    $selectedJudging = $judging;
                }
            }
        }

        $claimWarning = null;

        if ($request->get('claim') || $request->get('unclaim')) {
            if ($response = $this->processClaim($selectedJudging, $request, $claimWarning)) {
                return $response;
            }
        }

        if ($request->get('claimdiff') || $request->get('unclaimdiff')) {
            $externalJudgement = $submission->getExternalJudgements()->first();
            if ($response = $this->processClaim($externalJudgement, $request, $claimWarning)) {
                return $response;
            }
        }

        $outputDisplayLimit    = (int)$this->config->get('output_display_limit');
        $outputTruncateMessage = sprintf("\n[output display truncated after %d B]\n", $outputDisplayLimit);

        $externalRuns = [];
        if ($externalJudgement = $submission->getExternalJudgements()->first()) {
            $queryBuilder = $this->em->createQueryBuilder()
                ->from(Testcase::class, 't')
                ->leftJoin('t.external_runs', 'er', Join::WITH, 'er.external_judgement = :judging')
                ->select('t', 'er')
                ->andWhere('t.problem = :problem')
                ->setParameter('judging', $externalJudgement)
                ->setParameter('problem', $submission->getProblem())
                ->orderBy('t.ranknumber');

            $externalRunResults = $queryBuilder
                ->getQuery()
                ->getResult();

            foreach ($externalRunResults as $externalRunResult) {
                $externalRuns[] = $externalRunResult;
            }
        }

        $judgehosts = $this->em->createQueryBuilder()
            ->from(JudgeTask::class, 'jt')
            ->join('jt.judgehost', 'jh')
            ->select('jh.judgehostid', 'jh.hostname')
            ->andWhere('jt.judgehost IS NOT NULL')
            ->andWhere('jt.jobid = :judging')
            ->setParameter('judging', $selectedJudging)
            ->groupBy('jh.hostname')
            ->orderBy('jh.hostname')
            ->getQuery()
            ->getScalarResult();
        $judgehosts = array_combine(
            array_column($judgehosts, 'judgehostid'),
            array_column($judgehosts, 'hostname')
        );

        $runsOutstanding = false;
        $runs       = [];
        $runsOutput = [];
        $sameTestcaseIds = true;
        if ($selectedJudging || $externalJudgement) {
            $queryBuilder = $this->em->createQueryBuilder()
                ->from(Testcase::class, 't')
                ->join('t.content', 'tc')
                ->leftJoin('t.judging_runs', 'jr', Join::WITH, 'jr.judging = :judging')
                ->leftJoin('jr.output', 'jro')
                ->select('t', 'jr', 'tc.image_thumb AS image_thumb', 'jro.metadata')
                ->andWhere('t.problem = :problem')
                ->setParameter('judging', $selectedJudging)
                ->setParameter('problem', $submission->getProblem())
                ->orderBy('t.ranknumber');
            if ($outputDisplayLimit < 0) {
                $queryBuilder
                    ->addSelect('tc.output AS output_reference')
                    ->addSelect('jro.output_run AS output_run')
                    ->addSelect('jro.output_diff AS output_diff')
                    ->addSelect('jro.output_error AS output_error')
                    ->addSelect('jro.team_message As team_message')
                    ->addSelect('jro.output_system AS output_system');
            } else {
                $queryBuilder
                    ->addSelect('TRUNCATE(tc.output, :outputDisplayLimit, :outputTruncateMessage) AS output_reference')
                    ->addSelect('TRUNCATE(jro.output_run, :outputDisplayLimit, :outputTruncateMessage) AS output_run')
                    ->addSelect('RIGHT(jro.output_run, 50) AS output_run_last_bytes')
                    ->addSelect('TRUNCATE(jro.output_diff, :outputDisplayLimit, :outputTruncateMessage) AS output_diff')
                    ->addSelect('TRUNCATE(jro.output_error, :outputDisplayLimit, :outputTruncateMessage) AS output_error')
                    ->addSelect('TRUNCATE(jro.team_message, :outputDisplayLimit, :outputTruncateMessage) AS team_message')
                    ->addSelect('TRUNCATE(jro.output_system, :outputDisplayLimit, :outputTruncateMessage) AS output_system')
                    ->setParameter('outputDisplayLimit', $outputDisplayLimit)
                    ->setParameter('outputTruncateMessage', $outputTruncateMessage);
            }

            $runResults = $queryBuilder
                ->getQuery()
                ->getResult();

            $judgingRunTestcaseIdsInOrder = $this->em->createQueryBuilder()
                ->from(JudgeTask::class, 'jt')
                ->select('jt.testcase_id')
                ->andWhere('jt.jobid = :judging')
                ->setParameter('judging', $selectedJudging)
                ->orderBy('jt.judgetaskid')
                ->getQuery()
                ->getScalarResult();

            $cnt = 0;
            if (count($judgingRunTestcaseIdsInOrder) !== count($runResults)) {
                $sameTestcaseIds = false;
            }
            foreach ($runResults as $runResult) {
                /** @var Testcase $testcase */
                $testcase = $runResult[0];
                if (isset($judgingRunTestcaseIdsInOrder[$cnt])) {
                    if ($testcase->getTestcaseid() != $judgingRunTestcaseIdsInOrder[$cnt]['testcase_id']) {
                        $sameTestcaseIds = false;
                    }
                }
                $cnt++;
                /** @var JudgingRun|null $firstJudgingRun */
                $firstJudgingRun = $runResult[0]->getFirstJudgingRun();
                if ($firstJudgingRun !== null && $firstJudgingRun->getRunresult() === null) {
                    $runsOutstanding = true;
                }
                $runs[] = $runResult[0];
                unset($runResult[0]);
                if (!empty($runResult['metadata'])) {
                    $metadata = $this->dj->parseMetadata($runResult['metadata']);
                    $runResult['output_limit'] = $metadata['output-truncated'] ?? 'n/a';
                }
                $runResult['terminated'] = preg_match('/timelimit exceeded.*hard (wall|cpu) time/',
                                                      (string)$runResult['output_system']);
                $runResult['hostname'] = null;
                $runResult['judgehostid'] = null;
                if ($firstJudgingRun && $firstJudgingRun->getJudgeTask() && $firstJudgingRun->getJudgeTask()->getJudgehost()) {
                    $runResult['hostname'] = $firstJudgingRun->getJudgeTask()->getJudgehost()->getHostname();
                    $runResult['judgehostid'] = $firstJudgingRun->getJudgeTask()->getJudgehost()->getJudgehostid();
                }
                $runResult['is_output_run_truncated_in_db'] = preg_match(
                    '/\[output storage truncated after \d* B\]/',
                    $outputDisplayLimit >= 0 ?
                        (string)$runResult['output_run_last_bytes'] : (string)$runResult['output_run']
                );
                if ($firstJudgingRun) {
                    $runResult['testcasedir'] = $firstJudgingRun->getTestcaseDir();
                }
                $runsOutput[] = $runResult;
            }
        }

        if ($submission->getOriginalSubmission()) {
            $lastSubmission = $submission->getOriginalSubmission();
        } else {
            /** @var Submission|null $lastSubmission */
            $lastSubmission = $this->em->createQueryBuilder()
                ->from(Submission::class, 's')
                ->select('s')
                ->andWhere('s.team = :team')
                ->andWhere('s.problem = :problem')
                ->andWhere('s.submittime < :submittime')
                ->setParameter('team', $submission->getTeam())
                ->setParameter('problem', $submission->getProblem())
                ->setParameter('submittime', $submission->getSubmittime())
                ->orderBy('s.submittime', 'DESC')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
        }

        /** @var Judging|null $lastJudging */
        $lastJudging = null;
        /** @var Testcase[] $lastRuns */
        $lastRuns = [];
        if ($lastSubmission !== null) {
            $lastJudging = $this->em->createQueryBuilder()
                ->from(Judging::class, 'j')
                ->select('j')
                ->andWhere('j.submission = :submission')
                ->andWhere('j.valid = 1')
                ->setParameter('submission', $lastSubmission)
                ->orderBy('j.judgingid', 'DESC')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if ($lastJudging !== null) {
                // Clear the testcases, otherwise Doctrine will use the previous data
                $this->em->clear();
                $lastRuns = $this->em->createQueryBuilder()
                    ->from(Testcase::class, 't')
                    ->leftJoin('t.judging_runs', 'jr', Join::WITH, 'jr.judging = :judging')
                    ->select('t', 'jr')
                    ->andWhere('t.problem = :problem')
                    ->setParameter('judging', $lastJudging)
                    ->setParameter('problem', $submission->getProblem())
                    ->orderBy('t.ranknumber')
                    ->getQuery()
                    ->getResult();
            }
        }

        $unjudgableReasons = [];
        if ($runsOutstanding || $submission->getResult() == null) {
            // Determine if this submission is unjudgable.

            $numActiveJudgehosts = (int)$this->em->createQueryBuilder()
                ->from(Judgehost::class, 'j')
                ->select('count(j.judgehostid)')
                ->andWhere('j.enabled = 1')
                ->getQuery()
                ->getSingleScalarResult();
            if ($numActiveJudgehosts == 0) {
                $extraMsg = '';
                if (!$this->config->get('judgehost_activated_by_default')) {
                    $extraMsg = ' (judgehosts are disabled by default in your configuration)';
                }
                $unjudgableReasons[] = 'No active judgehost. Add or enable judgehosts' . $extraMsg . '!';
            }

            if (!$submission->getLanguage()->getAllowJudge()) {
                $unjudgableReasons[] = 'Submission language is currently not allowed to be judged!';
            }

            if (!$submission->getContestProblem()->getAllowJudge()) {
                $unjudgableReasons[] = 'Problem is currently not allowed to be judged!';
            }
        }

        if (!isset($judging)) {
            $requestedOutputCount = 0;
        } else {
            $requestedOutputCount = (int)$this->em->createQueryBuilder()
                ->from(JudgeTask::class, 'jt')
                ->select('count(jt.judgetaskid)')
                ->andWhere('jt.type = :type')
                ->andWhere('jt.jobid = :judgingid')
                ->andWhere('jt.starttime IS NULL')
                ->setParameter('type', JudgeTaskType::DEBUG_INFO)
                ->setParameter('judgingid', $judging->getJudgingid())
                ->getQuery()
                ->getSingleScalarResult();
        }

        $twigData = [
            'submission' => $submission,
            'lastSubmission' => $lastSubmission,
            'judgings' => $judgings,
            'maxRunTimes' => $maxRunTimes,
            'timelimits' => $timelimits,
            'selectedJudging' => $selectedJudging,
            'lastJudging' => $lastJudging,
            'runs' => $runs,
            'runsOutstanding' => $runsOutstanding,
            'judgehosts' => $judgehosts,
            'sameTestcaseIds' => $sameTestcaseIds,
            'externalRuns' => $externalRuns,
            'runsOutput' => $runsOutput,
            'lastRuns' => $lastRuns,
            'unjudgableReasons' => $unjudgableReasons,
            'verificationRequired' => (bool)$this->config->get('verification_required'),
            'claimWarning' => $claimWarning,
            'combinedRunCompare' => $submission->getProblem()->getCombinedRunCompare(),
            'requestedOutputCount' => $requestedOutputCount,
            'version_warnings' => [],
            'isMultiPassProblem' => $submission->getProblem()->isMultipassProblem(),
        ];

        if ($selectedJudging === null) {
            // Automatically refresh page while we wait for judging data.
            $twigData['refresh'] = [
                'after' => 15,
                'url' => $this->generateUrl('jury_submission', ['submitId' => $submission->getSubmitid()]),
            ];
        } else {
            $contestProblem = $submission->getContestProblem();
            /** @var JudgeTask[] $judgeTasks */
            $judgeTasks = $this->em->getRepository(JudgeTask::class)->findBy([
                'jobid' => $selectedJudging->getJudgingid(),
                'type' => JudgeTaskType::JUDGING_RUN,
                ]);
            $unique_compiler_versions = [];
            $unique_runner_versions = [];
            $sampleJudgeTask = null;
            foreach ($judgeTasks as $judgeTask) {
                $sampleJudgeTask = $judgeTask;
                $version = $judgeTask->getVersion();
                if (!$version) {
                    continue;
                }
                if ($version->getCompilerVersion()) {
                    $unique_compiler_versions[$version->getCompilerVersion()] = true;
                }
                if ($version->getRunnerVersion()) {
                    $unique_runner_versions[$version->getRunnerVersion()] = true;
                }
            }
            if (count($unique_compiler_versions) > 1) {
                $twigData['version_warnings']['compiler'] = array_keys($unique_compiler_versions);
            }
            if (count($unique_runner_versions) > 1) {
                $twigData['version_warnings']['runner'] = array_keys($unique_runner_versions);
            }
            if ($sampleJudgeTask !== null) {
                $errors = [];
                $this->maybeGetErrors('Compile config',
                    $this->dj->getCompileConfig($submission),
                    $sampleJudgeTask->getCompileConfig(),
                    $errors);
                $this->maybeGetErrors('Run config',
                    $this->dj->getRunConfig($contestProblem, $submission),
                    $sampleJudgeTask->getRunConfig(),
                    $errors);
                $this->maybeGetErrors('Compare config',
                    $this->dj->getCompareConfig($contestProblem),
                    $sampleJudgeTask->getCompareConfig(),
                    $errors);
                if (!empty($errors)) {
                    if ($selectedJudging->getValid()) {
                        $type = 'danger';
                        $header = "Some parameters have changed since the judging was created, consider rejudging.\n\n";
                    } else {
                        $type = 'warning';
                        $header = "Some parameters have changed since the judging was created, but this judging has been superseded, please verify if that needs a rejudging.\n\n";
                    }
                    $this->addFlash($type, $header . implode("\n", $errors));
                }
            }
        }

        return $this->render('jury/submission.html.twig', $twigData);
    }

    #[Route(path: '/request-full-debug/{jid}', name: 'request_full_debug')]
    public function requestFullDebug(Request $request, Judging $jid): RedirectResponse
    {
        $submission = $jid->getSubmission();
        $defaultFullDebugExecutable = $this->em
            ->getRepository(Executable::class)
            ->findOneBy(['execid' => $this->config->get('default_full_debug')]);
        if ($defaultFullDebugExecutable === null) {
            $this->addFlash('error', 'No default full_debug executable specified, please configure one.');
        } else {
            $executable = $defaultFullDebugExecutable->getImmutableExecutable();
            foreach ($jid->getJudgehosts() as $hostname) {
                $judgehost = $this->em
                    ->getRepository(Judgehost::class)
                    ->findOneBy(['hostname' => $hostname]);
                $judgeTask = new JudgeTask();
                $judgeTask
                    ->setType(JudgeTaskType::DEBUG_INFO)
                    ->setJudgehost($judgehost)
                    ->setSubmission($submission)
                    ->setPriority(JudgeTask::PRIORITY_HIGH)
                    ->setJobId($jid->getJudgingid())
                    ->setUuid($jid->getUuid())
                    ->setRunScriptId($executable->getImmutableExecId())
                    ->setRunConfig($this->dj->jsonEncode(['hash' => $executable->getHash()]));
                $this->em->persist($judgeTask);
            }
            $this->em->flush();
        }
        return $this->redirectToLocalReferrer($this->router, $request, $this->generateUrl('jury_submission', [
            'submitId' => $jid->getSubmission()->getSubmitid(),
            'jid' => $jid->getJudgingid(),
        ]));
    }

    #[Route(path: '/download-full-debug/{debug_package_id}', name: 'download_full_debug')]
    public function downloadFullDebug(DebugPackage $debugPackage): StreamedResponse
    {
        $name = 'debug_package.j' . $debugPackage->getJudging()->getJudgingid()
            . '.db' . $debugPackage->getDebugPackageId()
            . '.jh' . $debugPackage->getJudgehost()->getJudgehostid()
            . '.tar.gz';
        return Utils::streamAsBinaryFile(file_get_contents($debugPackage->getFilename()), $name);
    }

    #[Route(path: '/request-output/{jid}/{jrid}', name: 'request_output')]
    public function requestOutput(Request $request, Judging $jid, JudgingRun $jrid): RedirectResponse
    {
        $submission = $jid->getSubmission();
        $testcase = $jrid->getTestcase();
        $judgeTask = new JudgeTask();
        $judgeTask
            ->setType(JudgeTaskType::DEBUG_INFO)
            ->setJudgehost($jrid->getJudgeTask()->getJudgehost())
            ->setSubmission($submission)
            ->setPriority(JudgeTask::PRIORITY_HIGH)
            ->setJobId($jid->getJudgingid())
            ->setUuid($jid->getUuid())
            ->setTestcaseId($testcase->getTestcaseid())
            ->setTestcaseHash($testcase->getMd5sumInput() . '_' . $testcase->getMd5sumOutput());
        $this->em->persist($judgeTask);
        $this->em->flush();
        return $this->redirectToLocalReferrer($this->router, $request, $this->generateUrl('jury_submission', [
            'submitId' => $jid->getSubmission()->getSubmitid(),
            'jid' => $jid->getJudgingid(),
        ]));
    }

    #[Route(path: '/by-judging-id/{jid}', name: 'jury_submission_by_judging')]
    public function viewForJudgingAction(Judging $jid): RedirectResponse
    {
        return $this->redirectToRoute('jury_submission', [
            'submitId' => $jid->getSubmission()->getSubmitid(),
            'jid' => $jid->getJudgingid(),
        ]);
    }

    #[Route(path: '/by-external-judgement-id/{externalJudgement}', name: 'jury_submission_by_external_judgement')]
    public function viewForExternalJudgementAction(ExternalJudgement $externalJudgement): RedirectResponse
    {
        return $this->redirectToRoute('jury_submission', [
            'submitId' => $externalJudgement->getSubmission()->getSubmitid(),
        ]);
    }

    #[Route(path: '/by-contest-and-external-id/{externalContestId}/{externalId}', name: 'jury_submission_by_context_external_id')]
    public function viewForContestExternalIdAction(string $externalContestId, string $externalId): Response
    {
        $contest = $this->em->getRepository(Contest::class)->findOneBy(['externalid' => $externalContestId]);
        if ($contest === null) {
            throw new NotFoundHttpException(sprintf('No contest found with external ID %s', $externalContestId));
        }

        $submission = $this->em->getRepository(Submission::class)
            ->findOneBy([
                'contest' => $contest,
                'externalid' => $externalId
            ]);

        if (!$submission) {
            throw new NotFoundHttpException(sprintf('No submission found with external ID %s', $externalId));
        }

        return $this->redirectToRoute('jury_submission', [
            'submitId' => $submission->getSubmitid(),
        ]);
    }

    #[Route(path: '/by-external-id/{externalId}', name: 'jury_submission_by_external_id')]
    public function viewForExternalIdAction(string $externalId): RedirectResponse
    {
        if (!$this->dj->getCurrentContest()) {
            throw new BadRequestHttpException("Cannot determine submission from external ID without selecting a contest.");
        }

        $submission = $this->em->getRepository(Submission::class)
            ->findOneBy([
                            'contest' => $this->dj->getCurrentContest(),
                            'externalid' => $externalId
                        ]);

        if (!$submission) {
            throw new NotFoundHttpException(sprintf('No submission found with external ID %s', $externalId));
        }

        return $this->redirectToRoute('jury_submission', [
            'submitId' => $submission->getSubmitid(),
        ]);
    }

    #[Route(path: '/{submission}/runs/{contest}/{run}/team-output', name: 'jury_submission_team_output')]
    public function teamOutputAction(Submission $submission, Contest $contest, JudgingRun $run): StreamedResponse
    {
        if ($run->getJudging()->getSubmission()->getSubmitid() !== $submission->getSubmitid() || $submission->getContest()->getCid() !== $contest->getCid()) {
            throw new BadRequestHttpException('Integrity problem while fetching team output.');
        }
        if ($run->getOutput() === null) {
            throw new NotFoundHttpException('No team output available (yet).');
        }

        $filename = sprintf('p%d.t%d.%s.run%d.team%d.out', $submission->getProblem()->getProbid(), $run->getTestcase()->getRank(),
                            $submission->getContestProblem()->getShortname(), $run->getRunid(),
                            $submission->getTeam()->getTeamid());

        $outputRun = $run->getOutput()->getOutputRun();
        return Utils::streamAsBinaryFile($outputRun, $filename);
    }

    private function allowEdit(): bool {
        return $this->dj->getUser()->getTeam() && $this->dj->checkrole('team');
    }

    /**
     * @throws NonUniqueResultException
     */
    #[Route(path: '/{submission}/source', name: 'jury_submission_source')]
    public function sourceAction(
        Submission $submission,
        #[MapQueryParameter]
        ?int $fetch = null
    ): Response {
        if ($fetch !== null) {
            /** @var SubmissionFile|null $file */
            $file = $this->em->createQueryBuilder()
                ->from(SubmissionFile::class, 'file')
                ->select('file')
                ->andWhere('file.ranknumber = :ranknumber')
                ->andWhere('file.submission = :submission')
                ->setParameter('ranknumber', $fetch)
                ->setParameter('submission', $submission)
                ->getQuery()
                ->getOneOrNullResult();
            if (!$file) {
                throw new NotFoundHttpException(sprintf('No submission file found with rank %s',
                                                        $fetch));
            }
            // Download requested
            $response = new Response();
            $response->headers->set('Content-Type',
                                    sprintf('text/plain; name="%s"; charset="utf-8"', $file->getFilename()));
            $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $file->getFilename()));
            $response->headers->set('Content-Length', (string)strlen($file->getSourcecode()));
            $response->setContent($file->getSourcecode());

            return $response;
        }

        /** @var SubmissionFile[] $files */
        $files = $this->em->createQueryBuilder()
            ->from(SubmissionFile::class, 'file')
            ->select('file')
            ->andWhere('file.submission = :submission')
            ->setParameter('submission', $submission)
            ->orderBy('file.ranknumber')
            ->getQuery()
            ->getResult();

        $originalSubmission = $originalFiles = null;

        if ($submission->getOriginalSubmission()) {
            /** @var Submission $originalSubmission */
            $originalSubmission = $this->em->getRepository(Submission::class)->find($submission->getOriginalSubmission()->getSubmitid());

            /** @var SubmissionFile[] $files */
            $originalFiles = $this->em->createQueryBuilder()
                ->from(SubmissionFile::class, 'file')
                ->select('file')
                ->andWhere('file.submission = :submission')
                ->setParameter('submission', $originalSubmission)
                ->orderBy('file.ranknumber')
                ->getQuery()
                ->getResult();

            /** @var Submission $oldSubmission */
            $oldSubmission = $this->em->createQueryBuilder()
                ->from(Submission::class, 's')
                ->select('s')
                ->andWhere('s.problem = :probid')
                ->andWhere('s.language = :langid')
                ->andWhere('s.submittime < :submittime')
                ->andWhere('s.originalSubmission = :origsubmitid')
                ->setParameter('probid', $submission->getProblem())
                ->setParameter('langid', $submission->getLanguage())
                ->setParameter('submittime', $submission->getSubmittime())
                ->setParameter('origsubmitid', $submission->getOriginalSubmission())
                ->orderBy('s.submittime', 'DESC')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
        } else {
            $oldSubmission = $this->em->createQueryBuilder()
                ->from(Submission::class, 's')
                ->select('s')
                ->andWhere('s.team = :teamid')
                ->andWhere('s.problem = :probid')
                ->andWhere('s.language = :langid')
                ->andWhere('s.submittime < :submittime')
                ->setParameter('teamid', $submission->getTeam())
                ->setParameter('probid', $submission->getProblem())
                ->setParameter('langid', $submission->getLanguage())
                ->setParameter('submittime', $submission->getSubmittime())
                ->orderBy('s.submittime', 'DESC')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
        }

        /** @var SubmissionFile[] $files */
        $oldFiles = $this->em->createQueryBuilder()
            ->from(SubmissionFile::class, 'file')
            ->select('file')
            ->andWhere('file.submission = :submission')
            ->setParameter('submission', $oldSubmission)
            ->orderBy('file.ranknumber')
            ->getQuery()
            ->getResult();

        $oldFileStats      = $oldFiles !== null ? $this->determineFileChanged($files, $oldFiles) : [];
        $originalFileStats = $originalFiles !== null ? $this->determineFileChanged($files, $originalFiles) : [];

        return $this->render('jury/submission_source.html.twig', [
            'submission' => $submission,
            'files' => $files,
            'oldSubmission' => $oldSubmission,
            'oldFiles' => $oldFiles,
            'oldFileStats' => $oldFileStats,
            'originalSubmission' => $originalSubmission,
            'originalFiles' => $originalFiles,
            'originalFileStats' => $originalFileStats,
            'allowEdit' => $this->allowEdit(),
        ]);
    }

    #[Route(path: '/{submission}/edit-source', name: 'jury_submission_edit_source')]
    public function editSourceAction(Request $request, Submission $submission, #[MapQueryParameter] ?int $rank = null): Response
    {
        if (!$this->allowEdit()) {
            $this->addFlash('danger', 'You cannot re-submit code without being a team.');
            return $this->redirectToLocalReferrer($this->router, $request, $this->generateUrl(
                'jury_submission',
                ['submitId' => $submission->getSubmitid()]
            ));
        }

        /** @var SubmissionFile[] $files */
        $files = $this->em->createQueryBuilder()
            ->from(SubmissionFile::class, 'file')
            ->select('file')
            ->andWhere('file.submission = :submission')
            ->setParameter('submission', $submission)
            ->orderBy('file.ranknumber')
            ->getQuery()
            ->getResult();

        $data = [
            'problem' => $submission->getProblem(),
            'language' => $submission->getLanguage(),
            'entry_point' => $submission->getEntryPoint(),
        ];

        foreach ($files as $file) {
            $data['source' . $file->getRank()] = $file->getSourcecode();
        }

        $formBuilder = $this->createFormBuilder($data)
            ->add('problem', EntityType::class, [
                'class' => Problem::class,
                'choice_label' => 'name',
                'query_builder' => fn(EntityRepository $er) => $er->createQueryBuilder('p')
                    ->join('p.contest_problems', 'cp')
                    ->andWhere('cp.allowSubmit = 1')
                    ->andWhere('cp.contest = :contest')
                    ->setParameter('contest', $submission->getContest())
                    ->orderBy('p.name'),
            ])
            ->add('language', EntityType::class, [
                'class' => Language::class,
                'choice_label' => 'name',
                'query_builder' => fn(EntityRepository $er) => $er->createQueryBuilder('lang')
                    ->andWhere('lang.allowSubmit = 1')
                    ->orderBy('lang.name')
            ])
            ->add('entry_point', TextType::class, [
                'label' => 'Optional entry point',
                'required' => false,
            ])
            ->add('submit', SubmitType::class);

        foreach ($files as $file) {
            $formBuilder->add('source' . $file->getRank(), TextareaType::class);
        }

        $form = $formBuilder->getForm();

        // Handle the form if it is submitted
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $submittedData = $form->getData();

            /** @var UploadedFile[] $filesToSubmit */
            $filesToSubmit = [];
            $tmpdir        = $this->dj->getDomjudgeTmpDir();
            foreach ($files as $file) {
                if (!($tmpfname = tempnam($tmpdir, "edit_source-"))) {
                    throw new ServiceUnavailableHttpException(null, "Could not create temporary file.");
                }
                file_put_contents($tmpfname, $submittedData['source' . $file->getRank()]);
                $filesToSubmit[] = new UploadedFile($tmpfname, $file->getFilename(), null, null, true);
            }

            $team = $this->dj->getUser()->getTeam();
            /** @var Language $language */
            $language   = $submittedData['language'];
            $entryPoint = $submittedData['entry_point'];
            if ($language->getRequireEntryPoint() && $entryPoint === null) {
                $entryPoint = '__auto__';
            }
            $submittedSubmission = $this->submissionService->submitSolution(
                $team,
                $this->dj->getUser(),
                $submittedData['problem'],
                $submission->getContest(),
                $language,
                $filesToSubmit,
                'edit/resubmit',
                $this->getUser()->getUserIdentifier(),
                $submission->getOriginalSubmission() ?? $submission,
                $entryPoint,
                null,
                null,
                $message
            );

            foreach ($filesToSubmit as $file) {
                unlink($file->getRealPath());
            }

            if (!$submittedSubmission) {
                $this->addFlash('danger', $message);
                return $this->redirectToRoute('jury_submission', ['submitId' => $submission->getSubmitid()]);
            }

            return $this->redirectToRoute('jury_submission', ['submitId' => $submittedSubmission->getSubmitid()]);
        }

        return $this->render('jury/submission_edit_source.html.twig', [
            'submission' => $submission,
            'files' => $files,
            'form' => $form,
            'selected' => $rank,
        ]);
    }

    /**
     * @throws DBALException
     */
    #[Route(path: '/{judgingId<\d+>}/request-remaining', name: 'jury_submission_request_remaining', methods: ['POST'])]
    public function requestRemainingRuns(Request $request, int $judgingId): RedirectResponse
    {
        $judging = $this->em->getRepository(Judging::class)->find($judgingId);
        if ($judging === null) {
            throw new BadRequestHttpException("Unknown judging with '$judgingId' requested.");
        }
        $this->judgeRemaining([$judging]);

        return $this->redirectToLocalReferrer($this->router, $request,
            $this->generateUrl('jury_submission_by_judging', ['jid' => $judgingId])
        );
    }

    /**
     * @throws DBALException
     */
    #[IsGranted('ROLE_ADMIN')]
    #[Route(path: '/{submitId<\d+>}/update-status', name: 'jury_submission_update_status', methods: ['POST'])]
    public function updateStatusAction(
        EventLogService $eventLogService,
        ScoreboardService $scoreboardService,
        Request $request,
        int $submitId
    ): RedirectResponse {
        $submission = $this->em->getRepository(Submission::class)->find($submitId);
        $valid      = $request->request->getBoolean('valid');
        $submission->setValid($valid);
        $this->em->flush();

        $contestId = $submission->getContest()->getCid();
        $teamId    = $submission->getTeam()->getTeamid();
        $problemId = $submission->getProblem()->getProbid();

        // KLUDGE: We can't log an "undelete", so we re-"create".
        // FIXME: We should also delete/recreate any dependent judging(runs).
        $eventLogService->log('submission', $submission->getSubmitid(), ($valid ? 'create' : 'delete'),
                              $submission->getContest()->getCid(), null, null, $valid);
        $this->dj->auditlog('submission', $submission->getSubmitid(),
                                         'marked ' . ($valid ? 'valid' : 'invalid'));
        $contest = $this->em->getRepository(Contest::class)->find($contestId);
        $team    = $this->em->getRepository(Team::class)->find($teamId);
        $problem = $this->em->getRepository(Problem::class)->find($problemId);
        $scoreboardService->calculateScoreRow($contest, $team, $problem);

        return $this->redirectToLocalReferrer($this->router, $request,
            $this->generateUrl('jury_submission', ['submitId' => $submission->getSubmitid()])
        );
    }

    /**
     * @throws DBALException
     * @throws NoResultException
     * @throws NonUniqueResultException
     * @throws ORMException
     */
    #[Route(path: '/{judgingId<\d+>}/verify', name: 'jury_judging_verify', methods: ['POST'])]
    public function verifyAction(
        EventLogService $eventLogService,
        ScoreboardService $scoreboardService,
        BalloonService $balloonService,
        Request $request,
        int $judgingId
    ): RedirectResponse {
        $this->em->wrapInTransaction(function () use ($eventLogService, $request, $judgingId) {
            /** @var Judging $judging */
            $judging  = $this->em->getRepository(Judging::class)->find($judgingId);
            $verified = $request->request->getBoolean('verified');
            $comment  = $request->request->get('comment');
            $judging
                ->setVerified($verified)
                ->setJuryMember($verified ? $this->dj->getUser()->getUserIdentifier() : null)
                ->setVerifyComment($comment);

            $this->em->flush();
            $this->dj->auditlog('judging', $judging->getJudgingid(),
                                             $verified ? 'set verified' : 'set unverified');

            if ((bool)$this->config->get('verification_required')) {
                // Log to event table (case of no verification required is handled
                // in the REST API JudgehostController::addJudgingRunAction).
                $eventLogService->log('judging', $judging->getJudgingid(), 'update', $judging->getContest()->getCid());
            }
        });

        if ((bool)$this->config->get('verification_required')) {
            $this->em->clear();
            /** @var Judging $judging */
            $judging = $this->em->getRepository(Judging::class)->find($judgingId);
            // We need to update the score for all teams for this problem, since the first to solve might now
            // have changed.
            $teamsQueryBuilder = $this->em->createQueryBuilder()
                                     ->from(Team::class, 't')
                                     ->select('t')
                                     ->orderBy('t.teamid');
            if (!$judging->getContest()->isOpenToAllTeams()) {
                $teamsQueryBuilder
                    ->leftJoin('t.contests', 'c')
                    ->join('t.category', 'cat')
                    ->leftJoin('cat.contests', 'cc')
                    ->andWhere('c.cid = :cid OR cc.cid = :cid')
                    ->setParameter('cid', $judging->getContest()->getCid());
            }
            /** @var Team[] $teams */
            $teams = $teamsQueryBuilder->getQuery()->getResult();
            foreach ($teams as $team) {
                $scoreboardService->calculateScoreRow($judging->getContest(), $team, $judging->getSubmission()->getProblem());
            }
            $balloonService->updateBalloons($judging->getContest(), $judging->getSubmission(), $judging);
        }

        // Redirect to local referrer page but fall back to same defaults
        if ($request->request->getBoolean('verified')) {
            $this->addFlash('info', "Verified judging j$judgingId");
            $redirect = $this->generateUrl('jury_submissions');
        } else {
            $this->addFlash('info', "Unmarked judging j$judgingId as verified");
            $redirect = $this->generateUrl('jury_submission_by_judging', ['jid' => $judgingId]);
        }

        return $this->redirectToLocalReferrer($this->router, $request, $redirect);
    }


    #[Route(path: '/shadow-difference/{extjudgementid<\d+>}/verify', name: 'jury_shadow_difference_verify', methods: ['POST'])]
    public function verifyShadowDifferenceAction(
        EventLogService $eventLogService,
        Request $request,
        int $extjudgementid
    ): RedirectResponse {
        /** @var ExternalJudgement $judgement */
        $judgement  = $this->em->getRepository(ExternalJudgement::class)->find($extjudgementid);
        $this->em->wrapInTransaction(function () use ($request, $judgement) {
            $verified = $request->request->getBoolean('verified');
            $comment  = $request->request->get('comment');
            $judgement
                ->setVerified($verified)
                ->setJuryMember($verified ? $this->dj->getUser()->getUserIdentifier() : null)
                ->setVerifyComment($comment);

            $this->em->flush();
            $this->dj->auditlog('external_judgement', $judgement->getExtjudgementid(),
                $verified ? 'set verified' : 'set unverified');
        });

        // Redirect to local referrer page but fall back to same defaults
        if ($request->request->getBoolean('verified')) {
            $redirect = $this->generateUrl('jury_shadow_differences');
        } else {
            $redirect = $this->generateUrl('jury_submission_by_external_judgement', ['externalJudgement' => $extjudgementid]);
        }

        return $this->redirectToLocalReferrer($this->router, $request, $redirect);
    }

    /**
     * @param SubmissionFile[] $files
     * @param SubmissionFile[] $oldFiles
     * @return array{'changed': string[], 'changedfiles': array<SubmissionFile[]>,
     *               'unchanged': string[], 'added': string[], 'removed': string[]}
     */
    protected function determineFileChanged(array $files, array $oldFiles): array
    {
        $result = [
            'changed'      => [],
            'changedfiles' => [], // These will be shown, so we will add pairs of files here.
            'unchanged'    => [],
        ];

        $newFilenames = array_map(fn($f) => $f->getFilename(), $files);
        $oldFilenames = array_map(fn($f) => $f->getFilename(), $oldFiles);
        $result['added']   = array_diff($newFilenames, $oldFilenames);
        $result['removed'] = array_diff($oldFilenames, $newFilenames);

        foreach ($files as $newfile) {
            foreach ($oldFiles as $oldFile) {
                if ($newfile->getFilename() === $oldFile->getFilename()) {
                    if ($oldFile->getSourcecode() === $newfile->getSourcecode()) {
                        $result['unchanged'][] = $newfile->getFilename();
                    } else {
                        $result['changed'][]      = $newfile->getFilename();
                        $result['changedfiles'][] = [$newfile, $oldFile];
                    }
                }
            }
        }

        // Special case: if there's just a single file (before and after) that has been renamed, use that for diffing.
        if (count($result['added']) === 1 && count($result['removed']) === 1 && empty($result['changed'])) {
            $result['added']        = [];
            $result['removed']      = [];
            $result['changed']      = [$files[0]->getFilename()];
            $result['changedfiles'] = [[$files[0], $oldFiles[0]]];
        }

        return $result;
    }

    protected function processClaim(
        Judging|ExternalJudgement|null $judging,
        Request $request,
        ?string &$claimWarning
    ): ?RedirectResponse {
        $user   = $this->dj->getUser();
        $action = ($request->get('claim') || $request->get('claimdiff')) ? 'claim' : 'unclaim';

        $type = ($judging instanceof ExternalJudgement) ?'shadow difference' : 'submission';

        if ($judging === null) {
            $claimWarning = sprintf('Cannot %s this %s: no valid judging found.', $type, $action);
        } elseif ($judging->getVerified()) {
            $claimWarning = sprintf('Cannot %s this %s: judging already verified.', $type, $action);
        } elseif (!$user && $action === 'claim') {
            $claimWarning = sprintf('Cannot claim this %s: no jury member specified.', $type);
        } else {
            if (!empty($judging->getJuryMember()) && $action === 'claim' &&
                $user->getUsername() !== $judging->getJuryMember() &&
                !$request->request->has('forceclaim')) {
                $claimWarning = sprintf('%s has been claimed by %s. Claim again on this page to force an update.',
                    ucfirst($type), $judging->getJuryMember());
            } else {
                $judging->setJuryMember($action === 'claim' ? $user->getUsername() : null);
                $this->em->flush();
                if ($judging instanceof ExternalJudgement) {
                    $auditLogType = 'external_judgement';
                    $auditLogId = $judging->getExtjudgementid();
                } else {
                    $auditLogType = 'judging';
                    $auditLogId = $judging->getJudgingid();
                }
                $this->dj->auditlog($auditLogType, $auditLogId, $action . 'ed');

                if ($action === 'claim') {
                    return $this->redirectToRoute('jury_submission', ['submitId' => $judging->getSubmission()->getSubmitid()]);
                } else {
                    return $this->redirectToLocalReferrer($this->router, $request,
                        $this->generateUrl('jury_submissions')
                    );
                }
            }
        }

        return null;
    }

    #[Route(path: '/{submitId<\d+>}/create-tasks', name: 'jury_submission_create_tasks')]
    public function createJudgeTasks(string $submitId): RedirectResponse
    {
        $this->dj->unblockJudgeTasksForSubmission($submitId);
        $this->addFlash('info', "Started judging for submission: $submitId");
        return $this->redirectToRoute('jury_submission', ['submitId' => $submitId]);
    }

    /**
     * @param string[] $allErrors
     */
    private function maybeGetErrors(string $type, string $expectedConfigString, string $observedConfigString, array &$allErrors): void
    {
        $expectedConfig = $this->dj->jsonDecode($expectedConfigString);
        $observedConfig = $this->dj->jsonDecode($observedConfigString);
        $errors = [];
        foreach (array_keys($expectedConfig) as $k) {
            if (!array_key_exists($k, $observedConfig)) {
                $errors[] = '- ' . preg_replace('/_/', ' ', $k) . ': missing';
            } elseif ($expectedConfig[$k] != $observedConfig[$k]) {
                if ($k === 'hash') {
                    $errors[] = '- script has changed';
                } elseif ($k === 'entry_point') {
                    // Changes to the entry point can only happen for jury submissions during initial problem upload.
                    // Silently ignore.
                } else {
                    $errors[] = '- ' . preg_replace('/_/', ' ', $k) . ': '
                        . $this->dj->jsonEncode($observedConfig[$k]) . ' → ' . $this->dj->jsonEncode($expectedConfig[$k]);
                }
            }
        }
        foreach (array_keys($observedConfig) as $k) {
            if (!array_key_exists($k, $expectedConfig)) {
                $errors[] = '- ' . preg_replace('/_/', ' ', $k) . ': unexpected';
            }
        }
        if (!empty($errors)) {
            $allErrors[] = $type . ' changes:';
            array_push($allErrors, ...$errors);
        }
    }
}
