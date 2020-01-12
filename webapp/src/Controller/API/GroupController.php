<?php declare(strict_types=1);

namespace App\Controller\API;

use App\Entity\TeamCategory;
use Doctrine\ORM\QueryBuilder;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\Model;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Rest\Route("/api/v4/contests/{cid}/groups", defaults={ "_format" = "json" })
 * @Rest\Prefix("/api/contests/{cid}/groups")
 * @Rest\NamePrefix("group_")
 * @SWG\Tag(name="Groups")
 * @SWG\Parameter(ref="#/parameters/cid")
 * @SWG\Response(response="404", ref="#/definitions/NotFound")
 * @SWG\Response(response="401", ref="#/definitions/Unauthorized")
 */
class GroupController extends AbstractRestController
{
    /**
     * Get all the groups for this contest
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @Rest\Get("")
     * @SWG\Parameter(ref="#/parameters/idlist")
     * @SWG\Parameter(ref="#/parameters/strict")
     * @SWG\Parameter(
     *     name="public",
     *     in="query",
     *     type="boolean",
     *     description="Only show public groups, even for users with more permissions"
     * )
     * @SWG\Response(
     *     response="200",
     *     description="Returns all the groups for this contest",
     *     @SWG\Schema(
     *         type="array",
     *         @SWG\Items(ref=@Model(type=TeamCategory::class))
     *     )
     * )
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function listAction(Request $request)
    {
        return parent::performListAction($request);
    }

    /**
     * Get the given group for this contest
     * @param Request $request
     * @param string $id
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @Rest\Get("/{id}")
     * @SWG\Response(
     *     response="200",
     *     description="Returns the given group for this contest",
     *     @Model(type=TeamCategory::class)
     * )
     * @SWG\Parameter(ref="#/parameters/id")
     * @SWG\Parameter(ref="#/parameters/strict")
     */
    public function singleAction(Request $request, string $id)
    {
        return parent::performSingleAction($request, $id);
    }

    /**
     * @inheritdoc
     */
    protected function getQueryBuilder(Request $request): QueryBuilder
    {
        // Call getContestId to make sure we have an active contest
        $this->getContestId($request);
        $queryBuilder = $this->em->createQueryBuilder()
            ->from(TeamCategory::class, 'c')
            ->select('c')
            ->orderBy('c.sortorder');

        if (!$this->dj->checkrole('api_reader') || $request->query->get('public')) {
            $queryBuilder
                ->andWhere('c.visible = 1');
        }

        return $queryBuilder;
    }

    /**
     * @inheritdoc
     */
    protected function getIdField(): string
    {
        return 'c.categoryid';
    }
}
