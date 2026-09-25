<?php declare(strict_types=1);

namespace App\Controller\API;

use App\DataTransferObject\JudgingWrapper;
use App\Entity\AbstractJudgement;
use App\Entity\Contest;
use App\Entity\ExternalJudgement;
use App\Entity\Judging;
use App\Entity\Team;
use App\Entity\TeamCategory;
use App\Service\AuthorizedUserService;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Utils\FreezeData;
use App\Utils\SimplifiedVerdict;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * @extends AbstractRestController<AbstractJudgement, JudgingWrapper>
 */
#[Rest\Route(path: '/')]
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

    private bool $useExternalJudgements = false;

    /**
     * The team to compare each judgement's submitting team against when redacting
     * judgement_type_id for non-privileged callers. Null means the caller may see all
     * detailed verdicts (privileged role or no team context).
     */
    private ?Team $ownTeamForRedaction = null;

    public function __construct(
        AuthorizedUserService $authService,
        EntityManagerInterface $em,
        DOMJudgeService $dj,
        ConfigurationService $config,
        EventLogService $eventLogService
    ) {
        parent::__construct($authService, $em, $dj, $config, $eventLogService);

        $this->verdicts = $this->config->getVerdicts(['final', 'error']);
    }

    /**
     * Get all the judgements for this contest.
     * @throws NonUniqueResultException
     */
    #[IsGranted(new Expression("is_granted('ROLE_JURY') or is_granted('ROLE_TEAM') or is_granted('ROLE_JUDGEHOST') or is_granted('ROLE_API_READER')"))]
    #[Rest\Get(path: 'contests/{cid}/judgements')]
    #[Rest\Get(path: 'judgements')]
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
    #[Rest\Get(path: 'contests/{cid}/judgements/{id<\d+>}')]
    #[Rest\Get(path: 'judgements/{id<\d+>}')]
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
        $contestId = null;
        $contest = null;
        if ($request->attributes->has('cid')) {
            $contestId = $this->getContestId($request);
            $contest = $this->em->getRepository(Contest::class)->find($contestId);
            $this->useExternalJudgements = $contest?->isExternalSourceUseJudgements() ?? false;
        } else {
            $this->useExternalJudgements = false;
        }

        if ($this->useExternalJudgements) {
            $queryBuilder = $this->em->createQueryBuilder()
                ->from(ExternalJudgement::class, 'j')
                ->select('j, c, s')
                ->leftJoin('j.contest', 'c')
                ->leftJoin('j.submission', 's')
                ->leftJoin('j.external_runs', 'jr')
                ->groupBy('j.extjudgementid')
                ->orderBy('j.extjudgementid');
        } else {
            $queryBuilder = $this->em->createQueryBuilder()
                ->from(Judging::class, 'j')
                ->select('j, c, s')
                ->leftJoin('j.contest', 'c')
                ->leftJoin('j.submission', 's')
                ->leftJoin('j.rejudging', 'r')
                ->leftJoin('j.runs', 'jr')
                ->groupBy('j.judgingid')
                ->orderBy('j.judgingid');
        }

        if ($contestId !== null) {
            $queryBuilder
                ->andWhere('j.contest = :cid')
                ->setParameter('cid', $contestId);
        }

        $roleAllowsVisibility = $this->authService->checkRole('api_reader')
            || $this->authService->checkRole('judgehost');
        if ($request->query->has('result')) {
            $queryBuilder
                ->andWhere('j.result = :result')
                ->setParameter('result', $request->query->get('result'));
        } elseif (!$roleAllowsVisibility) {
            $queryBuilder->andWhere('j.result IS NOT NULL');
        }

        $this->ownTeamForRedaction = null;
        if (!$roleAllowsVisibility) {
            $currentTeam = $this->authService->getUser()->getTeam();
            // During the contest freeze (or when no contest is in scope), restrict listings to the
            // caller's own team. Otherwise the caller may see other teams' judgements, but
            // transformObject() redacts judgement_type_id so only the simplified verdict is exposed.
            $contestIsFrozen = $contest !== null && (new FreezeData($contest))->showFrozen();
            if ($contestId === null || $contestIsFrozen) {
                $queryBuilder
                    ->andWhere('s.team = :team')
                    ->setParameter('team', $currentTeam);
            } else {
                // Never expose judgements for hidden (non-public) teams. Mirrors the
                // visibility filter used in SubmissionController::getQueryBuilder().
                $queryBuilder
                    ->join('s.team', 't')
                    ->join('t.categories', 'cat', Join::WITH, 'BIT_AND(cat.types, :scoring) = :scoring')
                    ->setParameter('scoring', TeamCategory::TYPE_SCORING)
                    ->andWhere('cat.visible = 1 OR s.team = :team')
                    ->setParameter('team', $currentTeam);
                $this->ownTeamForRedaction = $currentTeam;
            }
        }

        if ($request->query->has('submission_id')) {
            $queryBuilder
                ->andWhere('s.externalid = :submission')
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
        if ($this->useExternalJudgements) {
            return 'j.externalid';
        }
        return 'j.judgingid';
    }

    public function transformObject($judging): JudgingWrapper
    {
        /** @var AbstractJudgement $judging */
        $judgementTypeId = $judging->getResult() ? $this->verdicts[$judging->getResult()] : null;
        $simplifiedJudgementTypeId = $judgementTypeId === null
            ? null
            : SimplifiedVerdict::for($judgementTypeId)->value;

        // Hide the detailed verdict for other teams' judgements when the caller is a non-privileged
        // team viewing during a non-frozen contest. The simplified verdict is still exposed.
        if ($this->ownTeamForRedaction !== null
            && $judging->getSubmission()->getTeam()->getTeamid() !== $this->ownTeamForRedaction->getTeamid()) {
            $judgementTypeId = null;
        }

        return new JudgingWrapper($judging, $judgementTypeId, $simplifiedJudgementTypeId);
    }
}
