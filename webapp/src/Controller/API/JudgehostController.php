<?php declare(strict_types=1);

namespace App\Controller\API;

use App\Entity\Contest;
use App\Entity\Executable;
use App\Entity\InternalError;
use App\Entity\Judgehost;
use App\Entity\Judging;
use App\Entity\JudgingRun;
use App\Entity\JudgingRunOutput;
use App\Entity\Rejudging;
use App\Entity\Submission;
use App\Entity\Testcase;
use App\Entity\User;
use App\Service\BalloonService;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Service\RejudgingService;
use App\Service\ScoreboardService;
use App\Service\SubmissionService;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\Expr\Join;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\Model;
use Psr\Log\LoggerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @Rest\Route("/api/v4/judgehosts", defaults={ "_format" = "json" })
 * @Rest\Prefix("/api/judgehosts")
 * @Rest\NamePrefix("judgehost_")
 * @SWG\Tag(name="Judgehosts")
 */
class JudgehostController extends AbstractFOSRestController
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
     * @var EventLogService
     */
    protected $eventLogService;

    /**
     * @var ScoreboardService
     */
    protected $scoreboardService;

    /**
     * @var SubmissionService
     */
    protected $submissionService;

    /**
     * @var BalloonService
     */
    protected $balloonService;

    /**
     * @var RejudgingService
     */
    protected $rejudgingService;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * JudgehostController constructor.
     *
     * @param EntityManagerInterface $em
     * @param DOMJudgeService        $dj
     * @param ConfigurationService   $config
     * @param EventLogService        $eventLogService
     * @param ScoreboardService      $scoreboardService
     * @param SubmissionService      $submissionService
     * @param BalloonService         $balloonService
     * @param LoggerInterface        $logger
     */
    public function __construct(
        EntityManagerInterface $em,
        DOMJudgeService $dj,
        ConfigurationService $config,
        EventLogService $eventLogService,
        ScoreboardService $scoreboardService,
        SubmissionService $submissionService,
        BalloonService $balloonService,
        RejudgingService $rejudgingService,
        LoggerInterface $logger
    ) {
        $this->em                = $em;
        $this->dj                = $dj;
        $this->config            = $config;
        $this->eventLogService   = $eventLogService;
        $this->scoreboardService = $scoreboardService;
        $this->submissionService = $submissionService;
        $this->balloonService    = $balloonService;
        $this->rejudgingService  = $rejudgingService;
        $this->logger            = $logger;
    }

    /**
     * Get judgehosts
     * @Rest\Get("")
     * @IsGranted("ROLE_JURY")
     * @SWG\Response(
     *     response="200",
     *     description="The judgehosts",
     *     @SWG\Schema(type="array", @SWG\Items(ref=@Model(type=Judgehost::class)))
     * )
     * @SWG\Parameter(
     *     name="hostname",
     *     in="query",
     *     type="string",
     *     description="Only show the judgehost with the given hostname"
     * )
     * @param Request $request
     * @return array
     */
    public function getJudgehostsAction(Request $request)
    {
        $queryBuilder = $this->em->createQueryBuilder()
            ->from(Judgehost::class, 'j')
            ->select('j');

        if ($request->query->has('hostname')) {
            $queryBuilder
                ->andWhere('j.hostname = :hostname')
                ->setParameter(':hostname', $request->query->get('hostname'));
        }

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * Add a new judgehost to the list of judgehosts.
     * Also restarts (and returns) unfinished judgings.
     * @Rest\Post("")
     * @IsGranted("ROLE_JUDGEHOST")
     * @SWG\Response(
     *     response="200",
     *     description="The returned unfinished judgings",
     *     @SWG\Schema(
     *         type="array",
     *         @SWG\Items(
     *             type="object",
     *             properties={
     *                 @SWG\Property(property="judgingid", type="integer"),
     *                 @SWG\Property(property="submitid", type="integer"),
     *                 @SWG\Property(property="cid", type="integer")
     *             }
     *         )
     *     )
     * )
     * @param Request $request
     * @return array
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function createJudgehostAction(Request $request)
    {
        if (!$request->request->has('hostname')) {
            throw new BadRequestHttpException('Argument \'hostname\' is mandatory');
        }

        $hostname = $request->request->get('hostname');

        /** @var Judgehost|null $judgehost */
        $judgehost = $this->em->createQueryBuilder()
            ->from(Judgehost::class, 'j')
            ->select('j')
            ->andWhere('j.hostname = :hostname')
            ->setParameter(':hostname', $hostname)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$judgehost) {
            $judgehost = new Judgehost();
            $judgehost->setHostname($hostname);
            $this->em->persist($judgehost);
            $this->em->flush();
        }

        // If there are any unfinished judgings in the queue in my name, they will not be finished.
        // Give them back.
        /** @var Judging[] $judgings */
        $judgings = $this->em->createQueryBuilder()
            ->from(Judging::class, 'j')
            ->innerJoin('j.judgehost', 'jh')
            ->leftJoin('j.rejudging', 'r')
            ->select('j')
            ->andWhere('jh.hostname = :hostname')
            ->andWhere('j.endtime IS NULL')
            ->andWhere('j.valid = 1 OR r.valid = 1')
            ->setParameter(':hostname', $hostname)
            ->getQuery()
            ->getResult();

        foreach ($judgings as $judging) {
            $this->giveBackJudging($judging->getJudgingid());
        }

        return array_map(function (Judging $judging) {
            return [
                'judgingid' => $judging->getJudgingid(),
                'submitid' => $judging->getSubmitid(),
                'cid' => $judging->getContest()->getApiId($this->eventLogService),
            ];
        }, $judgings);
    }

    /**
     * Update the configuration of the given judgehost
     * @Rest\Put("/{hostname}")
     * @IsGranted("ROLE_JUDGEHOST")
     * @SWG\Response(
     *     response="200",
     *     description="The modified judgehost",
     *     @SWG\Schema(type="array", @SWG\Items(ref=@Model(type=Judgehost::class)))
     * )
     * @SWG\Parameter(
     *     name="hostname",
     *     in="path",
     *     type="string",
     *     description="The hostname of the judgehost to update"
     * )
     * @SWG\Parameter(
     *     name="active",
     *     in="formData",
     *     type="boolean",
     *     description="The new active state of the judgehost"
     * )
     * @param Request $request
     * @param string  $hostname
     * @return array
     */
    public function updateJudgeHostAction(Request $request, string $hostname)
    {
        if (!$request->request->has('active')) {
            throw new BadRequestHttpException('Argument \'active\' is mandatory');
        }

        $judgehost = $this->em->getRepository(Judgehost::class)->find($hostname);
        if ($judgehost) {
            $judgehost->setActive($request->request->getBoolean('active'));
            $this->em->flush();
        }

        return [$judgehost];
    }

    /**
     * Get the next judging for the given judgehost
     * @Rest\Post("/next-judging/{hostname}")
     * @IsGranted("ROLE_JUDGEHOST")
     * @SWG\Response(
     *     response="200",
     *     description="The next judging to judge",
     *     @SWG\Schema(ref="#/definitions/NextJudging")
     * )
     * @SWG\Parameter(
     *     name="hostname",
     *     in="path",
     *     type="string",
     *     description="The hostname of the judgehost to get the next judging for"
     * )
     * @param string $hostname
     * @return array|string
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Exception
     */
    public function getNextJudgingAction(string $hostname)
    {
        /** @var Judgehost $judgehost */
        $judgehost = $this->em->getRepository(Judgehost::class)->find($hostname);
        if (!$judgehost) {
            return '';
        }

        // Update last seen of judgehost
        $judgehost->setPolltime(Utils::now());
        $this->em->flush();

        // If this judgehost is not active, there's nothing to do
        if (!$judgehost->getActive()) {
            return '';
        }

        $restrictJudgingOnSameJudgehost = false;
        if ($judgehost->getRestriction()) {
            $restrictions = $judgehost->getRestriction()->getRestrictions();
            if (isset($restrictions['rejudge_own']) && (bool)$restrictions['rejudge_own'] == false) {
                $restrictJudgingOnSameJudgehost = true;
            }
        }

        /** @var Submission[] $submissions */
        $submissions = $this->getSubmissionsToJudge($judgehost, $restrictJudgingOnSameJudgehost);
        if (empty($submissions)) {
            // Relax the restriction to judge on a different judgehost to not block judging.
            $submissions = $this->getSubmissionsToJudge($judgehost, false);
        }
        $numUpdated = 0;

        // Pick first submission
        foreach ($submissions as $submission) {
            // update exactly one submission with our judgehost name
            // Note: this might still return 0 if another judgehost beat us to it
            // We do this directly as an SQL query so we can get the number of affected rows
            $numUpdated = $this->em->getConnection()->executeUpdate(
                'UPDATE submission SET judgehost = :judgehost WHERE submitid = :submitid AND judgehost IS NULL',
                [
                    ':judgehost' => $judgehost->getHostname(),
                    ':submitid' => $submission->getSubmitid()
                ]
            );
            if ($numUpdated == 1) {
                break;
            }
        }

        // No submission can be claimed
        if (empty($submission) || $numUpdated == 0) {
            return '';
        }

        // Update judging last started for team
        $submission->getTeam()->setJudgingLastStarted(Utils::now());
        $this->em->flush();

        // Build up result
        $maxRunTime = $submission->getProblem()->getTimelimit() * $submission->getLanguage()->getTimeFactor();
        $result     = [
            'submitid' => $submission->getSubmitid(),
            'cid' => $submission->getContest()->getApiId($this->eventLogService),
            'teamid' => $submission->getTeamid(),
            'probid' => $submission->getProbid(),
            'langid' => $submission->getLangid(),
            'language_extensions' => $submission->getLanguage()->getExtensions(),
            'filter_compiler_files' => $submission->getLanguage()->getFilterCompilerFiles(),
            'rejudgingid' => $submission->getRejudgingid(),
            'entry_point' => $submission->getEntryPoint(),
            'origsubmitid' => $submission->getOrigsubmitid(),
            'maxruntime' => Utils::roundedFloat($maxRunTime, 6),
            'memlimit' => $submission->getProblem()->getMemlimit(),
            'outputlimit' => $submission->getProblem()->getOutputlimit(),
            'run' => $submission->getProblem()->getSpecialRun(),
            'compare' => $submission->getProblem()->getSpecialCompare(),
            'compare_args' => $submission->getProblem()->getSpecialCompareArgs(),
            'compile_script' => $submission->getLanguage()->getCompileScript(),
            'combined_run_compare' => $submission->getProblem()->getCombinedRunCompare()
        ];

        // Merge defaults
        if (empty($result['memlimit'])) {
            $result['memlimit'] = $this->config->get('memory_limit');
        }
        if (empty($result['outputlimit'])) {
            $result['outputlimit'] = $this->config->get('output_limit');
        }
        if (empty($result['compare'])) {
            $result['compare'] = $this->config->get('default_compare');
        }
        if (empty($result['run'])) {
            $result['run'] = $this->config->get('default_run');
        }

        // Add executable MD5's
        $compareExecutable        = $this->em->getRepository(Executable::class)->findOneBy(['execid' => $result['compare']]);
        $result['compare_md5sum'] = $compareExecutable ? $compareExecutable->getMd5sum() : null;
        $runExecutable            = $this->em->getRepository(Executable::class)->findOneBy(['execid' => $result['run']]);
        $result['run_md5sum']     = $runExecutable ? $runExecutable->getMd5sum() : null;

        if (!empty($result['compile_script'])) {
            $compileExecutable               = $this->em->getRepository(Executable::class)->findOneBy(['execid' => $result['compile_script']]);
            $result['compile_script_md5sum'] = $compileExecutable ? $compileExecutable->getMd5sum() : null;
        }

        // Determine previous judging for rejudgings
        $isRejudge       = isset($result['rejudgingid']);
        $previousJudging = null;
        if ($isRejudge) {
            // FIXME: what happens if there is no valid judging?
            $previousJudging = $this->em->getRepository(Judging::class)
                ->findOneBy([
                                'submitid' => $submission->getSubmitid(),
                                'valid' => 1
                            ]);
        }

        // Determine jury member for jury-edits
        $isEditSubmit = isset($result['origsubmitid']);
        $juryMember   = '';
        if ($isEditSubmit) {
            /** @var User[] $juryMembers */
            $juryMembers = $this->em->createQueryBuilder()
                ->from(User::class, 'u')
                ->select('u')
                ->join('u.team', 't')
                ->join('t.submissions', 's')
                ->andWhere('s.submitid = :submitid')
                ->setParameter(':submitid', $submission->getSubmitid())
                ->getQuery()
                ->getResult();
            if (count($juryMembers) === 1) {
                $juryMember = $juryMembers[0]->getUsername();
            } else {
                $isEditSubmit = false; // Really is edit/submit but no single owner
            }
        }

        // Insert judging, but in a transaction
        $this->em->transactional(function () use (
            $previousJudging,
            $juryMember,
            $isEditSubmit,
            $isRejudge,
            $judgehost,
            &$result,
            $submission
        ) {
            $judging = new Judging();
            $judging
                ->setSubmission($submission)
                ->setContest($submission->getContest())
                ->setStarttime(Utils::now())
                ->setJudgehost($judgehost);
            if ($isRejudge) {
                $judging
                    ->setRejudging($submission->getRejudging())
                    ->setOriginalJudging($previousJudging)
                    ->setValid(!$isRejudge);
            }
            if ($isEditSubmit) {
                $judging->setJuryMember($juryMember);
            }

            $this->em->persist($judging);
            $this->em->flush();

            // Log the judging create event, but only if we are not doing a rejudging
            if (!$isRejudge) {
                $this->eventLogService->log('judging', $judging->getJudgingid(),
                                            EventLogService::ACTION_CREATE, $judging->getContest()->getCid());
            }

            $result['judgingid'] = $judging->getJudgingid();
        });

        /** @var Testcase[] $testcases */
        $testcases     = $submission->getProblem()->getTestcases();
        $testcase_md5s = array();
        foreach ($testcases as $testcase) {
            $testcase_md5s[$testcase->getRank()] = array(
                'md5sum_input' => $testcase->getMd5sumInput(),
                'md5sum_output' => $testcase->getMd5sumOutput(),
                'testcaseid' => $testcase->getTestcaseid(),
                'rank' => $testcase->getRank()
            );
        }
        $result['testcases'] = $testcase_md5s;

        return $result;
    }

    /**
     * Update the given judging for the given judgehost
     * @Rest\Put("/update-judging/{hostname}/{judgingId<\d+>}")
     * @IsGranted("ROLE_JUDGEHOST")
     * @SWG\Response(
     *     response="200",
     *     description="When the judging has been updated"
     * )
     * @SWG\Parameter(
     *     name="hostname",
     *     in="path",
     *     type="string",
     *     description="The hostname of the judgehost that wants to update the judging"
     * )
     * @SWG\Parameter(
     *     name="judgingId",
     *     in="path",
     *     type="integer",
     *     description="The ID of the judging to update"
     * )
     * @param Request $request
     * @param string  $hostname
     * @param int     $judgingId
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function updateJudgingAction(Request $request, string $hostname, int $judgingId)
    {
        /** @var Judgehost $judgehost */
        $judgehost = $this->em->getRepository(Judgehost::class)->find($hostname);
        if (!$judgehost) {
            return;
        }

        $query = $this->em->createQueryBuilder()
            ->from(Judging::class, 'j')
            ->join('j.submission', 's')
            ->join('j.judgehost', 'jh')
            ->join('s.contest', 'c')
            ->join('s.team', 't')
            ->join('s.problem', 'p')
            ->select('j, s, jh, c, t, p')
            ->andWhere('j.judgingid = :judgingId')
            ->setParameter(':judgingId', $judgingId)
            ->setMaxResults(1)
            ->getQuery();

        /** @var Judging $judging */
        $judging = $query->getOneOrNullResult();
        if (!$judging) {
            return;
        }

        if ($request->request->has('output_compile')) {
            if ($request->request->has('entry_point')) {
                $this->em->transactional(function () use ($query, $request, &$judging) {
                    $submission = $judging->getSubmission();
                    $submission->setEntryPoint($request->request->get('entry_point'));
                    $this->em->flush();
                    $submissionId = $submission->getSubmitid();
                    $contestId    = $submission->getCid();
                    $this->eventLogService->log('submission', $submissionId,
                                                EventLogService::ACTION_UPDATE, $contestId);

                    // As EventLogService::log() will clear the entity manager, so the judging has
                    // now become detached. We will have to reload it
                    $judging = $query->getOneOrNullResult();
                });
            }

            if ($request->request->getBoolean('compile_success')) {
                if ($judging->getJudgehost()->getHostname() === $hostname) {
                    $judging->setOutputCompile(base64_decode($request->request->get('output_compile')));
                    $this->em->flush();
                }
            } else {
                $this->em->transactional(function () use (
                    $request,
                    $hostname,
                    $judging
                ) {
                    if ($judging->getJudgehost()->getHostname() === $hostname) {
                        $judging
                            ->setOutputCompile(base64_decode($request->request->get('output_compile')))
                            ->setResult(Judging::RESULT_COMPILER_ERROR)
                            ->setEndtime(Utils::now());
                        $this->em->flush();
                    }

                    $judgingId = $judging->getJudgingid();
                    $contestId = $judging->getSubmission()->getCid();
                    $this->dj->auditlog('judging', $judgingId, 'judged',
                                        'compiler-error', $hostname, $contestId);

                    $this->maybeUpdateActiveJudging($judging);
                    $this->em->flush();
                    if (!$this->config->get('verification_required') &&
                        $judging->getValid()) {
                        $this->eventLogService->log('judging', $judgingId,
                                                    EventLogService::ACTION_UPDATE, $contestId);
                    }

                    $submission = $judging->getSubmission();
                    $contest    = $submission->getContest();
                    $team       = $submission->getTeam();
                    $problem    = $submission->getProblem();
                    $this->scoreboardService->calculateScoreRow($contest, $team, $problem);

                    $message = sprintf("submission %i, judging %i: compiler-error",
                                       $submission->getSubmitid(), $judging->getJudgingid());
                    $this->dj->alert('reject', $message);
                });
            }
        }

        $judgehost->setPolltime(Utils::now());
        $this->em->flush();
    }

    /**
     * Add an array of JudgingRuns. When relevant, finalize the judging.
     * @Rest\Post("/add-judging-run/{hostname}/{judgingId<\d+>}")
     * @IsGranted("ROLE_JUDGEHOST")
     * @SWG\Response(
     *     response="200",
     *     description="When the judging run has been added"
     * )
     * @SWG\Parameter(
     *     name="hostname",
     *     in="path",
     *     type="string",
     *     description="The hostname of the judgehost that wants to add the judging run"
     * )
     * @SWG\Parameter(
     *     name="judgingId",
     *     in="path",
     *     type="integer",
     *     description="The ID of the judging to add a run to"
     * )
     * @SWG\Parameter(
     *     name="testcaseid",
     *     in="formData",
     *     type="integer",
     *     description="The ID of the testcase of the run to add"
     * )
     * @SWG\Parameter(
     *     name="runresult",
     *     in="formData",
     *     type="string",
     *     description="The result of the run"
     * )
     * @SWG\Parameter(
     *     name="runtime",
     *     in="formData",
     *     type="number",
     *     format="float",
     *     description="The runtime of the run"
     * )
     * @SWG\Parameter(
     *     name="output_run",
     *     in="formData",
     *     type="string",
     *     description="The (base64-encoded) output of the run"
     * )
     * @SWG\Parameter(
     *     name="output_diff",
     *     in="formData",
     *     type="string",
     *     description="The (base64-encoded) output diff of the run"
     * )
     * @SWG\Parameter(
     *     name="output_error",
     *     in="formData",
     *     type="string",
     *     description="The (base64-encoded) error output of the run"
     * )
     * @SWG\Parameter(
     *     name="output_system",
     *     in="formData",
     *     type="string",
     *     description="The (base64-encoded) system output of the run"
     * )
     * @SWG\Parameter(
     *     name="metadata",
     *     in="formData",
     *     type="string",
     *     description="The (base64-encoded) metadata"
     * )
     * @param Request $request
     * @param string  $hostname
     * @param int     $judgingId
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     */
    public function addJudgingRunAction(
        Request $request,
        string $hostname,
        int $judgingId
    ) {
        if ($request->request->has('batch')) {
            $this->addMultipleJudgingRuns($request, $hostname, $judgingId);
        } else {
            $required = [
                'testcaseid',
                'runresult',
                'runtime',
                'output_run',
                'output_diff',
                'output_error',
                'output_system'
            ];

            foreach ($required as $argument) {
                if (!$request->request->has($argument)) {
                    throw new BadRequestHttpException(
                        sprintf("Argument '%s' is mandatory", $argument));
                }
            }

            $testCaseId   = $request->request->get('testcaseid');
            $runResult    = $request->request->get('runresult');
            $runTime      = $request->request->get('runtime');
            $outputRun    = $request->request->get('output_run');
            $outputDiff   = $request->request->get('output_diff');
            $outputError  = $request->request->get('output_error');
            $outputSystem = $request->request->get('output_system');
            $metadata     = $request->request->get('metadata');

            /** @var Judgehost $judgehost */
            $judgehost = $this->em->getRepository(Judgehost::class)->find($hostname);
            if (!$judgehost) {
                return;
            }

            /** @var Judging $judging */
            $judging = $this->em->getRepository(Judging::class)->find($judgingId);
            $this->addSingleJudgingRun($hostname, $judgingId, (int)$testCaseId, $runResult, $runTime, $judging,
                                       $outputSystem, $outputError, $outputDiff, $outputRun, $metadata);
            $judgehost = $this->em->getRepository(Judgehost::class)->find($hostname);
            $judgehost->setPolltime(Utils::now());
            $this->em->flush();
        }
    }

    /**
     * Add multiple judgings runs to a given judging
     * @param Request $request
     * @param string  $hostname
     * @param int     $judgingId
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     */
    private function addMultipleJudgingRuns(Request $request, string $hostname, int $judgingId)
    {
        $judgingRuns = json_decode($request->request->get('batch'), true);
        if (!is_array($judgingRuns)) {
            throw new BadRequestHttpException(
                sprintf("Argument 'batch' is not an array"));
        }
        /** @var Judgehost $judgehost */ // <--- FIXME: do this in the other commit
        $judgehost = $this->em->getRepository(Judgehost::class)->find($hostname);
        if (!$judgehost) {
            throw new BadRequestHttpException(
                sprintf("Judgehost unknown, please register yourself first!"));
        }
        foreach ($judgingRuns as $judgingRun) {
            $required = [
                'testcaseid',
                'runresult',
                'runtime',
                'output_run',
                'output_diff',
                'output_error',
                'output_system',
                'metadata'
            ];

            foreach ($required as $argument) {
                if (!isset($judgingRun[$argument])) {
                    throw new BadRequestHttpException(
                        sprintf("Argument '%s' is mandatory, got '%s'.", $argument, var_export($judgingRun, true)));
                }
            }

            $testCaseId   = $judgingRun['testcaseid'];
            $runResult    = $judgingRun['runresult'];
            $runTime      = $judgingRun['runtime'];
            $outputRun    = $judgingRun['output_run'];
            $outputDiff   = $judgingRun['output_diff'];
            $outputError  = $judgingRun['output_error'];
            $outputSystem = $judgingRun['output_system'];
            $metadata     = $judgingRun['metadata'];
            /** @var Judging $judging */
            $judging = $this->em->getRepository(Judging::class)->find($judgingId);
            if (!$judging) {
                throw new BadRequestHttpException(
                    sprintf("Unknown judging, don't send us any imaginary data!"));
            }
            $this->addSingleJudgingRun($hostname, $judgingId, (int)$testCaseId, $runResult, $runTime, $judging,
                                       $outputSystem, $outputError, $outputDiff, $outputRun, $metadata);
        }
        $judgehost = $this->em->getRepository(Judgehost::class)->find($hostname);
        $judgehost->setPolltime(Utils::now());
        $this->em->flush();
    }

    /**
     * Internal error reporting (back from judgehost)
     *
     * @Rest\Post("/internal-error")
     * @IsGranted("ROLE_JUDGEHOST")
     * @SWG\Response(
     *     response="200",
     *     description="The ID of the created internal error",
     *     @SWG\Schema(type="integer")
     * )
     * @SWG\Parameter(
     *     name="description",
     *     in="formData",
     *     type="string",
     *     description="The description of the internal error",
     *     required=true
     * )
     * @SWG\Parameter(
     *     name="judgehostlog",
     *     in="formData",
     *     type="string",
     *     description="The log of the judgehost",
     *     required=true
     * )
     * @SWG\Parameter(
     *     name="disabled",
     *     in="formData",
     *     type="string",
     *     description="The object to disable in JSON format",
     *     required=true
     * )
     * @SWG\Parameter(
     *     name="cid",
     *     in="formData",
     *     type="integer",
     *     description="The contest ID associated with this internal error",
     *     required=false
     * )
     * @SWG\Parameter(
     *     name="judgingid",
     *     in="formData",
     *     type="integer",
     *     description="The ID of the judging that was being worked on",
     *     required=false
     * )
     * @param Request $request
     * @return int|string
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     */
    public function internalErrorAction(Request $request)
    {
        $required = ['description', 'judgehostlog', 'disabled'];
        foreach ($required as $argument) {
            if (!$request->request->has($argument)) {
                throw new BadRequestHttpException(sprintf("Argument '%s' is mandatory", $argument));
            }
        }
        $description  = $request->request->get('description');
        $judgehostlog = $request->request->get('judgehostlog');
        $disabled     = $request->request->get('disabled');

        // Both cid and judgingid are allowed to be NULL.
        $cid       = $request->request->get('cid');
        $judgingId = $request->request->get('judgingid');

        // Group together duplicate internal errors
        // Note that it may be good to be able to ignore fields here, e.g. judgingid with compile errors
        $queryBuilder = $this->em->createQueryBuilder()
            ->from(InternalError::class, 'e')
            ->select('e')
            ->andWhere('e.description = :description')
            ->andWhere('e.disabled = :disabled')
            ->andWhere('e.status = :status')
            ->setParameter(':description', $description)
            ->setParameter(':disabled', $disabled)
            ->setParameter(':status', 'open')
            ->setMaxResults(1);

        /** @var Contest|null $contest */
        $contest = null;
        if ($cid) {
            $contestIdField = $this->eventLogService->externalIdFieldForEntity(Contest::class) ?? 'cid';
            $contest        = $this->em->createQueryBuilder()
                ->from(Contest::class, 'c')
                ->select('c')
                ->andWhere(sprintf('c.%s = :cid', $contestIdField))
                ->setParameter(':cid', $cid)
                ->getQuery()
                ->getSingleResult();
        }

        /** @var InternalError $error */
        $error = $queryBuilder->getQuery()->getOneOrNullResult();

        if ($error) {
            // FIXME: in some cases it makes sense to extend the known information, e.g. the judgehostlog
            return $error->getErrorid();
        }

        $error = new InternalError();
        $error
            ->setJudging($judgingId ? $this->em->getReference(Judging::class, $judgingId) : null)
            ->setContest($contest)
            ->setDescription($description)
            ->setJudgehostlog($judgehostlog)
            ->setTime(Utils::now())
            ->setDisabled(json_decode($disabled, true));

        $this->em->persist($error);
        $this->em->flush();

        $disabled = $this->dj->jsonDecode($disabled);

        $this->dj->setInternalError($disabled, $contest, false);

        if (in_array($disabled['kind'], ['problem', 'language', 'judgehost']) && $judgingId) {
            // give back judging if we have to
            $this->giveBackJudging((int)$judgingId);
        }

        return $error->getErrorid();
    }

    /**
     * Give back the judging with the given judging ID
     * @param int $judgingId
     */
    protected function giveBackJudging(int $judgingId)
    {
        /** @var Judging $judging */
        $judging = $this->em->getRepository(Judging::class)->find($judgingId);
        if ($judging) {
            $this->em->transactional(function () use ($judging) {
                $judging
                    ->setValid(false)
                    ->setRejudgingid(null);

                $judging->getSubmission()->setJudgehost(null);
            });

            $this->dj->auditlog('judging', $judgingId, 'given back', null,
                                             $judging->getJudgehost()->getHostname(), $judging->getCid());
        }
    }

    /**
     * Add a single judging to a given judging run
     * @param string  $hostname
     * @param int     $judgingId
     * @param int     $testCaseId
     * @param string  $runResult
     * @param string  $runTime
     * @param Judging $judging
     * @param string  $outputSystem
     * @param string  $outputError
     * @param string  $outputDiff
     * @param string  $outputRun
     * @param string  $metadata
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     */
    private function addSingleJudgingRun(
        string $hostname,
        int $judgingId,
        int $testCaseId,
        string $runResult,
        string $runTime,
        Judging $judging,
        string $outputSystem,
        string $outputError,
        string $outputDiff,
        string $outputRun,
        string $metadata
    ) {
        /** @var Testcase $testCase */
        $testCase = $this->em->getRepository(Testcase::class)->find($testCaseId);

        $resultsRemap = $this->config->get('results_remap');
        $resultsPrio  = $this->config->get('results_prio');

        if (array_key_exists($runResult, $resultsRemap)) {
            $this->logger->info('Testcase %d remapping result %s -> %s',
                                [ $testCaseId, $runResult, $resultsRemap[$runResult] ]);
            $runResult = $resultsRemap[$runResult];
        }

        $this->em->transactional(function () use (
            $runTime,
            $runResult,
            $testCase,
            $judging,
            $outputSystem,
            $outputError,
            $outputDiff,
            $outputRun,
            $metadata
        ) {
            $judgingRun = new JudgingRun();
            $judgingRunOutput = new JudgingRunOutput();
            $judgingRun->setOutput($judgingRunOutput);
            $judgingRun
                ->setJudging($judging)
                ->setTestcase($testCase)
                ->setRunresult($runResult)
                ->setRuntime($runTime)
                ->setEndtime(Utils::now());
            $judgingRunOutput
                ->setOutputRun(base64_decode($outputRun))
                ->setOutputDiff(base64_decode($outputDiff))
                ->setOutputError(base64_decode($outputError))
                ->setOutputSystem(base64_decode($outputSystem))
                ->setMetadata(base64_decode($metadata));

            $this->maybeUpdateActiveJudging($judging);
            $this->em->persist($judgingRun);
            $this->em->flush();

            if ($judging->getValid()) {
                $this->eventLogService->log('judging_run', $judgingRun->getRunid(),
                                            EventLogService::ACTION_CREATE, $judging->getCid());
            }
        });

        // Reload the testcase and judging, as EventLogService::log will clear the entity manager.
        // For the judging, also load in the submission and some of it's relations
        $testCase = $this->em->getRepository(Testcase::class)->find($testCaseId);
        /** @var Judging $judging */
        $judging  = $this->em->createQueryBuilder()
            ->from(Judging::class, 'j')
            ->join('j.submission', 's')
            ->join('s.problem', 'p')
            ->join('s.team', 't')
            ->join('s.contest', 'c')
            ->join('s.contest_problem', 'cp')
            ->select('j, s, p, t, c')
            ->where('j.judgingid = :judgingid')
            ->setParameter(':judgingid', $judgingId)
            ->getQuery()
            ->getOneOrNullResult();

        // result of this judging_run has been stored. now check whether
        // we're done or if more testcases need to be judged.

        /** @var JudgingRun[] $runs */
        $runs = $this->em->createQueryBuilder()
            ->from(JudgingRun::class, 'r')
            ->join('r.testcase', 't')
            ->select('r')
            ->andWhere('r.judgingid = :judgingid')
            ->orderBy('t.rank')
            ->setParameter(':judgingid', $judgingId)
            ->getQuery()
            ->getResult();

        $numTestCases = $this->em->createQueryBuilder()
            ->from(Testcase::class, 't')
            ->select('COUNT(t.testcaseid)')
            ->where('t.probid = :probid')
            ->setParameter(':probid', $testCase->getProbid())
            ->getQuery()
            ->getSingleScalarResult();

        $allRuns = array_pad($runs, (int)$numTestCases, null);

        $oldResult = $judging->getResult();

        if (($result = $this->submissionService->getFinalResult($allRuns, $resultsPrio)) !== null) {
            // Lookup global lazy evaluation of results setting and possible problem specific override.
            $lazyEval    = $this->config->get('lazy_eval_results');
            $problemLazy = $judging->getSubmission()->getContestProblem()->getLazyEvalResults();
            if (isset($problemLazy)) {
                $lazyEval = $problemLazy;
            }

            $judging->setResult($result);
            if (count($runs) == $numTestCases || $lazyEval) {
                // NOTE: setting endtime here determines in testcases_GET
                // whether a next testcase will be handed out.
                $judging->setEndtime(Utils::now());
                $this->maybeUpdateActiveJudging($judging);
            }
            $this->em->flush();

            // Only update if the current result is different from what we had before.
            // This should only happen when the old result was NULL.
            if ($oldResult !== $result) {
                if ($oldResult !== null) {
                    throw new \BadMethodCallException('internal bug: the evaluated result changed during judging');
                }

                /** @var Submission $submission */
                $submission = $judging->getSubmission();
                $contest    = $submission->getContest();
                $team       = $submission->getTeam();
                $problem    = $submission->getProblem();
                $this->scoreboardService->calculateScoreRow($contest, $team, $problem);

                // We call alert here before possible validation. Note that
                // this means that these alert messages should be treated as
                // confidential information.
                $msg = sprintf("submission %s, judging %s: %s",
                               $submission->getSubmitid(), $judging->getJudgingid(), $result);
                $this->dj->alert($result === 'correct' ? 'accept' : 'reject', $msg);

                // Log to event table if no verification required
                // (case of verification required is handled in
                // jury/SubmissionController::verifyAction)
                if (!$this->config->get('verification_required')) {
                    if ($judging->getValid()) {
                        $this->eventLogService->log('judging', $judging->getJudgingid(),
                                                    EventLogService::ACTION_UPDATE,
                                                    $judging->getCid());
                        $this->balloonService->updateBalloons($contest, $submission, $judging);
                    }
                }

                $this->dj->auditlog('judging', $judgingId, 'judged', $result,
                                                 $hostname);

                $justFinished = true;
            }
        }

        // Send an event for an endtime update if not done yet.
        if ($judging->getValid() && count($runs) == $numTestCases && empty($justFinished)) {
            $this->eventLogService->log('judging', $judging->getJudgingid(),
                                        EventLogService::ACTION_UPDATE, $judging->getCid());
        }
    }

    private function maybeUpdateActiveJudging(Judging $judging): void
    {
        if ($judging->getRejudgingid() !== null) {
            $rejudging = $judging->getRejudging();
            if ($rejudging->getAutoApply()) {
                $judging->getSubmission()->setRejudging(null);
                foreach ($judging->getSubmission()->getJudgings() as $j) {
                    $j->setValid(false);
                }
                $judging->setValid(true);

                // Check whether we are completely done with this rejudging.
                if ($rejudging->getEndtime() === null && $this->rejudgingService->calculateTodo($rejudging)['todo'] == 0) {
                    $rejudging->setEndtime(Utils::now());
                    $rejudging->setFinishUser(null);
                    $this->em->flush();
                }
            }

            if ($rejudging->getRepeat() > 1 && $rejudging->getEndtime() === null
                    && $this->rejudgingService->calculateTodo($rejudging)['todo'] == 0) {
                $numberOfRepetitions = $this->em->createQueryBuilder()
                    ->from(Rejudging::class, 'r')
                    ->select('COUNT(r.rejudgingid) AS cnt')
                    ->andWhere('r.repeat_rejudgingid = :repeat_rejudgingid')
                    ->setParameter('repeat_rejudgingid', $rejudging->getRepeatRejudgingId())
                    ->getQuery()
                    ->getSingleScalarResult();
                // Only "cancel" the rejudging if it's not the last.
                if ($numberOfRepetitions < $rejudging->getRepeat()) {
                    $rejudging
                        ->setEndtime(Utils::now())
                        ->setFinishUser(null)
                        ->setValid(false);
                    $this->em->flush();

                    // Reset association before creating the new rejudging.
                    $this->em->getConnection()->executeQuery(
                        'UPDATE submission
                            SET rejudgingid = NULL
                            WHERE rejudgingid = :rejudgingid',
                        [':rejudgingid' => $rejudging->getRejudgingid()]);
                    $this->em->flush();

                    $skipped = [];
                    /** @var array[] $judgings */
                    $judgings = $this->em->createQueryBuilder()
                        ->from(Judging::class, 'j')
                        ->leftJoin('j.submission', 's')
                        ->select('j', 's')
                        ->andWhere('j.rejudgingid = :rejudgingid')
                        ->setParameter('rejudgingid', $rejudging->getRejudgingid())
                        ->getQuery()
                        ->getResult(Query::HYDRATE_ARRAY);
                    $this->rejudgingService->createRejudging($rejudging->getReason(), $judgings,
                        false, $rejudging->getRepeat(), $rejudging->getRepeatRejudgingId(), $skipped);
                }
            }
        }
    }

    private function getSubmissionsToJudge(Judgehost $judgehost, $restrictJudgingOnSameJudgehost)
    {
        // Get all active contests
        $contests   = $this->dj->getCurrentContests();
        $contestIds = array_map(function (Contest $contest) {
            return $contest->getCid();
        }, $contests);

        // If there are no active contests, there is nothing to do
        if (empty($contestIds)) {
            return [];
        }

        // Determine all viable submissions
        $queryBuilder = $this->em->createQueryBuilder()
            ->from(Submission::class, 's')
            ->join('s.team', 't')
            ->join('s.language', 'l')
            ->join('s.contest_problem', 'cp')
            ->select('s')
            ->andWhere('s.judgehost IS NULL')
            ->andWhere('s.cid IN (:contestIds)')
            ->setParameter(':contestIds', $contestIds)
            ->andWhere('l.allowJudge= 1')
            ->andWhere('cp.allowJudge = 1')
            ->andWhere('s.valid = 1')
            ->orderBy('t.judging_last_started', 'ASC')
            ->addOrderBy('s.submittime', 'ASC')
            ->addOrderBy('s.submitid', 'ASC');

        // Apply restrictions
        if ($judgehost->getRestriction()) {
            $restrictions = $judgehost->getRestriction()->getRestrictions();

            if (isset($restrictions['contest'])) {
                $queryBuilder
                    ->andWhere('s.cid IN (:restrictionContestIds)')
                    ->setParameter(':restrictionContestIds', $restrictions['contest']);
            }

            if (isset($restrictions['problem'])) {
                $queryBuilder
                    ->andWhere('s.probid IN (:restrictionProblemIds)')
                    ->setParameter(':restrictionProblemIds', $restrictions['problem']);
            }

            if (isset($restrictions['language'])) {
                $queryBuilder
                    ->andWhere('s.langid IN (:restrictionLanguageIds)')
                    ->setParameter(':restrictionLanguageIds', $restrictions['language']);
            }
        }
        if ($restrictJudgingOnSameJudgehost) {
            $queryBuilder
                ->leftJoin('s.judgings', 'j', Join::WITH, 'j.judgehost = :judgehost')
                ->andWhere('j.judgehost IS NULL')
                ->setParameter(':judgehost', $judgehost->getHostname());
        }

        /** @var Submission[] $submissions */
        $submissions = $queryBuilder->getQuery()->getResult();
        return $submissions;
    }
}
