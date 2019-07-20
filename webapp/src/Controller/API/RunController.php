<?php declare(strict_types=1);

namespace App\Controller\API;

use App\Entity\JudgingRun;
use App\Helpers\JudgingRunWrapper;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\Model;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Rest\Route("/api/v4/contests/{cid}/runs", defaults={ "_format" = "json" })
 * @Rest\Prefix("/api/contests/{cid}/runs")
 * @Rest\NamePrefix("run_")
 * @SWG\Tag(name="Runs")
 * @SWG\Parameter(ref="#/parameters/cid")
 * @SWG\Response(response="404", ref="#/definitions/NotFound")
 * @SWG\Response(response="401", ref="#/definitions/Unauthorized")
 */
class RunController extends AbstractRestController implements QueryObjectTransformer
{
    /**
     * @var string[]
     */
    protected $verdicts;

    public function __construct(EntityManagerInterface $entityManager, DOMJudgeService $DOMJudgeService, EventLogService $eventLogService)
    {
        parent::__construct($entityManager, $DOMJudgeService, $eventLogService);

        $verdictsConfig = $this->dj->getDomjudgeEtcDir() . '/verdicts.php';
        $this->verdicts = include $verdictsConfig;
    }

    /**
     * Get all the runs for this contest
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @IsGranted({"ROLE_JURY", "ROLE_JUDGEHOST", "ROLE_API_READER"})
     * @Rest\Get("")
     * @SWG\Response(
     *     response="200",
     *     description="Returns all the runs for this contest",
     *     @SWG\Schema(
     *         type="array",
     *         @SWG\Items(
     *             allOf={
     *                 @SWG\Schema(ref=@Model(type=JudgingRun::class)),
     *                 @SWG\Schema(ref="#/definitions/RunExtraFields")
     *             }
     *         )
     *     )
     * )
     * @SWG\Parameter(ref="#/parameters/idlist")
     * @SWG\Parameter(
     *     name="first_id",
     *     in="query",
     *     type="string",
     *     description="Only show runs starting from this ID"
     * )
     * @SWG\Parameter(
     *     name="last_id",
     *     in="query",
     *     type="string",
     *     description="Only show runs until this ID"
     * )
     * @SWG\Parameter(
     *     name="judging_id",
     *     in="query",
     *     type="string",
     *     description="Only show runs for this judgement"
     * )
     * @SWG\Parameter(
     *     name="limit",
     *     in="query",
     *     type="integer",
     *     description="Limit the number of returned runs to this amount"
     * )
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function listAction(Request $request)
    {
        return parent::performListAction($request);
    }

    /**
     * Get the given run for this contest
     * @param Request $request
     * @param string $id
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @IsGranted({"ROLE_JURY", "ROLE_JUDGEHOST", "ROLE_API_READER"})
     * @Rest\Get("/{id}")
     * @SWG\Response(
     *     response="200",
     *     description="Returns the given run for this contest",
     *     @SWG\Schema(
     *         allOf={
     *             @SWG\Schema(ref=@Model(type=JudgingRun::class)),
     *             @SWG\Schema(ref="#/definitions/RunExtraFields")
     *         }
     *     )
     * )
     * @SWG\Parameter(ref="#/parameters/id")
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
            ->from(JudgingRun::class, 'jr')
            ->leftJoin('jr.judging', 'j')
            ->leftJoin('jr.testcase', 'tc')
            ->leftJoin('j.submission', 's')
            ->leftJoin('j.contest', 'c')
            ->select('jr, j, tc, c')
            ->andWhere('j.cid = :cid')
            ->setParameter(':cid', $this->getContestId($request));

        if ($request->query->has('first_id')) {
            $queryBuilder
                ->andWhere('jr.runid >= :first_id')
                ->setParameter(':first_id', $request->query->get('first_id'));
        }

        if ($request->query->has('last_id')) {
            $queryBuilder
                ->andWhere('jr.runid = :last_id')
                ->setParameter(':last_id', $request->query->get('last_id'));
        }

        if ($request->query->has('judging_id')) {
            $queryBuilder
                ->andWhere('jr.judgingid = :judging_id')
                ->setParameter(':judging_id', $request->query->get('judging_id'));
        }

        if ($request->query->has('limit')) {
            $queryBuilder->setMaxResults($request->query->getInt('limit'));
        }

        // If an ID has not been given directly, only show runs before contest end
        if (!$request->attributes->has('id') && !$request->query->has('ids')) {
            $queryBuilder
                ->andWhere('s.submittime < c.endtime')
                ->andWhere('j.rejudgingid IS NULL OR j.valid = 1');
            if ($this->dj->dbconfig_get('verification_required', false)) {
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
        return 'jr.runid';
    }

    /**
     * @inheritdoc
     */
    public function transformObject($object)
    {
        /** @var JudgingRun $judgingRun */
        $judgingRun      = $object;
        $judgementTypeId = $judgingRun->getRunresult() ? $this->verdicts[$judgingRun->getRunresult()] : null;
        return new JudgingRunWrapper($judgingRun, $judgementTypeId);
    }
}
