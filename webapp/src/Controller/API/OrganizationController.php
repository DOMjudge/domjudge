<?php declare(strict_types=1);

namespace App\Controller\API;

use App\Entity\Team;
use App\Entity\TeamAffiliation;
use App\Service\ImportExportService;
use Doctrine\ORM\QueryBuilder;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Annotations as OA;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @Rest\Route("/contests/{cid}/organizations")
 * @OA\Tag(name="Organizations")
 * @OA\Parameter(ref="#/components/parameters/cid")
 * @OA\Response(response="404", ref="#/components/schemas/NotFound")
 * @OA\Response(response="401", ref="#/components/schemas/Unauthorized")
 */
class OrganizationController extends AbstractRestController
{
    /**
     * Get all the organizations for this contest
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @Rest\Get("")
     * @OA\Response(
     *     response="200",
     *     description="Returns all the organizations for this contest",
     *     @OA\JsonContent(
     *         type="array",
     *         @OA\Items(ref=@Model(type=TeamAffiliation::class))
     *     )
     * )
     * @OA\Parameter(ref="#/components/parameters/idlist")
     * @OA\Parameter(ref="#/components/parameters/strict")
     * @OA\Parameter(
     *     name="country",
     *     in="query",
     *     description="Only show organizations for the given country",
     *     @OA\Schema(type="string")
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
     * @OA\Response(
     *     response="200",
     *     description="Returns the given organization for this contest",
     *     @Model(type=TeamAffiliation::class)
     * )
     * @OA\Parameter(ref="#/components/parameters/id")
     * @OA\Parameter(ref="#/components/parameters/strict")
     */
    public function singleAction(Request $request, string $id)
    {
        return parent::performSingleAction($request, $id);
    }

    /**
     * Add a new organization
     *
     * @param Request             $request
     * @param ImportExportService $importExport
     *
     * @return Response
     *
     * @Rest\Post()
     * @Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_API_WRITER')")
     * @OA\RequestBody(
     *     required=true,
     *     @OA\MediaType(
     *         mediaType="multipart/form-data",
     *         @OA\Schema(ref="#/components/schemas/TeamAffiliation")
     *     ),
     *     @OA\MediaType(
     *         mediaType="application/json",
     *         @OA\Schema(ref="#/components/schemas/TeamAffiliation")
     *     )
     * )
     * @OA\Response(
     *     response="201",
     *     description="Returns the added organization",
     *     @Model(type=TeamAffiliation::class)
     * )
     */
    public function addAction(Request $request, ImportExportService $importExport): Response
    {
        $saved = [];
        $importExport->importOrganizationsJson([$request->request->all()], $message, $saved);
        if (!empty($message)) {
            throw new BadRequestHttpException("Error while adding organization: $message");
        }

        $organization = $saved[0];
        $idField = $this->eventLogService->externalIdFieldForEntity(TeamAffiliation::class) ?? 'affilid';
        $method = sprintf('get%s', ucfirst($idField));
        $id = call_user_func([$organization, $method]);

        return $this->renderCreateData($request, $saved[0], 'organization', $id);
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
