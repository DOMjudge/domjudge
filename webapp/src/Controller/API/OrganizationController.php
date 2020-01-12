<?php declare(strict_types=1);

namespace App\Controller\API;

use App\Entity\TeamAffiliation;
use Doctrine\ORM\QueryBuilder;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\Model;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Rest\Route("/api/v4/contests/{cid}/organizations", defaults={ "_format" = "json" })
 * @Rest\Prefix("/api/contests/{cid}/organizations")
 * @Rest\NamePrefix("organization_")
 * @SWG\Tag(name="Organizations")
 * @SWG\Parameter(ref="#/parameters/cid")
 * @SWG\Response(response="404", ref="#/definitions/NotFound")
 * @SWG\Response(response="401", ref="#/definitions/Unauthorized")
 */
class OrganizationController extends AbstractRestController
{
    /**
     * Get all the organizations for this contest
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @Rest\Get("")
     * @SWG\Response(
     *     response="200",
     *     description="Returns all the organizations for this contest",
     *     @SWG\Schema(
     *         type="array",
     *         @SWG\Items(ref=@Model(type=TeamAffiliation::class))
     *     )
     * )
     * @SWG\Parameter(ref="#/parameters/idlist")
     * @SWG\Parameter(ref="#/parameters/strict")
     * @SWG\Parameter(
     *     name="country",
     *     in="query",
     *     type="string",
     *     description="Only show organizations for the given country"
     * )
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function listAction(Request $request)
    {
        return parent::performListAction($request);
    }

    /**
     * Get the given organization for this contest
     * @param Request $request
     * @param string $id
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @Rest\Get("/{id}")
     * @SWG\Response(
     *     response="200",
     *     description="Returns the given organization for this contest",
     *     @Model(type=TeamAffiliation::class)
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
            ->from(TeamAffiliation::class, 'ta')
            ->select('ta')
            ->orderBy('ta.name');

        if ($request->query->has('country')) {
            $queryBuilder
                ->andWhere('ta.country = :country')
                ->setParameter(':country', $request->query->get('country'));
        }

        return $queryBuilder;
    }

    /**
     * @inheritdoc
     * @throws \Exception
     */
    protected function getIdField(): string
    {
        return sprintf('ta.%s', $this->eventLogService->externalIdFieldForEntity(TeamAffiliation::class) ?? 'affilid');
    }
}
