<?php declare(strict_types=1);

namespace App\Controller\API;

use App\Entity\Contest;
use App\Service\BalloonService;
use FOS\RestBundle\Controller\Annotations as Rest;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Swagger\Annotations as SWG;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Intl\Exception\NotImplementedException;

/**
 * @Rest\Route("/contests/{cid}/balloons")
 * @SWG\Tag(name="Balloons")
 * @SWG\Parameter(ref="#/parameters/cid")
 * @SWG\Response(response="404", ref="#/definitions/NotFound")
 * @SWG\Response(response="401", ref="#/definitions/Unauthorized")
 * @Security("is_granted('ROLE_JURY') or is_granted('ROLE_API_READER') or is_granted('ROLE_BALLOON')")
 */
class BalloonController extends AbstractRestController
{
    /**
     * Get all the balloons for this contest.
     * @param Request $request
     * @return array
     * @Rest\Get("")
     * @SWG\Response(
     *     response="200",
     *     description="Returns the balloons for this contest.",
     * )
     * @SWG\Parameter(
     *     name="todo",
     *     in="query",
     *     type="boolean",
     *     description="Only show balloons not handed out yet."
     * )
     * @throws \Exception
     */
    public function listAction(Request $request, BalloonService $balloonService)
    {
        /** @var Contest $contest */
        $contest = $this->em->getRepository(Contest::class)->find($this->getContestId($request));
        return array_column($balloonService->collectBalloonTable($contest, $request->query->getBoolean('todo')), 'data');
    }

    /**
     * Mark a specific balloon as done.
     * @param Request $request
     * @param int $balloonId
     * @Rest\Post("/{balloonId}/done")
     * @SWG\Response(
     *     response="204",
     *     description="The balloon was now marked as done or already marked as such.",
     * )
     * @SWG\Parameter(
     *     name="balloonId",
     *     in="path",
     *     type="integer",
     *     description="The balloonId to mark as done."
     * )
     * @Security("is_granted('ROLE_JURY') or is_granted('ROLE_BALLOON')")
     * @throws \Exception
     */
    public function markDoneAction(Request $request, int $balloonId, BalloonService $balloonService)
    {
        $balloonService->setDone($balloonId);
    }

    /**
     * @inheritDoc
     */
    protected function getQueryBuilder(Request $request): QueryBuilder
    {
        throw new NotImplementedException();
    }

    /**
     * @inheritDoc
     */
    protected function getIdField(): string
    {
        throw new NotImplementedException();
    }
}
