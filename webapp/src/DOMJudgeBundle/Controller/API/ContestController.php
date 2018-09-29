<?php declare(strict_types=1);

namespace DOMJudgeBundle\Controller\API;

use Doctrine\ORM\QueryBuilder;
use DOMJudgeBundle\Utils\Utils;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\Model;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\Request;
use DOMJudgeBundle\Entity\Contest;

/**
 * @Rest\Route("/api/v4/contests", defaults={ "_format" = "json" })
 * @Rest\Prefix("/api/contests")
 * @Rest\NamePrefix("contest_")
 * @SWG\Tag(name="Contests")
 * @SWG\Response(response="404", ref="#/definitions/NotFound")
 * @SWG\Response(response="401", ref="#/definitions/Unauthorized")
 */
class ContestController extends AbstractRestController
{
    /**
     * Get all the active contests
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @Rest\Get("")
     * @SWG\Response(
     *     response="200",
     *     description="Returns all the active contests",
     *     @SWG\Schema(
     *         type="array",
     *         @SWG\Items(ref=@Model(type=Contest::class))
     *     )
     * )
     */
    public function listAction(Request $request)
    {
        return parent::performListAction($request);
    }

    /**
     * Get the contests with the given ID's
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @Rest\Post("")
     * @SWG\Response(
     *     response="200",
     *     description="Returns the contests with the given ID's",
     *     @SWG\Schema(
     *         type="array",
     *         @SWG\Items(ref=@Model(type=Contest::class))
     *     )
     * )
     * @SWG\Parameter(ref="#/parameters/idlist")
     */
    public function getMultipleAction(Request $request)
    {
        return parent::performGetMultipleAction($request);
    }

    /**
     * Get the given contest
     * @param Request $request
     * @param string $id
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @Rest\Get("/{id}")
     * @SWG\Response(
     *     response="200",
     *     description="Returns the given contest",
     *     @Model(type=Contest::class)
     * )
     * @SWG\Parameter(ref="#/parameters/id")
     */
    public function getSingleAction(Request $request, string $id)
    {
        return parent::performGetSingleAction($request, $id);
    }

    /**
     * @inheritdoc
     */
    protected function getQueryBuilder(Request $request): QueryBuilder
    {
        $now = Utils::now();
        $qb  = $this->entityManager->createQueryBuilder();
        $qb
            ->from('DOMJudgeBundle:Contest', 'c')
            ->select('c')
            ->andWhere('c.enabled = 1')
            ->andWhere($qb->expr()->orX(
                'c.deactivatetime is null',
                $qb->expr()->gt('c.deactivatetime', $now)
            ))
            ->orderBy('c.activatetime');

        // Filter on contests this user has access to
        if (!$this->DOMJudgeService->checkrole('jury')) {
            if ($this->DOMJudgeService->checkrole('team') && $this->DOMJudgeService->getUser()->getTeamid()) {
                $qb->join('c.teams', 'ct')
                    ->andWhere('ct.teamid = :teamid')
                    ->setParameter(':teamid', $this->DOMJudgeService->getUser()->getTeamid());
            } else {
                $qb->andWhere('c.public = 1');
            }
        }

        return $qb;
    }

    /**
     * @inheritdoc
     */
    protected function getIdField(): string
    {
        return 'c.cid';
    }
}
