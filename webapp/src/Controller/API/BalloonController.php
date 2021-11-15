<?php declare(strict_types=1);

namespace App\Controller\API;

use App\Entity\Contest;
use App\Service\BalloonService;
use Exception;
use FOS\RestBundle\Controller\Annotations as Rest;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use OpenApi\Annotations as OA;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Intl\Exception\NotImplementedException;

/**
 * @Rest\Route("/contests/{cid}/balloons")
 * @OA\Tag(name="Balloons")
 * @OA\Parameter(ref="#/components/parameters/cid")
 * @OA\Response(response="404", ref="#/components/responses/NotFound")
 * @OA\Response(response="401", ref="#/components/responses/Unauthorized")
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
     * )
     * @OA\Parameter(
     *     name="todo",
     *     in="query",
     *     description="Only show balloons not handed out yet.",
     *     @OA\Schema(type="boolean")
     * )
     * @throws Exception
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
     * @throws Exception
     */
    public function markDoneAction(Request $request, int $balloonId, BalloonService $balloonService) : void
    {
        $balloonService->setDone($balloonId);
    }

    protected function getQueryBuilder(Request $request): QueryBuilder
    {
        throw new NotImplementedException();
    }

    protected function getIdField(): string
    {
        throw new NotImplementedException();
    }
}
