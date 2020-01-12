<?php declare(strict_types=1);

namespace App\Controller\API;

use App\Entity\Contest;
use App\Entity\Team;
use Doctrine\ORM\QueryBuilder;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\Model;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Rest\Route("/api/v4/contests/{cid}/teams", defaults={ "_format" = "json" })
 * @Rest\Prefix("/api/contests/{cid}/teams")
 * @Rest\NamePrefix("team_")
 * @SWG\Tag(name="Teams")
 * @SWG\Parameter(ref="#/parameters/cid")
 * @SWG\Response(response="404", ref="#/definitions/NotFound")
 * @SWG\Response(response="401", ref="#/definitions/Unauthorized")
 */
class TeamController extends AbstractRestController
{
    /**
     * Get all the teams for this contest
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @Rest\Get("")
     * @SWG\Response(
     *     response="200",
     *     description="Returns all the teams for this contest",
     *     @SWG\Schema(
     *         type="array",
     *         @SWG\Items(ref=@Model(type=Team::class))
     *     )
     * )
     * @SWG\Parameter(ref="#/parameters/idlist")
     * @SWG\Parameter(ref="#/parameters/strict")
     * @SWG\Parameter(
     *     name="category",
     *     in="query",
     *     type="string",
     *     description="Only show teams for the given category"
     * )
     * @SWG\Parameter(
     *     name="affiliation",
     *     in="query",
     *     type="string",
     *     description="Only show teams for the given affiliation / organization"
     * )
     * @SWG\Parameter(
     *     name="public",
     *     in="query",
     *     type="boolean",
     *     description="Only show visible teams, even for users with more permissions"
     * )
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function listAction(Request $request)
    {
        return parent::performListAction($request);
    }

    /**
     * Get the given team for this contest
     * @param Request $request
     * @param string $id
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @Rest\Get("/{id}")
     * @SWG\Response(
     *     response="200",
     *     description="Returns the given team for this contest",
     *     @Model(type=Team::class)
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
        $queryBuilder = $this->em->createQueryBuilder()
            ->from(Team::class, 't')
            ->leftJoin('t.affiliation', 'ta')
            ->leftJoin('t.category', 'tc')
            ->leftJoin('t.contests', 'c')
            ->leftJoin('tc.contests', 'cc')
            ->select('t, ta');

        if ($request->query->has('category')) {
            $queryBuilder
                ->andWhere('t.categoryid = :category')
                ->setParameter(':category', $request->query->get('category'));
        }

        if ($request->query->has('affiliation')) {
            $queryBuilder
                ->andWhere('t.affilid = :affiliation')
                ->setParameter(':affiliation', $request->query->get('affiliation'));
        }

        if (!$this->dj->checkrole('api_reader') || $request->query->getBoolean('public')) {
            $queryBuilder->andWhere('tc.visible = 1');
        }

        $contest = $this->em->getRepository(Contest::class)->find($this->getContestId($request));
        if (!$contest->isOpenToAllTeams()) {
            $queryBuilder
                ->andWhere('c.cid = :cid OR cc.cid = :cid')
                ->setParameter(':cid', $contest->getCid());
        }

        return $queryBuilder;
    }

    /**
     * @inheritdoc
     * @throws \Exception
     */
    protected function getIdField(): string
    {
        return sprintf('t.%s', $this->eventLogService->externalIdFieldForEntity(Team::class) ?? 'teamid');
    }
}
