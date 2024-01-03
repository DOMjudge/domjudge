<?php declare(strict_types=1);

namespace App\Controller\API;

use App\Entity\Contest;
use App\Service\AwardService;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Service\ScoreboardService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Exception;
use FOS\RestBundle\Controller\Annotations as Rest;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[Rest\Route('/contests/{cid}/awards')]
#[OA\Tag(name: 'Awards')]
#[OA\Parameter(ref: '#/components/parameters/cid')]
#[OA\Parameter(ref: '#/components/parameters/strict')]
#[OA\Response(ref: '#/components/responses/NotFound', response: 404)]
#[OA\Response(ref: '#/components/responses/Unauthenticated', response: 401)]
#[OA\Response(ref: '#/components/responses/InvalidResponse', response: 400)]
class AwardsController extends AbstractRestController
{
    public function __construct(
        EntityManagerInterface $entityManager,
        DOMJudgeService $DOMJudgeService,
        ConfigurationService $config,
        EventLogService $eventLogService,
        protected readonly ScoreboardService $scoreboardService,
        protected readonly AwardService $awards
    ) {
        parent::__construct($entityManager, $DOMJudgeService, $config, $eventLogService);
    }

    /**
     * Get all the awards standings for this contest.
     *
     * @return array<array<string,string|int[]>>|null
     * @throws Exception
     */
    #[Rest\Get('')]
    #[OA\Response(
        response: 200,
        description: 'Returns the current teams qualifying for each award',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/Award')
        )
    )]
    public function listAction(Request $request): ?array
    {
        return $this->getAwardsData($request);
    }

    /**
     * Get the specific award for this contest.
     *
     * @throws Exception
     */
    #[Rest\Get('/{id}')]
    #[OA\Response(
        response: 200,
        description: 'Returns the award for this contest',
        content: new OA\JsonContent(ref: '#/components/schemas/Award')
    )]
    #[OA\Parameter(ref: '#/components/parameters/id')]
    public function singleAction(Request $request, string $id): array
    {
        $award = $this->getAwardsData($request, $id);

        if ($award === null) {
            throw new NotFoundHttpException(sprintf('Object with ID \'%s\' not found', $id));
        }

        return $award;
    }

    /**
     * Get the awards data for the given request and optional award ID.
     *
     * @return array<array<string,string|int[]>>|null
     */
    protected function getAwardsData(Request $request, string $requestedType = null): ?array
    {
        $public = !$this->dj->checkrole('api_reader');
        if ($this->dj->checkrole('api_reader') && $request->query->has('public')) {
            $public = $request->query->getBoolean('public');
        }
        /** @var Contest $contest */
        $contest = $this->em->getRepository(Contest::class)->find($this->getContestId($request));
        $isJury = $this->dj->checkrole('api_reader');
        $accessAllowed = ($isJury && $contest->getEnabled()) || (!$isJury && $contest->isActive());
        if (!$accessAllowed) {
            throw new AccessDeniedHttpException();
        }
        $scoreboard = $this->scoreboardService->getScoreboard($contest, !$public, null, true);

        return $this->awards->getAwards($contest, $scoreboard, $requestedType);
    }

    protected function getQueryBuilder(Request $request): QueryBuilder
    {
        throw new Exception('Not implemented');
    }

    protected function getIdField(): string
    {
        throw new Exception('Not implemented');
    }
}
