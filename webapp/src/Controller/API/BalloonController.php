<?php declare(strict_types=1);

namespace App\Controller\API;

use App\Entity\Contest;
use App\Service\BalloonService;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\QueryBuilder;
use Exception;
use FOS\RestBundle\Controller\Annotations as Rest;
use OpenApi\Annotations as OA;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Rest\Route("/contests/{cid}/balloons")
 * @OA\Tag(name="Balloons")
 * @OA\Parameter(ref="#/components/parameters/cid")
 * @OA\Response(response="404", ref="#/components/responses/NotFound")
 * @OA\Response(response="400", ref="#/components/responses/InvalidResponse")
 * @OA\Response(response="401", ref="#/components/responses/Unauthenticated")
 * @OA\Response(response="403", ref="#/components/responses/Unauthorized")
 * @Security("is_granted('ROLE_JURY') or is_granted('ROLE_API_READER') or is_granted('ROLE_BALLOON')")
 */
class BalloonController extends AbstractRestController
{
    /**
     * Get all the balloons for this contest.
     * @Rest\Get("")
     * @OA\Response(
     *     response="200",
     *     description="Returns the balloons for this contest.",
     *     @OA\JsonContent(
     *         type="array",
     *         @OA\Items(ref="#/components/schemas/Balloon")
     *     )
     * )
     * @OA\Parameter(
     *     name="todo",
     *     in="query",
     *     description="Only show balloons not handed out yet.",
     *     @OA\Schema(type="boolean")
     * )
     *
     * @throws NonUniqueResultException
     */
    public function listAction(Request $request, BalloonService $balloonService): array
    {
        /** @var Contest $contest */
        $contest = $this->em->getRepository(Contest::class)->find($this->getContestId($request));
        return array_column($balloonService->collectBalloonTable($contest, $request->query->getBoolean('todo')), 'data');
    }

    /**
     * Mark a specific balloon as done.
     * @Rest\Post("/{balloonId}/done")
     * @OA\Response(
     *     response="204",
     *     description="The balloon was now marked as done or already marked as such.",
     * )
     * @OA\Parameter(ref="#/components/parameters/balloonId")
     * @Security("is_granted('ROLE_JURY') or is_granted('ROLE_BALLOON')")
     */
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
