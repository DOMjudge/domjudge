<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Controller\BaseController;
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
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;

/**
 * @Route("/jury/submissions")
 * @IsGranted("ROLE_JURY")
 */
class SubmissionController extends BaseController
{
    protected EntityManagerInterface $em;
    protected DOMJudgeService $dj;
    protected ConfigurationService $config;
    protected SubmissionService $submissionService;
    protected RouterInterface $router;

    public function __construct(
        EntityManagerInterface $em,
        DOMJudgeService $dj,
        ConfigurationService $config,
        SubmissionService $submissionService,
        RouterInterface $router
    ) {
        $this->em                = $em;
        $this->dj                = $dj;
        $this->config            = $config;
        $this->submissionService = $submissionService;
        $this->router            = $router;
    }

    /**
     * @Route("", name="jury_submissions")
     */
    public function indexAction(Request $request): Response
    {
        $viewTypes = [0 => 'newest', 1 => 'unverified', 2 => 'unjudged', 3 => 'all'];
        $view      = 0;
        if (($submissionViewCookie = $this->dj->getCookie('domjudge_submissionview')) &&
            isset($viewTypes[$submissionViewCookie])) {
            $view = $submissionViewCookie;
        }

        if ($request->query->has('view')) {
            $index = array_search($request->query->get('view'), $viewTypes);
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

        $restrictions = [];
        if ($viewTypes[$view] == 'unverified') {
            $restrictions['verified'] = 0;
        }
        if ($viewTypes[$view] == 'unjudged') {
            $restrictions['judged'] = 0;
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

        // Load preselected filters
        $filters          = $this->dj->jsonDecode((string)$this->dj->getCookie('domjudge_submissionsfilter') ?: '[]');

        $verdictsConfig = $this->dj->getDomjudgeEtcDir() . '/verdicts.php';
        $results = array_keys(include $verdictsConfig);
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
            'showExternalResult' => $this->config->get('data_source') ==
                DOMJudgeService::DATA_SOURCE_CONFIGURATION_AND_LIVE_EXTERNAL,
            'showTestcases' => count($submissions) <= $latestCount,
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
        $form = $this->createForm(SubmissionsFilterType::class, array_merge($filtersForForm, [
            "contests" => $contests,
        ]));
        $data["form"] = $form->createView();

        return $this->render('jury/submissions.html.twig', $data, $response);
    }

    /**
     * @Route("/{submitId<\d+>}", name="jury_submission")
     * @throws NonUniqueResultException
     */
    public function viewAction(Request $request, int $submitId): Response
    {
        $judgingId   = $request->query->get('jid');
        $rejudgingId = $request->query->get('rejudgingid');

        if (isset($judgingId) && isset($rejudgingId)) {
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
            ->join('s.files', 'f')
            ->leftJoin('s.external_judgements', 'ej', Join::WITH, 'ej.valid = 1')
            ->leftJoin('s.contest_problem', 'cp')
            ->select('s', 't', 'p', 'l', 'c', 'partial f.{submitfileid, filename}', 'cp', 'ej')
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
            ->orderBy('j.starttime')
            ->addOrderBy('j.judgingid')
            ->getQuery()
            ->getResult();

        /** @var Judging[] $judgings */
        $judgings    = array_map(fn($data) => $data[0], $judgingData);
        $maxRunTimes = array_map(fn($data) => $data['max_runtime'], $judgingData);

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
                    ->addSelect('jro.output_system AS output_system');
            } else {
                $queryBuilder
                    ->addSelect('TRUNCATE(tc.output, :outputDisplayLimit, :outputTruncateMessage) AS output_reference')
                    ->addSelect('TRUNCATE(jro.output_run, :outputDisplayLimit, :outputTruncateMessage) AS output_run')
                    ->addSelect('RIGHT(jro.output_run, 50) AS output_run_last_bytes')
                    ->addSelect('TRUNCATE(jro.output_diff, :outputDisplayLimit, :outputTruncateMessage) AS output_diff')
                    ->addSelect('TRUNCATE(jro.output_error, :outputDisplayLimit, :outputTruncateMessage) AS output_error')
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
            foreach ($runResults as $runResult) {
                /** @var Testcase $testcase */
                $testcase = $runResult[0];
                if (isset($judgingRunTestcaseIdsInOrder[$cnt])) {
                    if ($testcase->getTestcaseid() != $judgingRunTestcaseIdsInOrder[$cnt]['testcase_id']) {
                        $sameTestcaseIds = false;
                    }
                }
                $cnt++;
                /** @var JudgingRun $firstJudgingRun */
                $firstJudgingRun = $runResult[0]->getFirstJudgingRun();
                if ($firstJudgingRun !== null && $firstJudgingRun->getRunresult() === null) {
                    $runsOutstanding = true;
                }
                $runs[] = $runResult[0];
                unset($runResult[0]);
                if (empty($runResult['metadata'])) {
                    $runResult['cpu_time'] = $firstJudgingRun === null ? 'n/a' : $firstJudgingRun->getRuntime();
                } else {
                    $metadata = $this->dj->parseMetadata($runResult['metadata']);
                    $runResult['cpu_time'] = $metadata['cpu-time'];
                    $runResult['wall_time'] = $metadata['wall-time'];
                    $runResult['memory'] = Utils::printsize((int)$metadata['memory-bytes'], 2);
                    $runResult['exitcode'] = $metadata['exitcode'];
                    $runResult['signal'] = $metadata['signal'] ?? -1;
                    $runResult['output_limit'] = $metadata['output-truncated'];
                }
                $runResult['terminated'] = preg_match('/timelimit exceeded.*hard (wall|cpu) time/',
                                                      (string)$runResult['output_system']);
                $runResult['hostname'] = null;
                $runResult['judgehostid'] = null;
                if ($firstJudgingRun && $firstJudgingRun->getJudgeTask() && $firstJudgingRun->getJudgeTask()->getJudgehost()) {
                    $runResult['hostname'] = $firstJudgingRun->getJudgeTask()->getJudgehost()->getHostname();
                    $runResult['judgehostid'] = $firstJudgingRun->getJudgeTask()->getJudgehost()->getJudgehostid();
                }
                $runResult['is_output_run_truncated'] = preg_match(
                    '/\[output storage truncated after \d* B\]/',
                    (string)$runResult['output_run_last_bytes']
                );
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
        if ($runsOutstanding) {
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
        ];

        if ($selectedJudging === null) {
            // Automatically refresh page while we wait for judging data.
            $twigData['refresh'] = [
                'after' => 15,
                'url' => $this->generateUrl('jury_submission', ['submitId' => $submission->getSubmitid()]),
            ];
        } else {
            $contestProblem = $submission->getContestProblem();
            /** @var JudgeTask $judgeTask */
            $judgeTask = $this->em->getRepository(JudgeTask::class)->findOneBy(['jobid' => $selectedJudging->getJudgingid()]);
            if ($judgeTask !== null) {
                $errors = [];
                $this->maybeGetErrors('Compile config',
                    $this->dj->getCompileConfig($submission),
                    $judgeTask->getCompileConfig(),
                    $errors);
                $this->maybeGetErrors('Run config',
                    $this->dj->getRunConfig($contestProblem, $submission),
                    $judgeTask->getRunConfig(),
                    $errors);
                $this->maybeGetErrors('Compare config',
                    $this->dj->getCompareConfig($contestProblem),
                    $judgeTask->getCompareConfig(),
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

    /**
     * @Route("/request-full-debug/{jid}", name="request_full_debug")
     */
    public function requestFullDebug(Request $request, Judging $jid): RedirectResponse
    {
        $submission = $jid->getSubmission();
        /** @var Executable $defaultFullDebugExecutable */
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
                    ->setSubmitid($submission->getSubmitid())
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

    /**
     * @Route("/download-full-debug/{debug_package_id}", name="download_full_debug")
     */
    public function downloadFullDebug(DebugPackage $debugPackage): StreamedResponse
    {
        $name = 'debug_package.j' . $debugPackage->getJudging()->getJudgingid()
            . '.db' . $debugPackage->getDebugPackageId()
            . '.jh' . $debugPackage->getJudgehost()->getJudgehostid()
            . '.tar.gz';
        return Utils::streamAsBinaryFile(file_get_contents($debugPackage->getFilename()), $name);
    }

    /**
     * @Route("/request-output/{jid}/{jrid}", name="request_output")
     */
    public function requestOutput(Request $request, Judging $jid, JudgingRun $jrid): RedirectResponse
    {
        $submission = $jid->getSubmission();
        $testcase = $jrid->getTestcase();
        $judgeTask = new JudgeTask();
        $judgeTask
            ->setType(JudgeTaskType::DEBUG_INFO)
            ->setJudgehost($jrid->getJudgeTask()->getJudgehost())
            ->setSubmitid($submission->getSubmitid())
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

    /**
     * @Route("/by-judging-id/{jid}", name="jury_submission_by_judging")
     */
    public function viewForJudgingAction(Judging $jid): RedirectResponse
    {
        return $this->redirectToRoute('jury_submission', [
            'submitId' => $jid->getSubmission()->getSubmitid(),
            'jid' => $jid->getJudgingid(),
        ]);
    }

    /**
     * @Route("/by-external-judgement-id/{externalJudgement}", name="jury_submission_by_external_judgement")
     */
    public function viewForExternalJudgementAction(ExternalJudgement $externalJudgement): RedirectResponse
    {
        return $this->redirectToRoute('jury_submission', [
            'submitId' => $externalJudgement->getSubmission()->getSubmitid(),
        ]);
    }

    /**
     * @Route("/by-external-id/{externalId}", name="jury_submission_by_external_id")
     */
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

    /**
     * @Route("/{submission}/runs/{contest}/{run}/team-output", name="jury_submission_team_output")
     */
    public function teamOutputAction(Submission $submission, Contest $contest, JudgingRun $run): StreamedResponse
    {
        if ($run->getJudging()->getSubmission()->getSubmitid() !== $submission->getSubmitid() || $submission->getContest()->getCid() !== $contest->getCid()) {
            throw new BadRequestHttpException('Integrity problem while fetching team output.');
        }
        if ($run->getOutput() === null) {
            throw new BadRequestHttpException('No team output available (yet).');
        }

        $filename = sprintf('p%d.t%d.%s.run%d.team%d.out', $submission->getProblem()->getProbid(), $run->getTestcase()->getRank(),
                            $submission->getContestProblem()->getShortname(), $run->getRunid(),
                            $submission->getTeam()->getTeamid());

        $outputRun = $run->getOutput()->getOutputRun();
        return Utils::streamAsBinaryFile($outputRun, $filename);
    }

    /**
     * @Route("/{submission}/source", name="jury_submission_source")
     * @throws NonUniqueResultException
     */
    public function sourceAction(Request $request, Submission $submission): Response
    {
        if ($request->query->has('fetch')) {
            /** @var SubmissionFile $file */
            $file = $this->em->createQueryBuilder()
                ->from(SubmissionFile::class, 'file')
                ->select('file')
                ->andWhere('file.ranknumber = :ranknumber')
                ->andWhere('file.submission = :submission')
                ->setParameter('ranknumber', $request->query->get('fetch'))
                ->setParameter('submission', $submission)
                ->getQuery()
                ->getOneOrNullResult();
            if (!$file) {
                throw new NotFoundHttpException(sprintf('No submission file found with rank %s',
                                                        $request->query->get('fetch')));
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
        ]);
    }

    /**
     * @Route("/{submission}/edit-source", name="jury_submission_edit_source")
     */
    public function editSourceAction(Request $request, Submission $submission): Response
    {
        if (!$this->dj->getUser()->getTeam() || !$this->dj->checkrole('team')) {
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
                'class' => 'App\Entity\Problem',
                'choice_label' => 'name',
                'query_builder' => function (EntityRepository $er) use ($submission) {
                    return $er->createQueryBuilder('p')
                        ->join('p.contest_problems', 'cp')
                        ->andWhere('cp.allowSubmit = 1')
                        ->andWhere('cp.contest = :contest')
                        ->setParameter('contest', $submission->getContest())
                        ->orderBy('p.name');
                },
            ])
            ->add('language', EntityType::class, [
                'class' => 'App\Entity\Language',
                'choice_label' => 'name',
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('lang')
                        ->andWhere('lang.allowSubmit = 1')
                        ->orderBy('lang.name');
                }
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
                $this->getUser()->getUsername(),
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
            'form' => $form->createView(),
            'selected' => $request->query->get('ranknumber'),
        ]);
    }

    /**
     * @Route("/{judgingId<\d+>}/request-remaining", name="jury_submission_request_remaining", methods={"POST"})
     * @throws DBALException
     */
    public function requestRemainingRuns(Request $request, int $judgingId): RedirectResponse
    {
        /** @var Judging $judging */
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
     * @Route("/{submitId<\d+>}/update-status", name="jury_submission_update_status", methods={"POST"})
     * @IsGranted("ROLE_ADMIN")
     * @throws DBALException
     */
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
     * @Route("/{judgingId<\d+>}/verify", name="jury_judging_verify", methods={"POST"})
     * @throws DBALException
     * @throws NoResultException
     * @throws NonUniqueResultException
     * @throws ORMException
     */
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
                ->setJuryMember($verified ? $this->dj->getUser()->getUsername() : null)
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


    /**
     * @Route("/shadow-difference/{extjudgementid<\d+>}/verify", name="jury_shadow_difference_verify", methods={"POST"})
     */
    public function verifyShadowDifferenceAction(
        EventLogService $eventLogService,
        Request $request,
        int $extjudgementid
    ): RedirectResponse {
        /** @var ExternalJudgement $judgement */
        $judgement  = $this->em->getRepository(ExternalJudgement::class)->find($extjudgementid);
        $this->em->wrapInTransaction(function () use ($eventLogService, $request, $judgement) {
            $verified = $request->request->getBoolean('verified');
            $comment  = $request->request->get('comment');
            $judgement
                ->setVerified($verified)
                ->setJuryMember($verified ? $this->dj->getUser()->getUsername() : null)
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

    /**
     * @param Judging|ExternalJudgement|null $judging
     */
    protected function processClaim($judging, Request $request, ?string &$claimWarning) : ?RedirectResponse
    {
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

    /**
     * @Route("/{submitId<\d+>}/create-tasks", name="jury_submission_create_tasks")
     */
    public function createJudgeTasks(string $submitId): RedirectResponse
    {
        $this->dj->unblockJudgeTasksForSubmission($submitId);
        $this->addFlash('info', "Started judging for submission: $submitId");
        return $this->redirectToRoute('jury_submission', ['submitId' => $submitId]);
    }

    private function maybeGetErrors(string $type, string $expectedConfigString, string $observedConfigString, array &$allErrors)
    {
        $expectedConfig = $this->dj->jsonDecode($expectedConfigString);
        $observedConfig = $this->dj->jsonDecode($observedConfigString);
        $errors = [];
        foreach (array_keys($expectedConfig) as $k) {
            if ($expectedConfig[$k] != $observedConfig[$k]) {
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
        if (!empty($errors)) {
            $allErrors[] = $type . ' changes:';
            array_push($allErrors, ...$errors);
        }
    }
}
