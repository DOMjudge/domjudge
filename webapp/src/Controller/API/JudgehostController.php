<?php declare(strict_types=1);

namespace App\Controller\API;

use App\Doctrine\DBAL\Types\JudgeTaskType;
use App\Entity\Contest;
use App\Entity\Executable;
use App\Entity\ExecutableFile;
use App\Entity\InternalError;
use App\Entity\Judgehost;
use App\Entity\JudgeTask;
use App\Entity\Judging;
use App\Entity\JudgingRun;
use App\Entity\JudgingRunOutput;
use App\Entity\Rejudging;
use App\Entity\Submission;
use App\Entity\SubmissionFile;
use App\Entity\Testcase;
use App\Entity\TestcaseContent;
use App\Entity\User;
use App\Service\BalloonService;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Service\RejudgingService;
use App\Service\ScoreboardService;
use App\Service\SubmissionService;
use App\Utils\Utils;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\Expr\Join;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\Model;
use Psr\Log\LoggerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use OpenApi\Annotations as OA;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @Rest\Route("/judgehosts")
 * @OA\Tag(name="Judgehosts")
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
     * @OA\Response(
     *     response="200",
     *     description="The judgehosts",
     *     @OA\JsonContent(type="array", @OA\Items(ref=@Model(type=Judgehost::class)))
     * )
     * @OA\Parameter(
     *     name="hostname",
     *     in="query",
     *     description="Only show the judgehost with the given hostname",
     *     @OA\Schema(type="string")
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
     * @OA\Response(
     *     response="200",
     *     description="The returned unfinished judgings",
     *     @OA\JsonContent(
     *         type="array",
     *         @OA\Items(
     *             type="object",
     *             properties={
     *                 @OA\Property(property="judgingid", type="integer"),
     *                 @OA\Property(property="submitid", type="integer"),
     *                 @OA\Property(property="cid", type="integer")
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
                'jobid' => $judging->getJudgingid(),
                'submitid' => $judging->getSubmission()->getSubmitid(),
            ];
        }, $judgings);
    }

    /**
     * Update the configuration of the given judgehost
     * @Rest\Put("/{hostname}")
     * @IsGranted("ROLE_JUDGEHOST")
     * @OA\Response(
     *     response="200",
     *     description="The modified judgehost",
     *     @OA\JsonContent(type="array", @OA\Items(ref=@Model(type=Judgehost::class)))
     * )
     * @OA\Parameter(
     *     name="hostname",
     *     in="path",
     *     description="The hostname of the judgehost to update",
     *     @OA\Schema(type="string")
     * )
     * @OA\RequestBody(
     *     required=true,
     *     @OA\MediaType(
     *         mediaType="application/x-www-form-urlencoded",
     *         @OA\Schema(
     *             @OA\Property(
     *                 property="active",
     *                 description="The new active state of the judgehost",
     *                 @OA\Schema(type="boolean")
     *             )
     *         )
     *     )
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
     * @OA\Response(
     *     response="200",
     *     description="The next judging to judge",
     *     @OA\JsonContent(ref="#/components/schemas/NextJudging")
     * )
     * @OA\Parameter(
     *     name="hostname",
     *     in="path",
     *     description="The hostname of the judgehost to get the next judging for",
     *     @OA\Schema(type="string")
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
            'teamid' => $submission->getTeam()->getTeamid(),
            'probid' => $submission->getProblem()->getProbid(),
            'langid' => $submission->getLanguage()->getLangid(),
            'language_extensions' => $submission->getLanguage()->getExtensions(),
            'filter_compiler_files' => $submission->getLanguage()->getFilterCompilerFiles(),
            'rejudgingid' => $submission->getRejudging() ? $submission->getRejudging()->getRejudgingid() : null,
            'entry_point' => $submission->getEntryPoint(),
            'origsubmitid' => $submission->getOriginalSubmission() ? $submission->getOriginalSubmission()->getSubmitid() : null,
            'maxruntime' => Utils::roundedFloat($maxRunTime, 6),
            'memlimit' => $submission->getProblem()->getMemlimit(),
            'outputlimit' => $submission->getProblem()->getOutputlimit(),
            'run' => $submission->getProblem()->getRunExecutable() ? $submission->getProblem()->getRunExecutable()->getExecid() : null,
            'compare' => $submission->getProblem()->getCompareExecutable() ? $submission->getProblem()->getCompareExecutable()->getExecid() : null,
            'compare_args' => $submission->getProblem()->getSpecialCompareArgs(),
            'compile_script' => $submission->getLanguage()->getCompileExecutable()->getExecid(),
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
                                'submission' => $submission->getSubmitid(),
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
     * @Rest\Put("/update-judging/{hostname}/{judgetaskid}")
     * @IsGranted("ROLE_JUDGEHOST")
     * @OA\Response(
     *     response="200",
     *     description="When the judging has been updated"
     * )
     * @OA\Parameter(
     *     name="hostname",
     *     in="path",
     *     description="The hostname of the judgehost that wants to update the judging",
     *     @OA\Schema(type="string")
     * )
     * @OA\Parameter(
     *     name="judgetaskid",
     *     in="path",
     *     description="The ID of the judgetask to update",
     *     @OA\Schema(type="integer")
     * )
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function updateJudgingAction(Request $request, string $hostname, int $judgetaskid)
    {
        /** @var Judgehost $judgehost */
        $judgehost = $this->em->getRepository(Judgehost::class)->find($hostname);
        if (!$judgehost) {
            throw new BadRequestHttpException("Who are you and why are you sending us any data?");
            return;
        }

        /** @var JudgingRun $judgingRun */
        $judgingRun = $this->em->createQueryBuilder()
            ->from(JudgingRun::class, 'jr')
            ->select('jr')
            ->andWhere('jr.judgetaskid = :judgetaskid')
            ->setParameter(':judgetaskid', $judgetaskid)
            ->getQuery()
            ->getSingleResult();

        $query = $this->em->createQueryBuilder()
            ->from(Judging::class, 'j')
            ->join('j.submission', 's')
            ->join('s.contest', 'c')
            ->join('s.team', 't')
            ->join('s.problem', 'p')
            ->select('j, s, c, t, p')
            ->andWhere('j.judgingid = :judgingid')
            ->setParameter(':judgingid', $judgingRun->getJudgingId())
            ->setMaxResults(1)
            ->getQuery();

        /** @var Judging $judging */
        $judging = $query->getOneOrNullResult();
        if (!$judging) {
            throw new BadRequestHttpException("We don't know this judging with judgetaskid ID $judgetaskid.");
            return;
        }

        if ($request->request->has('output_compile')) {
            if ($request->request->has('entry_point')) {
                $this->em->transactional(function () use ($query, $request, &$judging) {
                    $submission = $judging->getSubmission();
                    $submission->setEntryPoint($request->request->get('entry_point'));
                    $this->em->flush();
                    $submissionId = $submission->getSubmitid();
                    $contestId    = $submission->getContest()->getCid();
                    $this->eventLogService->log('submission', $submissionId,
                                                EventLogService::ACTION_UPDATE, $contestId);

                    // As EventLogService::log() will clear the entity manager, so the judging has
                    // now become detached. We will have to reload it
                    /** @var Judging $judging */
                    $judging = $query->getOneOrNullResult();
                });
            }

            // Reload judgehost just in case it got cleared above.
            /** @var Judgehost $judgehost */
            $judgehost = $this->em->getRepository(Judgehost::class)->find($hostname);

            $output_compile = base64_decode($request->request->get('output_compile'));
            if ($request->request->getBoolean('compile_success')) {
                if ($judging->getOutputCompile() === null) {
                    $judging
                        ->setOutputCompile($output_compile)
                        ->setJudgehost($judgehost);
                    $this->em->flush();

                    $this->eventLogService->log('judging', $judging->getJudgingid(),
                        EventLogService::ACTION_CREATE, $judging->getContest()->getCid());
                } elseif ($judging->getResult() === Judging::RESULT_COMPILER_ERROR) {
                    // The new result contradicts a former one, that's not good.
                    $error = new InternalError();
                    $error
                        ->setJudging($judging)
                        ->setContest($judging->getContest())
                        ->setDescription('Compilation results are different for j' . $judging->getJudgingid())
                        ->setJudgehostlog('New compilation output: ' . $output_compile)
                        ->setTime(Utils::now())
                        ->setDisabled(null);
                    $this->em->persist($error);
                }
            } else {
                $this->em->transactional(function () use (
                    $request,
                    $judgehost,
                    $judging,
                    $query,
                    $output_compile
                ) {
                    if ($judging->getOutputCompile() === null) {
                        $judging
                            ->setOutputCompile($output_compile)
                            ->setResult(Judging::RESULT_COMPILER_ERROR)
                            ->setJudgehost($judgehost)
                            ->setEndtime(Utils::now());
                        $this->em->flush();

                        $this->eventLogService->log('judging', $judging->getJudgingid(),
                            EventLogService::ACTION_CREATE, $judging->getContest()->getCid());

                        // As EventLogService::log() will clear the entity manager, so the judging has
                        // now become detached. We will have to reload it
                        /** @var Judging $judging */
                        $judging = $query->getOneOrNullResult();

                        // Invalidate judgetasks.
                        $this->em->getConnection()->executeUpdate(
                            'UPDATE judgetask SET valid=0'
                            . ' WHERE jobid=:jobid',
                            [
                                ':jobid' => $judging->getJudgingid(),
                            ]
                        );
                        $this->em->flush();
                    } else if ($judging->getResult() !== Judging::RESULT_COMPILER_ERROR) {
                        // The new result contradicts a former one, that's not good.
                        $error = new InternalError();
                        $error
                            ->setJudging($judging)
                            ->setContest($judging->getContest())
                            ->setDescription('Compilation results are different for j' . $judging->getJudgingid())
                            ->setJudgehostlog('New compilation output: ' . $output_compile)
                            ->setTime(Utils::now())
                            ->setDisabled(null);
                        $this->em->persist($error);
                    }

                    $judgingId = $judging->getJudgingid();
                    $contestId = $judging->getSubmission()->getContest()->getCid();
                    $this->dj->auditlog('judging', $judgingId, 'judged',
                                        'compiler-error', $judgehost->getHostname(), $contestId);

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
     * Add one JudgingRun. When relevant, finalize the judging.
     * @Rest\Post("/add-judging-run/{hostname}/{judgeTaskId}")
     * @IsGranted("ROLE_JUDGEHOST")
     * @OA\Response(
     *     response="200",
     *     description="When the judging run has been added"
     * )
     * @OA\Parameter(
     *     name="hostname",
     *     in="path",
     *     description="The hostname of the judgehost that wants to add the judging run",
     *     @OA\Schema(type="string")
     * )
     * @OA\Parameter(
     *     name="testcaseid",
     *     in="formData",
     *     description="The ID of the testcase of the run to add",
     *     @OA\Schema(type="integer")
     * )
     * @OA\Parameter(
     *     name="runresult",
     *     in="formData",
     *     description="The result of the run",
     *     @OA\Schema(type="string")
     * )
     * @OA\Parameter(
     *     name="runtime",
     *     in="formData",
     *     description="The runtime of the run",
     *     @OA\Schema(type="number", format="float")
     * )
     * @OA\Parameter(
     *     name="output_run",
     *     in="formData",
     *     description="The (base64-encoded) output of the run",
     *     @OA\Schema(type="string")
     * )
     * @OA\Parameter(
     *     name="output_diff",
     *     in="formData",
     *     description="The (base64-encoded) output diff of the run",
     *     @OA\Schema(type="string")
     * )
     * @OA\RequestBody(
     *     required=true,
     *     @OA\MediaType(
     *         mediaType="application/x-www-form-urlencoded",
     *         @OA\Schema(
     *             @OA\Property(
     *                 property="testcaseid",
     *                 description="The ID of the testcase of the run to add",
     *                 @OA\Schema(type="integer")
     *             ),
     *             @OA\Property(
     *                 property="runresult",
     *                 description="The result of the run",
     *                 @OA\Schema(type="string")
     *             ),
     *             @OA\Property(
     *                 property="runtime",
     *                 description="The runtime of the run",
     *                 @OA\Schema(type="number", format="float")
     *             ),
     *             @OA\Property(
     *                 property="output_run",
     *                 description="The (base64-encoded) output of the run",
     *                 @OA\Schema(type="string")
     *             ),
     *             @OA\Property(
     *                 property="output_diff",
     *                 description="The (base64-encoded) output diff of the run",
     *                 @OA\Schema(type="string")
     *             ),
     *             @OA\Property(
     *                 property="output_error",
     *                 description="The (base64-encoded) error output of the run",
     *                 @OA\Schema(type="string")
     *             ),
     *             @OA\Property(
     *                 property="output_system",
     *                 description="The (base64-encoded) system output of the run",
     *                 @OA\Schema(type="string")
     *             ),
     *             @OA\Property(
     *                 property="metadata",
     *                 description="The (base64-encoded) metadata",
     *                 @OA\Schema(type="string")
     *             )
     *         )
     *     )
     * )
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     */
    public function addJudgingRunAction(
        Request $request,
        string $hostname,
        int $judgeTaskId
    ) {
        $required = [
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
            throw new BadRequestHttpException("Who are you and why are you sending us any data?");
            return;
        }

        $this->addSingleJudgingRun($judgeTaskId, $hostname, $runResult, $runTime,
                                   $outputSystem, $outputError, $outputDiff, $outputRun, $metadata);
        $judgehost = $this->em->getRepository(Judgehost::class)->find($hostname);
        $judgehost->setPolltime(Utils::now());
        $this->em->flush();
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
     * @OA\Response(
     *     response="200",
     *     description="The ID of the created internal error",
     *     @OA\JsonContent(type="integer")
     * )
     * @OA\RequestBody(
     *     required=true,
     *     @OA\MediaType(
     *         mediaType="application/x-www-form-urlencoded",
     *         @OA\Schema(
     *             required={"description","judgehostlog","disabled"},
     *             @OA\Property(
     *                 property="description",
     *                 description="The description of the internal error",
     *                 @OA\Schema(type="string")
     *             ),
     *             @OA\Property(
     *                 property="judgehostlog",
     *                 description="The log of the judgehost",
     *                 @OA\Schema(type="string")
     *             ),
     *             @OA\Property(
     *                 property="disabled",
     *                 description="The object to disable in JSON format",
     *                 @OA\Schema(type="string")
     *             ),
     *             @OA\Property(
     *                 property="judgetaskid",
     *                 description="The ID of the judgeTask that was being worked on",
     *                 @OA\Schema(type="integer")
     *             )
     *         )
     *     )
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

        // The judgetaskid is allowed to be NULL.
        $judgeTaskId = $request->request->get('judgetaskid');
        $judging = NULL;
        if ($judgeTaskId) {
            /** @var JudgeTask $judgeTask */
            $judgeTask = $this->em->getRepository(JudgeTask::class)->findOneBy(['judgetaskid' => $judgeTaskId]);
            if ($judgeTask->getType() == JudgeTaskType::JUDGING_RUN) {
                $judgingId = $judgeTask->getJobId();
                /** @var Judging $judging */
                $judging = $this->em->getRepository(Judging::class)->findOneBy(['judgingid' => $judgingId]);
                $cid = $judging->getContest()->getCid();
            }
        }

        $disabled = $this->dj->jsonDecode($disabled);
        if (in_array($disabled['kind'], array('compile_script', 'compare_script', 'run_script'))) {
            $field_name = $disabled['kind'] . '_id';
            // Disable any outstanding judgetasks with the same script that have not been claimed yet.
            $this->em->getConnection()->executeUpdate(
                'UPDATE judgetask SET valid=0'
                . ' WHERE ' . $field_name . ' = :id'
                . ' AND hostname IS NULL',
                [
                    ':id' => $disabled[$field_name],
                ]
            );

            // Since these are the immutable executables, we need to map it to the mutable one first to make linking and
            // re-enabling possible.
            /** @var Executable $executable */
            $executable = $this->em->getRepository(Executable::class)
                ->findOneBy(['immutableExecutable' => $disabled[$field_name]]);
            if (!$executable) {
                // Race condition where the user changed the executable (hopefully for the better). Ignore.
                return;
            }
            $disabled['execid'] = $executable->getExecid();
            unset($disabled[$field_name]);
            $disabled['kind'] = 'executable';
        }

        // Group together duplicate internal errors.
        // Note that it may be good to be able to ignore fields here, e.g. judgingid with compile errors.
        $queryBuilder = $this->em->createQueryBuilder()
            ->from(InternalError::class, 'e')
            ->select('e')
            ->andWhere('e.description = :description')
            ->andWhere('e.disabled = :disabled')
            ->andWhere('e.status = :status')
            ->setParameter(':description', $description)
            ->setParameter(':disabled', $this->dj->jsonEncode($disabled))
            ->setParameter(':status', 'open')
            ->setMaxResults(1);

        /** @var InternalError $error */
        $error = $queryBuilder->getQuery()->getOneOrNullResult();

        if ($error) {
            // FIXME: in some cases it makes sense to extend the known information, e.g. the judgehostlog.
            return $error->getErrorid();
        }

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

        $error = new InternalError();
        $error
            ->setJudging($judgingId ? $this->em->getReference(Judging::class, $judgingId) : null)
            ->setContest($contest)
            ->setDescription($description)
            ->setJudgehostlog($judgehostlog)
            ->setTime(Utils::now())
            ->setDisabled($disabled);

        $this->em->persist($error);
        $this->em->flush();

        $this->dj->setInternalError($disabled, $contest, false);

        if (in_array($disabled['kind'], ['problem', 'language', 'judgehost']) && $judgingId) {
            // Give back judging if we have to.
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
                    ->setRejudging(null);

                $judging->getSubmission()->setJudgehost(null);

                // Give back judging, create a new one.
                $newJudging = new Judging();
                $newJudging
                    ->setContest($judging->getContest())
                    ->setSubmission($judging->getSubmission());
                $this->em->persist($newJudging);
                $this->em->flush();

                $this->dj->maybeCreateJudgeTasks($newJudging);
            });

            $this->dj->auditlog('judging', $judgingId, 'given back', null,
                                             $judging->getJudgehost()->getHostname(), $judging->getContest()->getCid());
        }
    }

    /**
     * Add a single judging to a given judging run
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     */
    private function addSingleJudgingRun(
        int $judgeTaskId,
        string $hostname,
        string $runResult,
        string $runTime,
        string $outputSystem,
        string $outputError,
        string $outputDiff,
        string $outputRun,
        string $metadata
    ) {
        $resultsRemap = $this->config->get('results_remap');
        $resultsPrio  = $this->config->get('results_prio');

        if (array_key_exists($runResult, $resultsRemap)) {
            $this->logger->info('JudgeTask %d remapping result %s -> %s',
                                [ $judgeTaskId, $runResult, $resultsRemap[$runResult] ]);
            $runResult = $resultsRemap[$runResult];
        }

        $this->em->transactional(function () use (
            $judgeTaskId,
            $runTime,
            $runResult,
            $outputSystem,
            $outputError,
            $outputDiff,
            $outputRun,
            $metadata
        ) {
            /** @var JudgingRun $judgingRun */
            $judgingRun = $this->em->getRepository(JudgingRun::class)->findOneBy(
                ['judgetaskid' => $judgeTaskId]);
            if ($judgingRun === null) {
                throw new BadRequestHttpException('Inconsistent data, no judging run known with this judgetaskid.');
            }
            $judgingRunOutput = new JudgingRunOutput();
            $judgingRun->setOutput($judgingRunOutput);
            $judgingRun
                ->setRunresult($runResult)
                ->setRuntime($runTime)
                ->setEndtime(Utils::now());
            $judgingRunOutput
                ->setOutputRun(base64_decode($outputRun))
                ->setOutputDiff(base64_decode($outputDiff))
                ->setOutputError(base64_decode($outputError))
                ->setOutputSystem(base64_decode($outputSystem))
                ->setMetadata(base64_decode($metadata));

            $judging = $judgingRun->getJudging();
            $this->maybeUpdateActiveJudging($judging);
            $this->em->flush();

            if ($judging->getValid()) {
                $this->eventLogService->log('judging_run', $judgingRun->getRunid(),
                                            EventLogService::ACTION_CREATE, $judging->getContest()->getCid());
            }
        });

        // Reload the testcase and judging, as EventLogService::log will clear the entity manager.
        // For the judging, also load in the submission and some of it's relations.
        /** @var JudgingRun $judgingRun */
        $judgingRun = $this->em->getRepository(JudgingRun::class)->findOneBy(
            ['judgetaskid' => $judgeTaskId]);
        $testCase = $judgingRun->getTestcase();
        $judging = $judgingRun->getJudging();

        // result of this judging_run has been stored. now check whether
        // we're done or if more testcases need to be judged.

        /** @var JudgingRun[] $runs */
        $runs = $this->em->createQueryBuilder()
            ->from(JudgeTask::class, 'jt')
            ->leftJoin(JudgingRun::class, 'jr', Join::WITH, 'jt.testcase_id = jr.testcase AND jr.judging = :judgingid')
            ->select('jr.runresult')
            ->andWhere('jt.jobid = :judgingid')
            ->andWhere('jr.judging = :judgingid')
            ->andWhere('jt.testcase_id = jr.testcase')
            ->orderBy('jt.judgetaskid')
            ->setParameter(':judgingid', $judging->getJudgingid())
            ->getQuery()
            ->getArrayResult();
        $runresults = array_column($runs, 'runresult');
        $hasNullResults = false;
        foreach ($runresults as $runresult) {
            if ($runresult === NULL) {
                $hasNullResults = true;
                break;
            }
        }

        $oldResult = $judging->getResult();

        if (($result = $this->submissionService->getFinalResult($runresults, $resultsPrio)) !== null) {
            // Lookup global lazy evaluation of results setting and possible problem specific override.
            $lazyEval    = $this->config->get('lazy_eval_results');
            $problemLazy = $judging->getSubmission()->getContestProblem()->getLazyEvalResults();
            if (isset($problemLazy)) {
                $lazyEval = $problemLazy;
            }

            $judging->setResult($result);

            // Only update if the current result is different from what we had before.
            // This should only happen when the old result was NULL.
            if ($oldResult !== $result) {
                if (!$hasNullResults || $lazyEval) {
                    // NOTE: setting endtime here determines in testcases_GET
                    // whether a next testcase will be handed out.
                    $judging->setEndtime(Utils::now());
                    $this->maybeUpdateActiveJudging($judging);
                }
                $this->em->flush();

                if ($oldResult !== null) {
                    throw new \BadMethodCallException('internal bug: the evaluated result changed during judging');
                }

                if ($lazyEval) {
                    // We don't want to continue on this problem, even if there's spare resources.
                    $this->em->getConnection()->executeUpdate(
                        'UPDATE judgetask SET valid=0, priority=:priority'
                        . ' WHERE jobid=:jobid'
                        . ' AND hostname IS NULL',
                        [
                            ':priority' => JudgeTask::PRIORITY_LOW,
                            ':jobid' => $judgingRun->getJudgingid(),
                        ]
                    );
                } else {
                    // Decrease priority of remaining unassigned judging runs.
                    $this->em->getConnection()->executeUpdate(
                        'UPDATE judgetask SET priority=:priority'
                        . ' WHERE jobid=:jobid'
                        . ' AND hostname IS NULL',
                        [
                            ':priority' => JudgeTask::PRIORITY_LOW,
                            ':jobid' => $judgingRun->getJudgingid(),
                        ]
                    );
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
                                                    $judging->getContest()->getCid());
                        $this->balloonService->updateBalloons($contest, $submission, $judging);
                    }
                }

                $this->dj->auditlog('judging', $judging->getJudgingid(), 'judged', $result, $hostname);

                $justFinished = true;
            }
        }

        // Send an event for an endtime update if not done yet.
        if ($judging->getValid() && !$hasNullResults && empty($justFinished)) {
            $this->eventLogService->log('judging', $judging->getJudgingid(),
                                        EventLogService::ACTION_UPDATE, $judging->getContest()->getCid());
        }
    }

    private function maybeUpdateActiveJudging(Judging $judging): void
    {
        if ($judging->getRejudging() !== null) {
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
                    ->andWhere('r.repeatedRejudging = :repeat_rejudgingid')
                    ->setParameter('repeat_rejudgingid', $rejudging->getRepeatedRejudging()->getRejudgingid())
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
                        ->leftJoin('s.rejudging', 'r')
                        ->leftJoin('s.team', 't')
                        ->select('j', 's', 'r', 't')
                        ->andWhere('j.rejudging = :rejudgingid')
                        ->setParameter('rejudgingid', $rejudging->getRejudgingid())
                        ->getQuery()
                        ->getResult();
                    $this->rejudgingService->createRejudging($rejudging->getReason(), $judgings,
                        false, $rejudging->getRepeat(), $rejudging->getRepeatedRejudging(), $skipped);
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
            ->andWhere('s.contest IN (:contestIds)')
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
                    ->andWhere('s.contest IN (:restrictionContestIds)')
                    ->setParameter(':restrictionContestIds', $restrictions['contest']);
            }

            if (isset($restrictions['problem'])) {
                $queryBuilder
                    ->andWhere('s.problem IN (:restrictionProblemIds)')
                    ->setParameter(':restrictionProblemIds', $restrictions['problem']);
            }

            if (isset($restrictions['language'])) {
                $queryBuilder
                    ->andWhere('s.language IN (:restrictionLanguageIds)')
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

    /**
     * Get files for a given type and id.
     * @Rest\Get("/get_files/{type}/{id}")
     * @Security("is_granted('ROLE_JURY') or is_granted('ROLE_JUDGEHOST')")
     * @param Request $request
     * @param string  $type
     * @param string  $id
     * @return array
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @OA\Response(
     *     response="200",
     *     description="The files for the submission, testcase or script.",
     *     @OA\Schema(ref="#/definitions/SourceCodeList")
     * )
     * @OA\Parameter(ref="#/parameters/id")
     */
    public function getFilesAction(string $type, string $id)
    {
        switch($type) {
            case 'source':
                return $this->getSourceFiles($id);
            case 'testcase':
                return $this->getTestcaseFiles($id);
            case 'compile':
            case 'run':
            case 'compare':
                return $this->getExecutableFiles($id);
            default:
                throw new BadRequestHttpException('Unknown type requested.');
        }
    }

    private function getSourceFiles(string $id) {
        $queryBuilder = $this->em->createQueryBuilder()
            ->from(SubmissionFile::class, 'f')
            ->select('f')
            ->andWhere('f.submission = :submitid')
            ->setParameter(':submitid', $id)
            ->orderBy('f.ranknumber');

        /** @var SubmissionFile[] $files */
        $files = $queryBuilder->getQuery()->getResult();

        if (empty($files)) {
            throw new NotFoundHttpException(sprintf('Source code for submission with ID \'%s\' not found', $id));
        }

        $result = [];
        foreach ($files as $file) {
            $result[]   = [
                'filename' => $file->getFilename(),
                'content' => base64_encode($file->getSourcecode()),
            ];
        }
        return $result;
    }

    private function getExecutableFiles(string $id) {
        $queryBuilder = $this->em->createQueryBuilder()
            ->from(ExecutableFile::class, 'f')
            ->select('f')
            ->andWhere('f.immutableExecutable = :immutable_execid')
            ->setParameter(':immutable_execid', $id)
            ->orderBy('f.rank');

        /** @var ExecutableFile[] $files */
        $files = $queryBuilder->getQuery()->getResult();

        if (empty($files)) {
            throw new NotFoundHttpException(sprintf('Files for immutable executable with ID \'%s\' not found', $id));
        }

        $result = [];
        foreach ($files as $file) {
            $result[]   = [
                'filename' => $file->getFilename(),
                'content' => base64_encode($file->getFileContent()),
                'is_executable' => $file->isExecutable(),
            ];
        }
        return $result;
    }

    private function getTestcaseFiles(string $id) {
        $queryBuilder = $this->em->createQueryBuilder()
            ->from(TestcaseContent::class, 'f')
            ->select('f.input, f.output')
            ->andWhere('f.tc_contentid = :tc_contentid')
            ->setParameter(':tc_contentid', $id);

        /** @var string[] $inout */
        $inout = $queryBuilder->getQuery()->getOneOrNullResult();

        if (empty($inout)) {
            throw new NotFoundHttpException(sprintf('Files for testcase_content with ID \'%s\' not found', $id));
        }

        $result = [];
        foreach (['input', 'output'] as $k) {
            $result[] = [
                'filename' => $k,
                'content' => base64_encode($inout[$k]),
            ];
        }
        return $result;
    }

    /**
     * Fetch work tasks.
     * @Rest\Post("/fetch_work")
     * @Security("is_granted('ROLE_JUDGEHOST')")
     */
    public function getJudgeTasksAction(Request $request): array
    {
        if (!$request->request->has('hostname')) {
            throw new BadRequestHttpException('Argument \'hostname\' is mandatory');
        }
        $hostname = $request->request->get('hostname');

        /** @var Judgehost $judgehost */
        $judgehost = $this->em->getRepository(Judgehost::class)->find($hostname);
        if (!$judgehost) {
            throw new BadRequestHttpException('Register yourself first. You are not known to us yet.');
        }

        // Update last seen of judgehost
        $judgehost->setPolltime(Utils::now());

        // TODO: Determine a good max batch size here. We may want to do something more elaborate like looking at
        // previous judgements of the same testcase and use median runtime as an indicator.
        $max_batchsize = 5;
        if ($request->request->has('max_batchsize')) {
            $max_batchsize = $request->request->get('max_batchsize');
        }

        /* Our main objective is to work on high priority work first while keeping the additional overhead of splitting
         * work across judgehosts (e.g. additional compilation) low.
         *
         * We follow the following high-level strategy here to assign work:
         * 1) If there's an unfinished job (e.g. a judging)
         *    - to which we already contributed, and
         *    - where the remaining JudgeTasks have a priority <= 0,
         *    then continue handing out JudgeTasks for this job.
         * 2) Determine highest priority level of outstanding JudgeTasks, so that we work on one of the most important work
         *    items.
         *    a) If there's an already started job to which we already contributed,
         *       then continue working on this job.
         *    b) Otherwise, if there's an unstarted job, hand out tasks from that job.
         *    c) Otherwise, contribute to an already started job even if we didn't contribute yet.

         * Note that there could potentially be races in the selection of work, but adding synchronization mechanisms is
         * more costly than starting a possible only second most important work item.
         */

        // This is case 1) from above: continue what we have started (if still important).
        // TODO: These queries would be much easier and less heavy on the DB with an extra table.
        $started_judgetaskids = array_column(
            $this->em
                ->createQueryBuilder()
                ->from(JudgeTask::class, 'jt')
                ->select('jt.jobid')
                ->andWhere('jt.hostname = :hostname')
                ->setParameter(':hostname', $hostname)
                ->groupBy('jt.jobid')
                ->getQuery()
                ->getArrayResult(),
            'jobid');
        if (!empty($started_judgetaskids)) {
            $queryBuilder = $this->em->createQueryBuilder();
            /** @var JudgeTask[] $judgetasks */
            $judgetasks = $queryBuilder
                ->from(JudgeTask::class, 'jt')
                ->select('jt')
                ->andWhere('jt.hostname IS NULL')
                ->andWhere('jt.valid = 1')
                ->andWhere('jt.priority <= :default_priority')
                ->andWhere($queryBuilder->expr()->In('jt.jobid', $started_judgetaskids))
                ->addOrderBy('jt.priority')
                ->addOrderBy('jt.judgetaskid')
                ->setParameter(':default_priority', JudgeTask::PRIORITY_DEFAULT)
                ->setMaxResults($max_batchsize)
                ->getQuery()
                ->getResult();
            if (!empty($judgetasks)) {
                return $this->serializeJudgeTasks($judgetasks, $hostname);
            }
        }

        // Determine highest priority level of outstanding JudgeTasks.
        $max_priority = $this->em
            ->createQueryBuilder()
            ->from(JudgeTask::class, 'jt')
            ->select('jt.priority')
            ->andWhere('jt.hostname IS NULL')
            ->andWhere('jt.valid = 1')
            ->addOrderBy('jt.priority')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($max_priority === null) {
            return [];
        }
        $max_priority = $max_priority['priority'];

        // This is case 2.a) from above: continue what we have started (if same priority as the current most important
        // judgetask).
        // TODO: We should merge this with the query above to reduce code duplication.
        if ($started_judgetaskids) {
            /** @var JudgeTask[] $judgetasks */
            $judgetasks = $this->em
                ->createQueryBuilder()
                ->from(JudgeTask::class, 'jt')
                ->select('jt')
                ->andWhere('jt.hostname IS NULL')
                ->andWhere('jt.valid = 1')
                ->andWhere('jt.priority = :max_priority')
                ->setParameter(':max_priority', $max_priority)
                ->andWhere($queryBuilder->expr()->In('jt.jobid', $started_judgetaskids))
                ->addOrderBy('jt.judgetaskid')
                ->setMaxResults($max_batchsize)
                ->getQuery()
                ->getResult();
            if (!empty($judgetasks)) {
                return $this->serializeJudgeTasks($judgetasks, $hostname);
            }
        }

        // This is case 2.b) from above: start something new.
        // First, we have to filter for unfinished jobs. This would be easier with a separate table storing the
        // job state.
        $started_judgetaskids = array_column(
            $this->em
                ->createQueryBuilder()
                ->from(JudgeTask::class, 'jt')
                ->select('jt.jobid')
                ->andWhere('jt.hostname IS NOT NULL')
                ->groupBy('jt.jobid')
                ->getQuery()
                ->getArrayResult(),
            'jobid');
        $queryBuilder = $this->em->createQueryBuilder();
        $queryBuilder
            ->from(JudgeTask::class, 'jt')
            ->join(Submission::class, 's', Join::WITH, 'jt.submitid = s.submitid')
            ->join('s.team', 't')
            ->select('jt')
            ->andWhere('jt.hostname IS NULL')
            ->andWhere('jt.valid = 1')
            ->andWhere('jt.priority = :max_priority')
            ->setParameter(':max_priority', $max_priority)
            ->addOrderBy('t.judging_last_started', 'ASC')
            ->addOrderBy('s.submittime', 'ASC')
            ->addOrderBy('s.submitid', 'ASC');
        if (!empty($started_judgetaskids)) {
            $queryBuilder
            ->andWhere($queryBuilder->expr()->notIn('jt.jobid', $started_judgetaskids));
        }
        /** @var JudgeTask[] $judgetasks */
        $judgetasks =
            $queryBuilder
            ->addOrderBy('jt.judgetaskid')
            ->setMaxResults($max_batchsize)
            ->getQuery()
            ->getResult();
        if (!empty($judgetasks)) {
            return $this->serializeJudgeTasks($judgetasks, $hostname);
        }

        // This is case 2.c) from above: contribute to a job someone else has started but we have not contributed yet.
        // We intentionally lift the restriction on priority in this case to get any high priority work.
        /** @var JudgeTask[] $judgetasks */
        $judgetasks = $this->em
            ->createQueryBuilder()
            ->from(JudgeTask::class, 'jt')
            ->select('jt')
            ->andWhere('jt.hostname IS NULL')
            ->andWhere('jt.valid = 1')
            ->addOrderBy('jt.priority')
            ->addOrderBy('jt.judgetaskid')
            ->setMaxResults($max_batchsize)
            ->getQuery()
            ->getResult();
        if (!empty($judgetasks)) {
            return $this->serializeJudgeTasks($judgetasks, $hostname);
        }
    }

    /** @param JudgeTask[] $judgeTasks */
    private function serializeJudgeTasks($judgeTasks, string $hostname): array
    {
        if (empty($judgeTasks)) {
            return [];
        }

        // Filter by submit_id.
        $submit_id = $judgeTasks[0]->getSubmitid();
        $judgetaskids = [];
        foreach ($judgeTasks as $judgeTask) {
           if ($judgeTask->getSubmitid() == $submit_id) {
               $judgetaskids[] = $judgeTask->getJudgetaskid();
           }
        }

        $numUpdated = $this->em->getConnection()->executeUpdate(
            'UPDATE judgetask SET hostname = :hostname WHERE hostname IS NULL AND valid = 1 AND judgetaskid IN (:ids)',
            [
                ':hostname' => $hostname,
                ':ids' => $judgetaskids,
            ],
            [
                ':ids' => Connection::PARAM_INT_ARRAY,
            ]
        );

        if ($numUpdated == 0) {
            // Bad luck, some other judgehost beat us to it.
            return [];
        }

        $now = Utils::now();
        // We got at least one, let's update the starttime of the corresponding judging if haven't done so in the past.
        $starttime_set = $this->em->getConnection()->executeUpdate(
            'UPDATE judging SET starttime = :starttime WHERE judgingid = :jobid AND starttime IS NULL',
            [
                ':starttime' => $now,
                ':jobid' => $judgeTasks[0]->getJobId(),
            ]
        );

        if ($starttime_set) {
            /** @var Submission $submission */
            $submission = $this->em->getRepository(Submission::class)->findOneBy(['submitid' => $submit_id]);
            $teamid = $submission->getTeam()->getTeamid();

            $this->em->getConnection()->executeUpdate(
                'UPDATE team SET judging_last_started = :starttime WHERE teamid = :teamid',
                [
                    ':starttime' => $now,
                    ':teamid' => $teamid,
                ]
            );
        }

        if ($numUpdated == sizeof($judgeTasks)) {
            // We got everything, let's ship it!
            return $judgeTasks;
        }

        // A bit unlucky, we only got partially the assigned work, so query what was assigned to us.
        $queryBuilder = $this->em->createQueryBuilder();
        $partialJudgeTaskIds = array_column(
            $queryBuilder
                ->from(JudgeTask::class, 'jt')
                ->select('jt.judgetaskid')
                ->andWhere('jt.hostname = :hostname')
                ->setParameter(':hostname', $hostname)
                ->andWhere($queryBuilder->expr()->In('jt.judgetaskid', $judgetaskids))
                ->getQuery()
                ->getArrayResult(),
            'judgetaskid');

        $partialJudgeTasks = [];
        foreach ($judgeTasks as $judgeTask) {
            if (in_array($judgeTask->getJudgetaskid(), $partialJudgeTaskIds)) {
                $partialJudgeTasks[] = $judgeTask;
            }
        }
        return $partialJudgeTasks;
    }
}
