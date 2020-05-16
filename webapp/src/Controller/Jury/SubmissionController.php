<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Controller\BaseController;
use App\Entity\Contest;
use App\Entity\ExternalJudgement;
use App\Entity\Judgehost;
use App\Entity\Judging;
use App\Entity\JudgingRun;
use App\Entity\Language;
use App\Entity\Problem;
use App\Entity\Submission;
use App\Entity\SubmissionFile;
use App\Entity\Team;
use App\Entity\Testcase;
use App\Service\BalloonService;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Service\ScoreboardService;
use App\Service\SubmissionService;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
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
     * @var SubmissionService
     */
    protected $submissionService;

    /**
     * @var RouterInterface
     */
    protected $router;

    /**
     * SubmissionController constructor.
     *
     * @param EntityManagerInterface $em
     * @param DOMJudgeService        $dj
     * @param ConfigurationService   $config
     * @param SubmissionService      $submissionService
     * @param RouterInterface        $router
     */
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
    public function indexAction(Request $request)
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

        $limit = $viewTypes[$view] == 'newest' ? 50 : 0;

        /** @var Submission[] $submissions */
        list($submissions, $submissionCounts) =
            $this->submissionService->getSubmissionList($contests, $restrictions, $limit);

        // Load preselected filters
        $filters          = $this->dj->jsonDecode((string)$this->dj->getCookie('domjudge_submissionsfilter') ?: '[]');
        $filteredProblems = $filteredLanguages = $filteredTeams = [];
        if (isset($filters['problem-id'])) {
            /** @var Problem[] $filteredProblems */
            $filteredProblems = $this->em->createQueryBuilder()
                ->from(Problem::class, 'p')
                ->select('p')
                ->where('p.probid IN (:problemIds)')
                ->setParameter(':problemIds', $filters['problem-id'])
                ->getQuery()
                ->getResult();
        }
        if (isset($filters['language-id'])) {
            /** @var Language[] $filteredLanguages */
            $filteredLanguages = $this->em->createQueryBuilder()
                ->from(Language::class, 'lang')
                ->select('lang')
                ->where('lang.langid IN (:langIds)')
                ->setParameter(':langIds', $filters['language-id'])
                ->getQuery()
                ->getResult();
        }
        if (isset($filters['team-id'])) {
            /** @var Team[] $filteredTeams */
            $filteredTeams = $this->em->createQueryBuilder()
                ->from(Team::class, 't')
                ->select('t')
                ->where('t.teamid IN (:teamIds)')
                ->setParameter(':teamIds', $filters['team-id'])
                ->getQuery()
                ->getResult();
        }

        $data = [
            'refresh' => $refresh,
            'viewTypes' => $viewTypes,
            'view' => $view,
            'submissions' => $submissions,
            'submissionCounts' => $submissionCounts,
            'showContest' => count($contests) > 1,
            'hasFilters' => !empty($filters),
            'filteredProblems' => $filteredProblems,
            'filteredLanguages' => $filteredLanguages,
            'filteredTeams' => $filteredTeams,
            'showExternalResult' => $this->config->get('data_source') ==
                DOMJudgeService::DATA_SOURCE_CONFIGURATION_AND_LIVE_EXTERNAL,
        ];

        // For ajax requests, only return the submission list partial
        if ($request->isXmlHttpRequest()) {
            $data['showTestcases'] = true;
            return $this->render('jury/partials/submission_list.html.twig', $data);
        }

        return $this->render('jury/submissions.html.twig', $data, $response);
    }

    private function parseMetadata($raw_metadata) {
        // TODO: reduce duplication with judgedaemon code
        $contents = explode("\n", $raw_metadata);
        $res = [];
        foreach($contents as $line) {
            if (strpos($line, ":") !== false) {
                list($key, $value) = explode(":", $line, 2);
                $res[$key] = trim($value);
            }
        }

        return $res;
    }

    /**
     * @Route("/{submitId<\d+>}", name="jury_submission")
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Exception
     */
    public function viewAction(Request $request, int $submitId)
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
                                'submitid' => $submitId,
                                'rejudgingid' => $rejudgingId
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
            ->leftJoin('s.external_judgements', 'ej', Join::WITH, 'ej.valid = 1')
            ->leftJoin('s.contest_problem', 'cp')
            ->select('s', 't', 'p', 'l', 'c', 'cp', 'ej')
            ->andWhere('s.submitid = :submitid')
            ->setParameter(':submitid', $submitId)
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
            ->setParameter(':contest', $submission->getContest())
            ->setParameter(':submission', $submission)
            ->groupBy('j.judgingid')
            ->orderBy('j.starttime')
            ->addOrderBy('j.judgingid')
            ->getQuery()
            ->getResult();

        /** @var Judging[] $judgings */
        $judgings    = array_map(function ($data) {
            return $data[0];
        }, $judgingData);
        $maxRunTimes = array_map(function ($data) {
            return $data['max_runtime'];
        }, $judgingData);

        $selectedJudging = null;
        // Find the selected judging
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

        $unjudgableReasons = [];
        if ($selectedJudging === null) {
            // Determine if this submission is unjudgable

            // First, check if there is an active judgehost that can judge this submission.
            /** @var Judgehost[] $judgehosts */
            $judgehosts  = $this->em->createQueryBuilder()
                ->from(Judgehost::class, 'j')
                ->leftJoin('j.restriction', 'r')
                ->select('j', 'r')
                ->andWhere('j.active = 1')
                ->getQuery()
                ->getResult();
            $canBeJudged = false;
            foreach ($judgehosts as $judgehost) {
                if (!$judgehost->getRestriction()) {
                    $canBeJudged = true;
                    break;
                }

                $queryBuilder = $this->em->createQueryBuilder()
                    ->from(Submission::class, 's')
                    ->select('s')
                    ->join('s.language', 'lang')
                    ->join('s.contest_problem', 'cp')
                    ->andWhere('s.submitid = :submitid')
                    ->andWhere('s.judgehost IS NULL')
                    ->andWhere('lang.allowJudge = 1')
                    ->andWhere('cp.allowJudge = 1')
                    ->andWhere('s.valid = 1')
                    ->setParameter(':submitid', $submission->getSubmitid())
                    ->setMaxResults(1);

                $restrictions = $judgehost->getRestriction()->getRestrictions();
                if (isset($restrictions['contest'])) {
                    $queryBuilder
                        ->andWhere('s.cid IN (:contests)')
                        ->setParameter(':contests', $restrictions['contest']);
                }
                if (isset($restrictions['problem'])) {
                    $queryBuilder
                        ->leftJoin('s.problem', 'p')
                        ->andWhere('p.probid IN (:problems)')
                        ->setParameter(':problems', $restrictions['problem']);
                }
                if (isset($restrictions['language'])) {
                    $queryBuilder
                        ->andWhere('s.langid IN (:languages)')
                        ->setParameter(':languages', $restrictions['language']);
                }

                if ($queryBuilder->getQuery()->getOneOrNullResult()) {
                    $canBeJudged = true;
                }
            }

            if (!$canBeJudged) {
                $unjudgableReasons[] = 'No active judgehost can judge this submission. Edit judgehost restrictions!';
            }

            if (!$submission->getLanguage()->getAllowJudge()) {
                $unjudgableReasons[] = 'Submission language is currently not allowed to be judged!';
            }

            if (!$submission->getContestProblem()->getAllowJudge()) {
                $unjudgableReasons[] = 'Problem is currently not allowed to be judged!';
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
                ->setParameter(':judging', $externalJudgement)
                ->setParameter(':problem', $submission->getProblem())
                ->orderBy('t.rank');

            $externalRunResults = $queryBuilder
                ->getQuery()
                ->getResult();

            foreach ($externalRunResults as $externalRunResult) {
                $externalRuns[] = $externalRunResult;
            }
        }

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
                ->setParameter(':judging', $selectedJudging)
                ->setParameter(':problem', $submission->getProblem())
                ->orderBy('t.rank');
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
                    ->addSelect('TRUNCATE(jro.output_diff, :outputDisplayLimit, :outputTruncateMessage) AS output_diff')
                    ->addSelect('TRUNCATE(jro.output_error, :outputDisplayLimit, :outputTruncateMessage) AS output_error')
                    ->addSelect('TRUNCATE(jro.output_system, :outputDisplayLimit, :outputTruncateMessage) AS output_system')
                    ->setParameter(':outputDisplayLimit', $outputDisplayLimit)
                    ->setParameter(':outputTruncateMessage', $outputTruncateMessage);
            }

            $runResults = $queryBuilder
                ->getQuery()
                ->getResult();

            $judgingRunTestcaseIdsInOrder = $this->em->createQueryBuilder()
                ->from(JudgingRun::class, 'jr')
                ->select('jr.testcaseid')
                ->andWhere('jr.judging = :judging')
                ->setParameter(':judging', $selectedJudging)
                ->orderBy('jr.endtime')
                ->getQuery()
                ->getScalarResult();

            $cnt = 0;
            foreach ($runResults as $runResult) {
                /** @var Testcase $testcase */
                $testcase = $runResult[0];
                if (isset($judgingRunTestcaseIdsInOrder[$cnt])) {
                    if ($testcase->getTestcaseid() != $judgingRunTestcaseIdsInOrder[$cnt]['testcaseid']) {
                        $sameTestcaseIds = false;
                    }
                }
                $cnt++;
                $firstJudgingRun = $runResult[0]->getFirstJudgingRun();
                $runs[] = $runResult[0];
                unset($runResult[0]);
                if (empty($runResult['metadata'])) {
                    $runResult['cpu_time'] = $firstJudgingRun === NULL ? 'n/a' : $firstJudgingRun->getRuntime();
                } else {
                    $metadata = $this->parseMetadata($runResult['metadata']);
                    $runResult['cpu_time'] = $metadata['cpu-time'];
                    $runResult['wall_time'] = $metadata['wall-time'];
                    $runResult['memory'] = Utils::printsize((int)$metadata['memory-bytes'], 2);
                    $runResult['exitcode'] = $metadata['exitcode'];
                    $runResult['signal'] = isset($metadata['signal']) ? $metadata['signal'] : -1;
                }
                $runResult['terminated'] = preg_match('/timelimit exceeded.*hard (wall|cpu) time/',
                                                      (string)$runResult['output_system']);
                $runsOutput[]            = $runResult;
            }
        }

        if ($submission->getOrigsubmitid()) {
            $lastSubmission = $this->em->getRepository(Submission::class)->find($submission->getOrigsubmitid());
        } else {
            /** @var Submission|null $lastSubmission */
            $lastSubmission = $this->em->createQueryBuilder()
                ->from(Submission::class, 's')
                ->select('s')
                ->andWhere('s.team = :team')
                ->andWhere('s.problem = :problem')
                ->andWhere('s.submittime < :submittime')
                ->setParameter(':team', $submission->getTeam())
                ->setParameter(':problem', $submission->getProblem())
                ->setParameter(':submittime', $submission->getSubmittime())
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
                ->setParameter(':submission', $lastSubmission)
                ->orderBy('j.judgingid', 'DESC')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if ($lastJudging !== null) {
                // Clear the testcases, otherwise Doctrine will use the previous data
                $this->em->clear(Testcase::class);
                $lastRuns = $this->em->createQueryBuilder()
                    ->from(Testcase::class, 't')
                    ->leftJoin('t.judging_runs', 'jr', Join::WITH, 'jr.judging = :judging')
                    ->select('t', 'jr')
                    ->andWhere('t.problem = :problem')
                    ->setParameter(':judging', $lastJudging)
                    ->setParameter(':problem', $submission->getProblem())
                    ->orderBy('t.rank')
                    ->getQuery()
                    ->getResult();
            }
        }

        $twigData = [
            'submission' => $submission,
            'lastSubmission' => $lastSubmission,
            'judgings' => $judgings,
            'maxRunTimes' => $maxRunTimes,
            'selectedJudging' => $selectedJudging,
            'lastJudging' => $lastJudging,
            'runs' => $runs,
            'sameTestcaseIds' => $sameTestcaseIds,
            'externalRuns' => $externalRuns,
            'runsOutput' => $runsOutput,
            'lastRuns' => $lastRuns,
            'unjudgableReasons' => $unjudgableReasons,
            'verificationRequired' => (bool)$this->config->get('verification_required'),
            'claimWarning' => $claimWarning,
            'combinedRunCompare' => $submission->getProblem()->getCombinedRunCompare(),
        ];

        if ($selectedJudging === null) {
            // Automatically refresh page while we wait for judging data.
            $twigData['refresh'] = [
                'after' => 15,
                'url' => $this->generateUrl('jury_submission', ['submitId' => $submission->getSubmitid()]),
            ];
        }

        return $this->render('jury/submission.html.twig', $twigData);
    }

    /**
     * @Route("/by-judging-id/{jid}", name="jury_submission_by_judging")
     */
    public function viewForJudgingAction(Judging $jid)
    {
        return $this->redirectToRoute('jury_submission', [
            'submitId' => $jid->getSubmitid(),
            'jid' => $jid->getJudgingid(),
        ]);
    }

    /**
     * @Route("/by-external-judgement-id/{externalJudgement}", name="jury_submission_by_external_judgement")
     */
    public function viewForExternalJudgementAction(ExternalJudgement $externalJudgement)
    {
        return $this->redirectToRoute('jury_submission', [
            'submitId' => $externalJudgement->getSubmitid(),
        ]);
    }

    /**
     * @Route("/by-external-id/{externalId}", name="jury_submission_by_external_id")
     */
    public function viewForExternalIdAction(string $externalId)
    {
        if (!$this->dj->getCurrentContest()) {
            throw new BadRequestHttpException("Cannot determine submission from external ID without selecting a contest.");
        }

        $submission = $this->em->getRepository(Submission::class)
            ->findOneBy([
                            'cid' => $this->dj->getCurrentContest()->getCid(),
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
     * @param Submission $submission
     * @param Contest    $contest
     * @param JudgingRun $run
     */
    public function teamOutputAction(Submission $submission, Contest $contest, JudgingRun $run)
    {
        if ($run->getJudging()->getSubmitid() !== $submission->getSubmitid() || $submission->getCid() !== $contest->getCid()) {
            throw new BadRequestHttpException('Problem while fetching team output');
        }

        $filename = sprintf('p%d.t%d.%s.run%d.team%d.out', $submission->getProbid(), $run->getTestcase()->getRank(),
                            $submission->getContestProblem()->getShortname(), $run->getRunid(),
                            $submission->getTeamid());

        $outputRun = $run->getOutput()->getOutputRun();
        return Utils::streamAsBinaryFile($outputRun, $filename);
    }

    /**
     * @Route("/{submission}/source", name="jury_submission_source")
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function sourceAction(Request $request, Submission $submission)
    {
        if ($request->query->has('fetch')) {
            /** @var SubmissionFile $file */
            $file = $this->em->createQueryBuilder()
                ->from(SubmissionFile::class, 'file')
                ->select('file')
                ->andWhere('file.rank = :rank')
                ->andWhere('file.submission = :submission')
                ->setParameter(':rank', $request->query->get('fetch'))
                ->setParameter(':submission', $submission)
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
            ->setParameter(':submission', $submission)
            ->orderBy('file.rank')
            ->getQuery()
            ->getResult();

        $originalSubmission = $originalFiles = null;

        if ($submission->getOrigsubmitid()) {
            /** @var Submission $originalSubmission */
            $originalSubmission = $this->em->getRepository(Submission::class)->find($submission->getOrigsubmitid());

            /** @var SubmissionFile[] $files */
            $originalFiles = $this->em->createQueryBuilder()
                ->from(SubmissionFile::class, 'file')
                ->select('file')
                ->andWhere('file.submission = :submission')
                ->setParameter(':submission', $originalSubmission)
                ->orderBy('file.rank')
                ->getQuery()
                ->getResult();

            /** @var Submission $oldSubmission */
            $oldSubmission = $this->em->createQueryBuilder()
                ->from(Submission::class, 's')
                ->select('s')
                ->andWhere('s.probid = :probid')
                ->andWhere('s.langid = :langid')
                ->andWhere('s.submittime < :submittime')
                ->andWhere('s.origsubmitid = :origsubmitid')
                ->setParameter(':probid', $submission->getProbid())
                ->setParameter(':langid', $submission->getLangid())
                ->setParameter(':submittime', $submission->getSubmittime())
                ->setParameter(':origsubmitid', $submission->getOrigsubmitid())
                ->orderBy('s.submittime', 'DESC')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
        } else {
            $oldSubmission = $this->em->createQueryBuilder()
                ->from(Submission::class, 's')
                ->select('s')
                ->andWhere('s.teamid = :teamid')
                ->andWhere('s.probid = :probid')
                ->andWhere('s.langid = :langid')
                ->andWhere('s.submittime < :submittime')
                ->setParameter(':teamid', $submission->getTeamid())
                ->setParameter(':probid', $submission->getProbid())
                ->setParameter(':langid', $submission->getLangid())
                ->setParameter(':submittime', $submission->getSubmittime())
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
            ->setParameter(':submission', $oldSubmission)
            ->orderBy('file.rank')
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
     * @param Request    $request
     * @param Submission $submission
     * @return RedirectResponse|Response
     * @throws \Exception
     */
    public function editSourceAction(Request $request, Submission $submission)
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
            ->setParameter(':submission', $submission)
            ->orderBy('file.rank')
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
                        ->setParameter(':contest', $submission->getContest())
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
                $submittedData['problem'],
                $submission->getContest(),
                $language,
                $filesToSubmit,
                $submission->getOriginalSubmission() ?? $submission,
                $entryPoint,
                null,
                null,
                $message
            );

            foreach ($filesToSubmit as $file) {
                unlink($file->getRealPath());
            }

            if (!$submission) {
                $this->addFlash('danger', $message);
                return $this->redirectToRoute('jury_submission', ['submitId' => $submission->getSubmitid()]);
            }

            return $this->redirectToRoute('jury_submission', ['submitId' => $submittedSubmission->getSubmitid()]);
        }

        return $this->render('jury/submission_edit_source.html.twig', [
            'submission' => $submission,
            'files' => $files,
            'form' => $form->createView(),
            'selected' => $request->query->get('rank'),
        ]);
    }

    /**
     * @Route("/{submitId<\d+>}/update-status", name="jury_submission_update_status", methods={"POST"})
     * @IsGranted("ROLE_ADMIN")
     * @param EventLogService   $eventLogService
     * @param ScoreboardService $scoreboardService
     * @param Request           $request
     * @param int               $submitId
     * @return RedirectResponse
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Exception
     */
    public function updateStatusAction(
        EventLogService $eventLogService,
        ScoreboardService $scoreboardService,
        Request $request,
        int $submitId
    ) {
        $submission = $this->em->getRepository(Submission::class)->find($submitId);
        $valid      = $request->request->getBoolean('valid');
        $submission->setValid($valid);
        $this->em->flush();

        // KLUDGE: We can't log an "undelete", so we re-"create".
        // FIXME: We should also delete/recreate any dependent judging(runs).
        $eventLogService->log('submission', $submission->getSubmitid(), ($valid ? 'create' : 'delete'),
                              $submission->getCid(), null, null, $valid);
        $this->dj->auditlog('submission', $submission->getSubmitid(),
                                         'marked ' . ($valid ? 'valid' : 'invalid'));
        $contest = $this->em->getRepository(Contest::class)->find($submission->getCid());
        $team    = $this->em->getRepository(Team::class)->find($submission->getTeamid());
        $problem = $this->em->getRepository(Problem::class)->find($submission->getProbid());
        $scoreboardService->calculateScoreRow($contest, $team, $problem);

        return $this->redirectToRoute('jury_submission', ['submitId' => $submission->getSubmitid()]);
    }

    /**
     * @Route("/{judgingId<\d+>}/verify", name="jury_judging_verify", methods={"POST"})
     * @param EventLogService   $eventLogService
     * @param ScoreboardService $scoreboardService
     * @param BalloonService    $balloonService
     * @param Request           $request
     * @param int               $judgingId
     * @return RedirectResponse
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     */
    public function verifyAction(
        EventLogService $eventLogService,
        ScoreboardService $scoreboardService,
        BalloonService $balloonService,
        Request $request,
        int $judgingId
    ) {
        $this->em->transactional(function () use ($eventLogService, $request, $judgingId) {
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
                // in the REST API API/JudgehostController::addJudgingRunAction
                $eventLogService->log('judging', $judging->getJudgingid(), 'update', $judging->getCid());
            }
        });

        if ((bool)$this->config->get('verification_required')) {
            $this->em->clear();
            /** @var Judging $judging */
            $judging = $this->em->getRepository(Judging::class)->find($judgingId);
            // We need to update the score for all teams for this problem, since the first to solve might now have changed
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
                    ->setParameter(':cid', $judging->getContest()->getCid());
            }
            /** @var Team[] $teams */
            $teams = $teamsQueryBuilder->getQuery()->getResult();
            foreach ($teams as $team) {
                $scoreboardService->calculateScoreRow($judging->getContest(), $team, $judging->getSubmission()->getProblem());
            }
            $balloonService->updateBalloons($judging->getContest(), $judging->getSubmission(), $judging);
        }

        // Redirect to referrer page after verification or back to submission page when unverifying.
        if ($request->request->getBoolean('verified')) {
            $redirect = $request->request->get('redirect', $this->generateUrl('jury_submissions'));
        } else {
            $redirect = $this->generateUrl('jury_submission_by_judging', ['jid' => $judgingId]);
        }

        return $this->redirect($redirect);
    }


    /**
     * @Route("/shadow-difference/{extjudgementid<\d+>}/verify", name="jury_shadow_difference_verify", methods={"POST"})
     * @param EventLogService $eventLogService
     * @param Request         $request
     * @param int             $extjudgementid
     *
     * @return RedirectResponse
     */
    public function verifyShadowDifferenceAction(
        EventLogService $eventLogService,
        Request $request,
        int $extjudgementid
    ) {
        /** @var ExternalJudgement $judgement */
        $judgement  = $this->em->getRepository(ExternalJudgement::class)->find($extjudgementid);
        $this->em->transactional(function () use ($eventLogService, $request, $judgement) {
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

        // Redirect to referrer page after verification or back to submission page when unverifying.
        if ($request->request->getBoolean('verified')) {
            $redirect = $request->request->get('redirect', $this->generateUrl('jury_shadow_differences'));
        } else {
            $redirect = $this->generateUrl('jury_submission_by_external_judgement', ['externalJudgement' => $extjudgementid]);
        }

        return $this->redirect($redirect);
    }

    /**
     * @param SubmissionFile[] $files
     * @param SubmissionFile[] $oldFiles
     * @return array
     */
    protected function determineFileChanged(array $files, array $oldFiles)
    {
        $result = [
            'added' => [],
            'removed' => [],
            'changed' => [],
            'changedfiles' => [], // These will be shown, so we will add pairs of files here
            'unchanged' => [],
        ];

        $newFilenames = [];
        $oldFilenames = [];
        foreach ($files as $newfile) {
            $oldFilenames = [];
            foreach ($oldFiles as $oldFile) {
                if ($newfile->getFilename() === $oldFile->getFilename()) {
                    if ($oldFile->getSourcecode() === $newfile->getSourcecode()) {
                        $result['unchanged'][] = $newfile->getFilename();
                    } else {
                        $result['changed'][]      = $newfile->getFilename();
                        $result['changedfiles'][] = [$newfile, $oldFile];
                    }
                }
                $oldFilenames[] = $oldFile->getFilename();
            }
            $newFilenames[] = $newfile->getFilename();
        }

        $result['added']   = array_diff($newFilenames, $oldFilenames);
        $result['removed'] = array_diff($oldFilenames, $newFilenames);

        // Special case: if we have exactly one file now and before but the filename is different, use that for diffing
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
     * @param Request                        $request
     * @param string                         $claimWarning
     *
     * @return RedirectResponse|null
     */
    protected function processClaim($judging, Request $request, ?string &$claimWarning)
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
                    return $this->redirectToRoute('jury_submission', ['submitId' => $judging->getSubmitid()]);
                } else {
                    return $this->redirectToRoute('jury_submissions');
                }
            }
        }

        return null;
    }
}
