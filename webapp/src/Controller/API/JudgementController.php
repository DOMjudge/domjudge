<?php declare(strict_types=1);

namespace App\Controller\API;

use App\Entity\Judging;
use App\Helpers\JudgingWrapper;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\Model;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Rest\Route("/api/v4/contests/{cid}/judgements", defaults={ "_format" = "json" })
 * @Rest\Prefix("/api/contests/{cid}/judgements")
 * @Rest\NamePrefix("judgement_")
 * @SWG\Tag(name="Judgements")
 * @SWG\Parameter(ref="#/parameters/cid")
 * @SWG\Response(response="404", ref="#/definitions/NotFound")
 * @SWG\Response(response="401", ref="#/definitions/Unauthorized")
 */
class JudgementController extends AbstractRestController implements QueryObjectTransformer
{
    /**
     * @var string[]
     */
    protected $verdicts;

    public function __construct(
        EntityManagerInterface $entityManager,
        DOMJudgeService $DOMJudgeService,
        ConfigurationService $config,
        EventLogService $eventLogService
    ) {
        parent::__construct($entityManager, $DOMJudgeService, $config, $eventLogService);

        $verdictsConfig = $this->dj->getDomjudgeEtcDir() . '/verdicts.php';
        $this->verdicts = include $verdictsConfig;
    }

    /**
     * Get all the judgements for this contest
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @Security("is_granted('ROLE_JURY') or is_granted('ROLE_TEAM') or is_granted('ROLE_JUDGEHOST') or is_granted('ROLE_API_READER')")
     * @Rest\Get("")
     * @SWG\Response(
     *     response="200",
     *     description="Returns all the judgements for this contest",
     *     @SWG\Schema(
     *         type="array",
     *         @SWG\Items(
     *             allOf={
     *                 @SWG\Schema(ref=@Model(type=Judging::class)),
     *                 @SWG\Schema(ref="#/definitions/JudgementExtraFields")
     *             }
     *         )
     *     )
     * )
     * @SWG\Parameter(ref="#/parameters/idlist")
     * @SWG\Parameter(ref="#/parameters/strict")
     * @SWG\Parameter(
     *     name="result",
     *     in="query",
     *     type="string",
     *     description="Only show judgements with the given result"
     * )
     * @SWG\Parameter(
     *     name="submission_id",
     *     in="query",
     *     type="string",
     *     description="Only show judgements for the given submission"
     * )
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function listAction(Request $request)
    {
        return parent::performListAction($request);
    }

    /**
     * Get the given judgement for this contest
     * @param Request $request
     * @param string $id
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @Security("is_granted('ROLE_JURY') or is_granted('ROLE_TEAM') or is_granted('ROLE_JUDGEHOST') or is_granted('ROLE_API_READER')")
     * @Rest\Get("/{id}")
     * @SWG\Response(
     *     response="200",
     *     description="Returns the given judgement for this contest",
     *     @SWG\Schema(
     *         allOf={
     *             @SWG\Schema(ref=@Model(type=Judging::class)),
     *             @SWG\Schema(ref="#/definitions/JudgementExtraFields")
     *         }
     *     )
     * )
     * @SWG\Parameter(ref="#/parameters/id")
     * @SWG\Parameter(ref="#/parameters/strict")
     */
    public function singleAction(Request $request, string $id)
    {
        return parent::performSingleAction($request, $id);
    }

    /**
     * @inheritdoc
     * @throws \Exception
     */
    protected function getQueryBuilder(Request $request): QueryBuilder
    {
        $queryBuilder = $this->em->createQueryBuilder()
            ->from(Judging::class, 'j')
            ->select('j, c, s, MAX(jr.runtime) AS maxruntime')
            ->leftJoin('j.contest', 'c')
            ->leftJoin('j.submission', 's')
            ->leftJoin('j.rejudging', 'r')
            ->leftJoin('j.runs', 'jr')
            ->andWhere('j.cid = :cid')
            ->setParameter(':cid', $this->getContestId($request))
            ->groupBy('j.judgingid')
            ->orderBy('j.judgingid');

        $roleAllowsVisibility = $this->dj->checkrole('api_reader')
            || $this->dj->checkrole('judgehost');
        if ($request->query->has('result')) {
            $queryBuilder
                ->andWhere('j.result = :result')
                ->setParameter(':result', $request->query->get('result'));
        } elseif (!$roleAllowsVisibility) {
            $queryBuilder->andWhere('j.result IS NOT NULL');
        }

        if (!$roleAllowsVisibility) {
            $queryBuilder
                ->andWhere('s.teamid = :team')
                ->setParameter(':team', $this->dj->getUser()->getTeamid());
        }

        if ($request->query->has('submission_id')) {
            $queryBuilder
                ->andWhere('j.submitid = :submission')
                ->setParameter(':submission', $request->query->get('submission_id'));
        }

        $specificJudgingRequested = $request->attributes->has('id')
            || $request->query->has('ids');
        // If we don't have correct permissions or didn't request a specific
        // judging (necessary for the event log), then exclude some judgings:
        if (!$roleAllowsVisibility && !$specificJudgingRequested) {
            $queryBuilder
                ->andWhere('s.submittime < c.endtime')
                ->andWhere('j.valid = 1');
            if ($this->config->get('verification_required')) {
                $queryBuilder->andWhere('j.verified = 1');
            }
        }

        return $queryBuilder;
    }

    /**
     * @inheritdoc
     */
    protected function getIdField(): string
    {
        return 'j.judgingid';
    }

    /**
     * @inheritdoc
     */
    public function transformObject($object)
    {
        /** @var Judging $judging */
        $judging         = $object[0];
        $maxRunTime      = $object['maxruntime'] === null ? null : (float)$object['maxruntime'];
        $judgementTypeId = $judging->getResult() ? $this->verdicts[$judging->getResult()] : null;
        return new JudgingWrapper($judging, $maxRunTime, $judgementTypeId);
    }
}
