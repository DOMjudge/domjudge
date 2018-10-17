<?php declare(strict_types=1);

namespace DOMJudgeBundle\Controller\API;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use DOMJudgeBundle\Entity\Judging;
use DOMJudgeBundle\Helpers\JudgingWrapper;
use DOMJudgeBundle\Service\DOMJudgeService;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\Model;
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

    public function __construct(EntityManagerInterface $entityManager, DOMJudgeService $DOMJudgeService, string $rootDir)
    {
        parent::__construct($entityManager, $DOMJudgeService);

        global $VERDICTS;
        $dir          = realpath($rootDir . '/../../etc/');
        $commonConfig = $dir . '/common-config.php';
        require_once $commonConfig;

        /** @var string[] $VERDICTS */
        $this->verdicts = $VERDICTS;
    }

    /**
     * Get all the judgements for this contest
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @Security("has_role('ROLE_JURY') or has_role('ROLE_TEAM') or has_role('ROLE_JUDGEHOST')")
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
     * @Security("has_role('ROLE_JURY') or has_role('ROLE_TEAM') or has_role('ROLE_JUDGEHOST')")
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
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->from('DOMJudgeBundle:Judging', 'j')
            ->select('j, c, MAX(jr.runtime) AS maxruntime')
            ->leftJoin('j.contest', 'c')
            ->leftJoin('j.submission', 's')
            ->leftJoin('j.rejudging', 'r')
            ->leftJoin('j.runs', 'jr')
            ->andWhere('j.cid = :cid')
            ->setParameter(':cid', $this->getContestId($request))
            ->groupBy('j.judgingid')
            ->orderBy('j.judgingid');

        if ($request->query->has('result')) {
            $queryBuilder
                ->andWhere('j.result = :result')
                ->setParameter(':result', $request->query->get('result'));
        } elseif (!($this->DOMJudgeService->checkrole('jury') || $this->DOMJudgeService->checkrole('judgehost'))) {
            $queryBuilder->andWhere('j.result IS NOT NULL');
        }

        if (!($this->DOMJudgeService->checkrole('jury') || $this->DOMJudgeService->checkrole('judgehost'))) {
            $queryBuilder
                ->andWhere('s.teamid = :team')
                ->setParameter(':team', $this->DOMJudgeService->getUser()->getTeamid());
        }

        if ($request->query->has('submission_id')) {
            $queryBuilder
                ->andWhere('j.submitid = :submission')
                ->setParameter(':submission', $request->query->get('submission_id'));
        }

        // If one or more ID's are not given directly or we do not have the correct permissions, only show judgements before contest end
        if (!($this->DOMJudgeService->checkrole('jury') || $this->DOMJudgeService->checkrole('judgehost')) || !($request->attributes->has('id') || $request->query->has('ids'))) {
            $queryBuilder
                ->andWhere('s.submittime < c.endtime')
                ->andWhere('j.rejudgingid IS NULL OR j.valid = 1');
            if ($this->DOMJudgeService->dbconfig_get('verification_required', false)) {
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
