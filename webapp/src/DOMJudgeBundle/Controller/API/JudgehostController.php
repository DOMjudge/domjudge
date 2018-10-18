<?php declare(strict_types=1);

namespace DOMJudgeBundle\Controller\API;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use DOMJudgeBundle\Entity\Contest;
use DOMJudgeBundle\Entity\Executable;
use DOMJudgeBundle\Entity\InternalError;
use DOMJudgeBundle\Entity\Judgehost;
use DOMJudgeBundle\Entity\Judging;
use DOMJudgeBundle\Entity\Submission;
use DOMJudgeBundle\Entity\User;
use DOMJudgeBundle\Service\DOMJudgeService;
use DOMJudgeBundle\Service\EventLogService;
use DOMJudgeBundle\Utils\Utils;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\FOSRestController;
use Nelmio\ApiDocBundle\Annotation\Model;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @Rest\Route("/api/v4/judgehosts", defaults={ "_format" = "json" })
 * @Rest\Prefix("/api/judgehosts")
 * @Rest\NamePrefix("judgehost_")
 * @SWG\Tag(name="Judgehosts")
 */
class JudgehostController extends FOSRestController
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
     * @var EventLogService
     */
    protected $eventLogService;

    /**
     * JudgehostController constructor.
     * @param EntityManagerInterface $entityManager
     * @param DOMJudgeService $DOMJudgeService
     */
    public function __construct(EntityManagerInterface $entityManager, DOMJudgeService $DOMJudgeService, EventLogService $eventLogService)
    {
        $this->entityManager   = $entityManager;
        $this->DOMJudgeService = $DOMJudgeService;
        $this->eventLogService = $eventLogService;
    }

    /**
     * Get judgehosts
     * @Rest\Get("")
     * @Security("has_role('ROLE_JURY')")
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
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->from('DOMJudgeBundle:Judgehost', 'j')
            ->select('j');

        if ($request->query->has('hostname')) {
            $queryBuilder
                ->where('j.hostname = :hostname')
                ->setParameter(':hostname', $request->query->get('hostname'));
        }

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * Add a new judgehost to the list of judgehosts. Also restarts (and returns) unfinished judgings.
     * @Rest\Post("")
     * @Security("has_role('ROLE_JUDGEHOST')")
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
        $judgehost = $this->entityManager->createQueryBuilder()
            ->from('DOMJudgeBundle:Judgehost', 'j')
            ->select('j')
            ->where('j.hostname = :hostname')
            ->setParameter(':hostname', $hostname)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$judgehost) {
            $judgehost = new Judgehost();
            $judgehost->setHostname($hostname);
            $this->entityManager->persist($judgehost);
            $this->entityManager->flush();
        }

        // If there are any unfinished judgings in the queue in my name, they will not be finished. Give them back.
        /** @var Judging[] $judgings */
        $judgings = $this->entityManager->createQueryBuilder()
            ->from('DOMJudgeBundle:Judging', 'j')
            ->innerJoin('j.judgehost', 'jh')
            ->leftJoin('j.rejudging', 'r')
            ->select('j')
            ->where('jh.hostname = :hostname')
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
                'cid' => $judging->getCid(),
            ];
        }, $judgings);
    }

    /**
     * Update the configuration of the given judgehost
     * @Rest\Put("/{hostname}")
     * @Security("has_role('ROLE_JUDGEHOST')")
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
     * @param string $hostname
     * @return array
     */
    public function updateJudgeHostAction(Request $request, string $hostname)
    {
        if (!$request->request->has('active')) {
            throw new BadRequestHttpException('Argument \'active\' is mandatory');
        }

        $judgehost = $this->entityManager->getRepository(Judgehost::class)->find($hostname);
        if ($judgehost) {
            $judgehost->setActive($request->request->getBoolean('active'));
            $this->entityManager->flush();
        }

        return [$judgehost];
    }

    /**
     * Get the next judging for the given judgehost
     * @Rest\Post("/next-judging/{hostname}")
     * @Security("has_role('ROLE_JUDGEHOST')")
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
     */
    public function getNextJudgingAction(string $hostname)
    {
        /** @var Judgehost $judgehost */
        $judgehost = $this->entityManager->getRepository(Judgehost::class)->find($hostname);
        if (!$judgehost) {
            return '';
        }

        // Update last seen of judgehost
        $judgehost->setPolltime(Utils::now());
        $this->entityManager->flush();

        // If this judgehost is not active, there's nothing to do
        if (!$judgehost->getActive()) {
            return '';
        }

        // Get all active contests
        $contests   = $this->DOMJudgeService->getCurrentContests();
        $contestIds = array_map(function (Contest $contest) {
            return $contest->getCid();
        }, $contests);

        // If there are no active contests, there is nothing to do
        if (empty($contestIds)) {
            return '';
        }

        // Determine all viable submissions
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->from('DOMJudgeBundle:Submission', 's')
            ->join('s.team', 't')
            ->join('s.language', 'l')
            ->join('s.contest_problem', 'cp')
            ->join('s.problem', 'p')
            ->join('s.contest', 'c')
            ->leftJoin('s.rejudging', 'r')
            ->select('s, t, l, cp, p, r')
            ->where('s.judgehost IS NULL')
            ->andWhere('s.cid IN (:contestIds)')
            ->setParameter(':contestIds', $contestIds)
            ->andWhere('l.allow_submit = 1')
            ->andWhere('cp.allow_judge = 1')
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

            if (isset($restrictions['rejudge_own']) && (bool)$restrictions['rejudge_own'] == false) {
                $queryBuilder
                    ->leftJoin('s.judgings', 'j', Join::WITH, 'j.judgehost = :judgehost')
                    ->andWhere('j.judgehost IS NULL')
                    ->setParameter(':judgehost', $judgehost->getHostname());
            }
        }

        /** @var Submission[] $submissions */
        $submissions = $queryBuilder->getQuery()->getResult();

        $numUpdated = 0;

        // Pick first submission
        foreach ($submissions as $submission) {
            // update exactly one submission with our judgehost name
            // Note: this might still return 0 if another judgehost beat us to it
            // We do this directly as an SQL query so we can get the number of affected rows
            $numUpdated = $this->entityManager->getConnection()->executeUpdate(
                'UPDATE submission SET judgehost = :judgehost WHERE submitid = :submitid AND judgehost IS NULL',
                [':judgehost' => $judgehost->getHostname(), ':submitid' => $submission->getSubmitid()]
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
        $this->entityManager->flush();

        // Build up result
        $result = [
            'submitid' => $submission->getSubmitid(),
            'cid' => $submission->getCid(),
            'teamid' => $submission->getTeamid(),
            'probid' => $submission->getProbid(),
            'langid' => $submission->getLangid(),
            'rejudgingid' => $submission->getRejudgingid(),
            'entry_point' => $submission->getEntryPoint(),
            'origsubmitid' => $submission->getOrigsubmitid(),
            'maxruntime' => Utils::roundedFloat($submission->getProblem()->getTimelimit() * $submission->getLanguage()->getTimeFactor(), 6),
            'memlimit' => $submission->getProblem()->getMemlimit(),
            'outputlimit' => $submission->getProblem()->getOutputlimit(),
            'run' => $submission->getProblem()->getSpecialRun(),
            'compare' => $submission->getProblem()->getSpecialCompare(),
            'compare_args' => $submission->getProblem()->getSpecialCompareArgs(),
            'compile_script' => $submission->getLanguage()->getCompileScript()
        ];

        // Merge defaults
        if (empty($result['memlimit'])) {
            $result['memlimit'] = $this->DOMJudgeService->dbconfig_get('memory_limit');
        }
        if (empty($result['outputlimit'])) {
            $result['outputlimit'] = $this->DOMJudgeService->dbconfig_get('output_limit');
        }
        if (empty($result['compare'])) {
            $result['compare'] = $this->DOMJudgeService->dbconfig_get('default_compare');
        }
        if (empty($result['run'])) {
            $result['run'] = $this->DOMJudgeService->dbconfig_get('default_run');
        }

        // Add executable MD5's
        $compareExecutable        = $this->entityManager->getRepository(Executable::class)->findOneBy(['execid' => $result['compare']]);
        $result['compare_md5sum'] = $compareExecutable ? $compareExecutable->getMd5sum() : null;
        $runExecutable            = $this->entityManager->getRepository(Executable::class)->findOneBy(['execid' => $result['run']]);
        $result['run_md5sum']     = $runExecutable ? $runExecutable->getMd5sum() : null;

        if (!empty($result['compile_script'])) {
            $compileExecutable               = $this->entityManager->getRepository(Executable::class)->findOneBy(['execid' => $result['compile_script']]);
            $result['compile_script_md5sum'] = $compileExecutable ? $compileExecutable->getMd5sum() : null;
        }

        // Determine previous judging for rejudgings
        $isRejudge       = isset($result['rejudgingid']);
        $previousJudging = null;
        if ($isRejudge) {
            // FIXME: what happens if there is no valid judging?
            $previousJudging = $this->entityManager->getRepository(Judging::class)
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
            $juryMembers = $this->entityManager->createQueryBuilder()
                ->from('DOMJudgeBundle:User', 'u')
                ->select('u')
                ->join('u.team', 't')
                ->join('t.submissions', 's')
                ->where('s.submitid = :submitid')
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
        $this->entityManager->transactional(function () use (
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

            $this->entityManager->persist($judging);
            $this->entityManager->flush();

            // Log the judging create event, but only if we are not doing a rejudging
            if (!$isRejudge) {
                $this->eventLogService->log('judging', $judging->getJudgingid(), 'create', $result['cid']);
            }

            $result['judgingid'] = $judging->getJudgingid();
        });

        return $result;
    }

    /**
     * Internal error reporting (back from judgehost)
     *
     * @Rest\Post("/internal-error")
     * @Security("has_role('ROLE_JUDGEHOST')")
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
     */
    public function internalErrorAction(Request $request)
    {
        $required = ['description', 'judgehostlog', 'disabled'];
        foreach ($required as $argument) {
            if (!$request->request->has($argument)) {
                throw new BadRequestHttpException(sprintf('Argument \'%s\' is mandatory', $argument));
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
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->from('DOMJudgeBundle:InternalError', 'e')
            ->select('e')
            ->where('e.description = :description')
            ->andWhere('e.disabled = :disabled')
            ->andWhere('e.status = :status')
            ->setParameter(':description', $description)
            ->setParameter(':disabled', $disabled)
            ->setParameter(':status', 'open')
            ->setMaxResults(1);

        if ($cid) {
            $queryBuilder
                ->andWhere('e.cid = :cid')
                ->setParameter(':cid', $cid);
        }

        /** @var InternalError $error */
        $error = $queryBuilder->getQuery()->getOneOrNullResult();

        if ($error) {
            // FIXME: in some cases it makes sense to extend the known information, e.g. the judgehostlog
            return $error->getErrorid();
        }

        $error = new InternalError();
        $error
            ->setJudgingid($judgingId)
            ->setCid($cid)
            ->setDescription($description)
            ->setJudgehostlog($judgehostlog)
            ->setTime(Utils::now())
            ->setDisabled($disabled);

        $this->entityManager->persist($error);
        $this->entityManager->flush();

        $disabled = $this->DOMJudgeService->jsonDecode($disabled);

        $this->DOMJudgeService->setInternalError($disabled, $cid, false);

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
        $judging = $this->entityManager->getRepository(Judging::class)->find($judgingId);
        if ($judging) {
            $this->entityManager->transactional(function () use ($judging) {
                $judging
                    ->setValid(false)
                    ->setRejudgingid(null);

                $judging->getSubmission()->setJudgehost(null);
            });

            $this->DOMJudgeService->auditlog('judging', $judgingId, 'given back', null, $judging->getJudgehost()->getHostname(),
                                             $judging->getCid());
        }
    }
}
