<?php declare(strict_types=1);

namespace App\Controller\API;

use App\DataTransferObject\Award;
use App\Entity\Contest;
use App\Service\AwardService;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Service\ScoreboardService;
use App\Utils\Scoreboard\Scoreboard;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[Rest\Route(path: '/contests/{cid}/awards')]
#[OA\Tag(name: 'Awards')]
#[OA\Parameter(ref: '#/components/parameters/cid')]
#[OA\Parameter(ref: '#/components/parameters/strict')]
#[OA\Response(ref: '#/components/responses/NotFound', response: 404)]
#[OA\Response(ref: '#/components/responses/Unauthenticated', response: 401)]
#[OA\Response(ref: '#/components/responses/InvalidResponse', response: 400)]
class AwardsController extends AbstractApiController
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
     * @throws Exception
     *
     * @return Award[]
     */
    #[Rest\Get(path: '')]
    #[OA\Response(
        response: 200,
        description: 'Returns the current teams qualifying for each award',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Award::class))
        )
    )]
    public function listAction(Request $request): array
    {
        [$contest, $scoreboard] = $this->getContestAndScoreboard($request);
        return $this->awards->getAwards($contest, $scoreboard);
    }

    /**
     * Get the specific award for this contest.
     *
     * @throws Exception
     */
    #[Rest\Get(path: '/{id}')]
    #[OA\Response(
        response: 200,
        description: 'Returns the award for this contest',
        content: new OA\JsonContent(ref: new Model(type: Award::class))
    )]
    #[OA\Parameter(ref: '#/components/parameters/id')]
    public function singleAction(Request $request, string $id): Award
    {
        [$contest, $scoreboard] = $this->getContestAndScoreboard($request);
        $award = $this->awards->getAward($contest, $scoreboard, $id);

        if ($award === null) {
            throw new NotFoundHttpException(sprintf('Object with ID \'%s\' not found', $id));
        }

        return $award;
    }

    /**
     * @return array{Contest, Scoreboard}
     */
    protected function getContestAndScoreboard(Request $request): array
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

        return [$contest, $scoreboard];
    }
}
