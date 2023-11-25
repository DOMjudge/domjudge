<?php declare(strict_types=1);

namespace App\Controller\API;

use App\Entity\Contest;
use App\Entity\Team;
use App\Service\BalloonService;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\QueryBuilder;
use Exception;
use FOS\RestBundle\Controller\Annotations as Rest;
use OpenApi\Attributes as OA;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(new Expression("is_granted('ROLE_JURY') or is_granted('ROLE_API_READER') or is_granted('ROLE_BALLOON')"))]
#[Rest\Route('/contests/{cid}/balloons')]
#[OA\Tag(name: 'Balloons')]
#[OA\Parameter(ref: '#/components/parameters/cid')]
#[OA\Response(ref: '#/components/responses/NotFound', response: 404)]
#[OA\Response(ref: '#/components/responses/InvalidResponse', response: 400)]
#[OA\Response(ref: '#/components/responses/Unauthenticated', response: 401)]
#[OA\Response(ref: '#/components/responses/Unauthorized', response: 403)]
class BalloonController extends AbstractRestController
{
    /**
     * Get all the balloons for this contest.
     *
     * @throws NonUniqueResultException
     */
    #[Rest\Get('')]
    #[OA\Response(
        response: 200,
        description: 'Returns the balloons for this contest.',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/Balloon')
        )
    )]
    #[OA\Parameter(
        name: 'todo',
        description: 'Only show balloons not handed out yet.',
        in: 'query',
        schema: new OA\Schema(type: 'boolean')
    )]
    public function listAction(
        Request $request,
        BalloonService $balloonService,
        #[MapQueryParameter]
        bool $todo = false
    ): array {
        /** @var Contest $contest */
        $contest = $this->em->getRepository(Contest::class)->find($this->getContestId($request));
        $balloonsData = $balloonService->collectBalloonTable($contest, $todo);
        foreach ($balloonsData as &$b) {
            /** @var Team $team */
            $team = $b['data']['team'];
            $b['data']['team'] = "t" . $team->getTeamid() . ": " . $team->getEffectiveName();
        }
        unset($b);
        return array_column($balloonsData, 'data');
    }

    /**
     * Mark a specific balloon as done.
     */
    #[IsGranted(new Expression("is_granted('ROLE_JURY') or is_granted('ROLE_BALLOON')"))]
    #[Rest\Post('/{balloonId<\d+>}/done')]
    #[OA\Response(
        response: 204,
        description: 'The balloon was now marked as done or already marked as such.'
    )]
    #[OA\Parameter(ref: '#/components/parameters/balloonId')]
    public function markDoneAction(int $balloonId, BalloonService $balloonService): void
    {
        $balloonService->setDone($balloonId);
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
