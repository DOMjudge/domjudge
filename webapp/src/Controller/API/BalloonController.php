<?php


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
 * @Rest\Route("/api/v4/contests/{cid}/balloons", defaults={ "_format" = "json" })
 * @Rest\Prefix("/api/contests/{cid}/balloons")
 * @Rest\NamePrefix("balloons_")
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
     * @SWG\Parameter(ref="#/parameters/strict")
     * @throws \Exception
     */
    public function listAction(Request $request, BalloonService $balloonService)
    {
        /** @var Contest $contest */
        $contest = $this->em->getRepository(Contest::class)->find($this->getContestId($request));
        return array_column($balloonService->collectBalloonTable($contest), 'data');
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
