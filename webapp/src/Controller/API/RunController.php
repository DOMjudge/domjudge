<?php declare(strict_types=1);

namespace App\Controller\API;

use App\DataTransferObject\JudgingRunWrapper;
use App\Entity\JudgingRun;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\QueryBuilder;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * @extends AbstractRestController<JudgingRun, JudgingRunWrapper>
 */
#[Rest\Route('/contests/{cid}/runs')]
#[OA\Tag(name: 'Runs')]
#[OA\Parameter(ref: '#/components/parameters/cid')]
#[OA\Parameter(ref: '#/components/parameters/strict')]
#[OA\Response(ref: '#/components/responses/InvalidResponse', response: 400)]
#[OA\Response(ref: '#/components/responses/Unauthenticated', response: 401)]
#[OA\Response(ref: '#/components/responses/Unauthorized', response: 403)]
#[OA\Response(ref: '#/components/responses/NotFound', response: 404)]
class RunController extends AbstractRestController implements QueryObjectTransformer
{
    /**
     * @var string[]
     */
    protected readonly array $verdicts;

    public function __construct(
        EntityManagerInterface $entityManager,
        DOMJudgeService $DOMJudgeService,
        ConfigurationService $config,
        EventLogService $eventLogService
    ) {
        parent::__construct($entityManager, $DOMJudgeService, $config,
            $eventLogService);

        $this->verdicts = $this->dj->getVerdicts();
    }

    /**
     * Get all the runs for this contest.
     * @throws NonUniqueResultException
     */
    #[IsGranted(new Expression("is_granted('ROLE_JURY') or is_granted('ROLE_JUDGEHOST') or is_granted('ROLE_API_READER')"))]
    #[Rest\Get('')]
    #[OA\Response(
        response: 200,
        description: 'Returns all the runs for this contest',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: JudgingRunWrapper::class))
        )
    )]
    #[OA\Parameter(ref: '#/components/parameters/idlist')]
    #[OA\Parameter(
        name: 'first_id',
        description: 'Only show runs starting from this ID',
        in: 'query',
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'last_id',
        description: 'Only show runs until this ID',
        in: 'query',
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'judging_id',
        description: 'Only show runs for this judgement',
        in: 'query',
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'limit',
        description: 'Limit the number of returned runs to this amount',
        in: 'query',
        schema: new OA\Schema(type: 'integer')
    )]
    public function listAction(Request $request): Response
    {
        return parent::performListAction($request);
    }

    /**
     * Get the given run for this contest.
     * @throws NonUniqueResultException
     */
    #[IsGranted(new Expression("is_granted('ROLE_JURY') or is_granted('ROLE_JUDGEHOST') or is_granted('ROLE_API_READER')"))]
    #[Rest\Get('/{id<\d+>}')]
    #[OA\Response(
        response: 200,
        description: 'Returns the given run for this contest',
        content: new OA\JsonContent(ref: new Model(type: JudgingRunWrapper::class))
    )]
    #[OA\Parameter(ref: '#/components/parameters/id')]
    public function singleAction(Request $request, string $id): Response
    {
        return parent::performSingleAction($request, $id);
    }

    protected function getQueryBuilder(Request $request): QueryBuilder
    {
        $queryBuilder = $this->em->createQueryBuilder()
            ->from(JudgingRun::class, 'jr')
            ->leftJoin('jr.judging', 'j')
            ->leftJoin('jr.testcase', 'tc')
            ->leftJoin('j.submission', 's')
            ->leftJoin('j.contest', 'c')
            ->select('jr, j, tc, c')
            ->andWhere('j.contest = :cid')
            // With the new judgehost API we pre-create the judging_runs; only expose those who correspond to a real run
            // on a judgehost.
            ->andWhere('jr.endtime IS NOT NULL')
            ->setParameter('cid', $this->getContestId($request));

        if ($request->query->has('first_id')) {
            $queryBuilder
                ->andWhere('jr.runid >= :first_id')
                ->setParameter('first_id', $request->query->get('first_id'));
        }

        if ($request->query->has('last_id')) {
            $queryBuilder
                ->andWhere('jr.runid = :last_id')
                ->setParameter('last_id', $request->query->get('last_id'));
        }

        if ($request->query->has('judging_id')) {
            $queryBuilder
                ->andWhere('jr.judging = :judging_id')
                ->setParameter('judging_id', $request->query->get('judging_id'));
        }

        if ($request->query->has('limit')) {
            $limit = $request->query->getInt('limit');
            if ($limit<0) {
                throw new BadRequestHttpException('Limiting below 0 not possible.');
            }
            $queryBuilder->setMaxResults($limit);
        }

        // If an ID has not been given directly, only show runs before contest end.
        if (!$request->attributes->has('id') && !$request->query->has('ids')) {
            $queryBuilder
                ->andWhere('s.submittime < c.endtime')
                ->andWhere('j.rejudging IS NULL OR j.valid = 1');
            if ($this->config->get('verification_required')) {
                $queryBuilder->andWhere('j.verified = 1');
            }
        }

        return $queryBuilder;
    }

    protected function getIdField(): string
    {
        return 'jr.runid';
    }

    public function transformObject($object): JudgingRunWrapper
    {
        /** @var JudgingRun $judgingRun */
        $judgingRun      = $object;
        $judgementTypeId = $judgingRun->getRunresult() ? $this->verdicts[$judgingRun->getRunresult()] : null;
        return new JudgingRunWrapper($judgingRun, $judgementTypeId);
    }
}
