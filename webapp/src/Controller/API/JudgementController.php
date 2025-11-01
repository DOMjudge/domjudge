<?php declare(strict_types=1);

namespace App\Controller\API;

use App\DataTransferObject\JudgingWrapper;
use App\Entity\Judging;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\QueryBuilder;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * @extends AbstractRestController<Judging, JudgingWrapper>
 */
#[Rest\Route('/')]
#[OA\Tag(name: 'Judgements')]
#[OA\Parameter(ref: '#/components/parameters/cid')]
#[OA\Parameter(ref: '#/components/parameters/strict')]
#[OA\Response(ref: '#/components/responses/NotFound', response: 404)]
#[OA\Response(ref: '#/components/responses/Unauthenticated', response: 401)]
#[OA\Response(ref: '#/components/responses/InvalidResponse', response: 400)]
#[OA\Response(ref: '#/components/responses/Unauthorized', response: 403)]
class JudgementController extends AbstractRestController implements QueryObjectTransformer
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
        parent::__construct($entityManager, $DOMJudgeService, $config, $eventLogService);

        $this->verdicts = $this->config->getVerdicts(['final', 'error']);
    }

    /**
     * Get all the judgements for this contest.
     * @throws NonUniqueResultException
     */
    #[IsGranted(new Expression("is_granted('ROLE_JURY') or is_granted('ROLE_TEAM') or is_granted('ROLE_JUDGEHOST') or is_granted('ROLE_API_READER')"))]
    #[Rest\Get('contests/{cid}/judgements')]
    #[Rest\Get('judgements')]
    #[OA\Response(
        response: 200,
        description: 'Returns all the judgements for this contest',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: JudgingWrapper::class))
        )
    )]
    #[OA\Parameter(ref: '#/components/parameters/idlist')]
    #[OA\Parameter(
        name: 'result',
        description: 'Only show judgements with the given result',
        in: 'query',
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'submission_id',
        description: 'Only show judgements for the given submission',
        in: 'query',
        schema: new OA\Schema(type: 'string')
    )]
    public function listAction(Request $request): Response
    {
        return parent::performListAction($request);
    }

    /**
     * Get the given judgement for this contest.
     * @throws NonUniqueResultException
     */
    #[IsGranted(new Expression("is_granted('ROLE_JURY') or is_granted('ROLE_TEAM') or is_granted('ROLE_JUDGEHOST') or is_granted('ROLE_API_READER')"))]
    #[Rest\Get('contests/{cid}/judgements/{id<\d+>}')]
    #[Rest\Get('judgements/{id<\d+>}')]
    #[OA\Response(
        response: 200,
        description: 'Returns the given judgement for this contest',
        content: new OA\JsonContent(ref: new Model(type: JudgingWrapper::class))
    )]
    #[OA\Parameter(ref: '#/components/parameters/id')]
    public function singleAction(Request $request, string $id): Response
    {
        return parent::performSingleAction($request, $id);
    }

    protected function getQueryBuilder(Request $request): QueryBuilder
    {
        $queryBuilder = $this->em->createQueryBuilder()
            ->from(Judging::class, 'j')
            ->select('j, c, s')
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

    public function transformObject($judging): JudgingWrapper
    {
        /** @var Judging $judging */
        $judgementTypeId = $judging->getResult() ? $this->verdicts[$judging->getResult()] : null;
        return new JudgingWrapper($judging, $judgementTypeId);
    }
}
