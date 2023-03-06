<?php declare(strict_types=1);

namespace App\Controller\API;

use App\Entity\Judging;
use App\Helpers\JudgingWrapper;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\QueryBuilder;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Annotations as OA;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Rest\Route("/")
 * @OA\Tag(name="Judgements")
 * @OA\Parameter(ref="#/components/parameters/cid")
 * @OA\Parameter(ref="#/components/parameters/strict")
 * @OA\Response(response="404", ref="#/components/responses/NotFound")
 * @OA\Response(response="401", ref="#/components/responses/Unauthenticated")
 * @OA\Response(response="400", ref="#/components/responses/InvalidResponse")
 * @OA\Response(response="403", ref="#/components/responses/Unauthorized")
 */
class JudgementController extends AbstractRestController implements QueryObjectTransformer
{
    /**
     * @var string[]
     */
    protected array $verdicts;

    public function __construct(
        EntityManagerInterface $entityManager,
        DOMJudgeService $DOMJudgeService,
        ConfigurationService $config,
        EventLogService $eventLogService
    ) {
        parent::__construct($entityManager, $DOMJudgeService, $config, $eventLogService);

        $verdictsConfig = $this->dj->getDomjudgeEtcDir() . '/verdicts.php';
        $this->verdicts = include $verdictsConfig;
        $this->verdicts['aborted'] = 'JE'; /* happens for aborted judgings */
    }

    /**
     * Get all the judgements for this contest.
     * @Security("is_granted('ROLE_JURY') or is_granted('ROLE_TEAM') or is_granted('ROLE_JUDGEHOST') or is_granted('ROLE_API_READER')")
     * @Rest\Get("contests/{cid}/judgements")
     * @Rest\Get("judgements")
     * @OA\Response(
     *     response="200",
     *     description="Returns all the judgements for this contest",
     *     @OA\JsonContent(
     *         type="array",
     *         @OA\Items(
     *             allOf={
     *                 @OA\Schema(ref=@Model(type=Judging::class)),
     *                 @OA\Schema(ref="#/components/schemas/JudgementExtraFields")
     *             }
     *         )
     *     )
     * )
     * @OA\Parameter(ref="#/components/parameters/idlist")
     * @OA\Parameter(
     *     name="result",
     *     in="query",
     *     description="Only show judgements with the given result",
     *     @OA\Schema(type="string")
     * )
     * @OA\Parameter(
     *     name="submission_id",
     *     in="query",
     *     description="Only show judgements for the given submission",
     *     @OA\Schema(type="string")
     * )
     * @throws NonUniqueResultException
     */
    public function listAction(Request $request): Response
    {
        return parent::performListAction($request);
    }

    /**
     * Get the given judgement for this contest.
     * @throws NonUniqueResultException
     * @Security("is_granted('ROLE_JURY') or is_granted('ROLE_TEAM') or is_granted('ROLE_JUDGEHOST') or is_granted('ROLE_API_READER')")
     * @Rest\Get("contests/{cid}/judgements/{id<\d+>}")
     * @Rest\Get("judgements/{id<\d+>}")
     * @OA\Response(
     *     response="200",
     *     description="Returns the given judgement for this contest",
     *     @OA\JsonContent(
     *         allOf={
     *             @OA\Schema(ref=@Model(type=Judging::class)),
     *             @OA\Schema(ref="#/components/schemas/JudgementExtraFields")
     *         }
     *     )
     * )
     * @OA\Parameter(ref="#/components/parameters/id")
     */
    public function singleAction(Request $request, string $id): Response
    {
        return parent::performSingleAction($request, $id);
    }

    protected function getQueryBuilder(Request $request): QueryBuilder
    {
        $queryBuilder = $this->em->createQueryBuilder()
            ->from(Judging::class, 'j')
            ->select('j, c, s, MAX(jr.runtime) AS maxruntime')
            ->leftJoin('j.contest', 'c')
            ->leftJoin('j.submission', 's')
            ->leftJoin('j.rejudging', 'r')
            ->leftJoin('j.runs', 'jr')
            ->groupBy('j.judgingid')
            ->orderBy('j.judgingid');

        if ($request->attributes->has('cid')) {
            $queryBuilder
                ->andWhere('j.contest = :cid')
                ->setParameter('cid', $this->getContestId($request));
        }

        $roleAllowsVisibility = $this->dj->checkrole('api_reader')
            || $this->dj->checkrole('judgehost');
        if ($request->query->has('result')) {
            $queryBuilder
                ->andWhere('j.result = :result')
                ->setParameter('result', $request->query->get('result'));
        } elseif (!$roleAllowsVisibility) {
            $queryBuilder->andWhere('j.result IS NOT NULL');
        }

        if (!$roleAllowsVisibility) {
            $queryBuilder
                ->andWhere('s.team = :team')
                ->setParameter('team', $this->dj->getUser()->getTeam());
        }

        if ($request->query->has('submission_id')) {
            $queryBuilder
                ->andWhere('j.submission = :submission')
                ->setParameter('submission', $request->query->get('submission_id'));
        }

        $specificJudgingRequested = $request->attributes->has('id')
            || $request->query->has('ids');
        // Only include invalid or too late submissions if the role allows it
        // and we request these specific submissions.
        if (!($roleAllowsVisibility && $specificJudgingRequested)) {
            $queryBuilder
                ->andWhere('s.submittime < c.endtime')
                ->andWhere('j.valid = 1');
            if ($this->config->get('verification_required')) {
                $queryBuilder->andWhere('j.verified = 1');
            }
        }

        return $queryBuilder;
    }

    protected function getIdField(): string
    {
        return 'j.judgingid';
    }

    public function transformObject($object): JudgingWrapper
    {
        /** @var Judging $judging */
        $judging         = $object[0];
        $maxRunTime      = $object['maxruntime'] === null ? null : (float)$object['maxruntime'];
        $judgementTypeId = $judging->getResult() ? $this->verdicts[$judging->getResult()] : null;
        return new JudgingWrapper($judging, $maxRunTime, $judgementTypeId);
    }
}
