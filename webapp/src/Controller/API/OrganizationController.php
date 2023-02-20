<?php declare(strict_types=1);

namespace App\Controller\API;

use App\Entity\TeamAffiliation;
use App\Service\AssetUpdateService;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Service\ImportExportService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\QueryBuilder;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Annotations as OA;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @Rest\Route("/")
 * @OA\Tag(name="Organizations")
 * @OA\Parameter(ref="#/components/parameters/cid")
 * @OA\Parameter(ref="#/components/parameters/strict")
 * @OA\Response(response="400", ref="#/components/responses/InvalidResponse")
 * @OA\Response(response="401", ref="#/components/responses/Unauthenticated")
 * @OA\Response(response="403", ref="#/components/responses/Unauthorized")
 * @OA\Response(response="404", ref="#/components/responses/NotFound")
 */
class OrganizationController extends AbstractRestController
{
    protected AssetUpdateService $assetUpdater;

    public function __construct(
        EntityManagerInterface $entityManager,
        DOMJudgeService $dj,
        ConfigurationService $config,
        EventLogService $eventLogService,
        AssetUpdateService $assetUpdater
    ) {
        parent::__construct($entityManager, $dj, $config, $eventLogService);
        $this->assetUpdater = $assetUpdater;
    }

    /**
     * Get all the organizations for this contest.
     * @Rest\Get("contests/{cid}/organizations")
     * @Rest\Get("organizations")
     * @OA\Response(
     *     response="200",
     *     description="Returns all the organizations for this contest",
     *     @OA\JsonContent(
     *         type="array",
     *         @OA\Items(
     *             allOf={
     *                 @OA\Schema(ref=@Model(type=TeamAffiliation::class)),
     *                 @OA\Schema(ref="#/components/schemas/Logo")
     *             }
     *         )
     *     )
     * )
     * @OA\Parameter(ref="#/components/parameters/idlist")
     * @OA\Parameter(
     *     name="country",
     *     in="query",
     *     description="Only show organizations for the given country",
     *     @OA\Schema(type="string")
     * )
     * @throws NonUniqueResultException
     */
    public function listAction(Request $request): Response
    {
        return parent::performListAction($request);
    }

    /**
     * Get the given organization for this contest.
     * @throws NonUniqueResultException
     * @Rest\Get("contests/{cid}/organizations/{id}")
     * @Rest\Get("organizations/{id}")
     * @OA\Response(
     *     response="200",
     *     description="Returns the given organization for this contest",
     *     @OA\JsonContent(
     *         allOf={
     *             @OA\Schema(ref=@Model(type=TeamAffiliation::class)),
     *             @OA\Schema(ref="#/components/schemas/Logo")
     *         }
     *     )
     * )
     * @OA\Parameter(ref="#/components/parameters/id")
     */
    public function singleAction(Request $request, string $id): Response
    {
        return parent::performSingleAction($request, $id);
    }

    /**
     * Get the logo for the given organization.
     * @Rest\Get("contests/{cid}/organizations/{id}/logo", name="organization_logo")
     * @Rest\Get("organizations/{id}/logo")
     * @OA\Response(
     *     response="200",
     *     description="Returns the given organization logo in PNG, JPG or SVG format",
     *     @OA\MediaType(mediaType="image/png"),
     *     @OA\MediaType(mediaType="image/jpeg"),
     *     @OA\MediaType(mediaType="image/svg+xml")
     * )
     * @OA\Parameter(ref="#/components/parameters/id")
     */
    public function logoAction(Request $request, string $id): Response
    {
        /** @var TeamAffiliation $teamAffiliation */
        $teamAffiliation = $this->getQueryBuilder($request)
            ->andWhere(sprintf('%s = :id', $this->getIdField()))
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        if ($teamAffiliation === null) {
            throw new NotFoundHttpException(sprintf('Object with ID \'%s\' not found', $id));
        }

        $affiliationLogo = $this->dj->assetPath($id, 'affiliation', true);

        if ($affiliationLogo && file_exists($affiliationLogo)) {
            return static::sendBinaryFileResponse($request, $affiliationLogo);
        }

        throw new NotFoundHttpException('Affiliation logo not found');
    }

    /**
     * Delete the logo for the given organization.
     * @Rest\Delete("contests/{cid}/organizations/{id}/logo", name="delete_organization_logo")
     * @Rest\Delete("organizations/{id}/logo")
     * @IsGranted("ROLE_ADMIN")
     * @OA\Response(response="204", description="Deleting logo succeeded")
     * @OA\Parameter(ref="#/components/parameters/id")
     */
    public function deleteLogoAction(Request $request, string $id): Response
    {
        /** @var TeamAffiliation $teamAffiliation */
        $teamAffiliation = $this->getQueryBuilder($request)
            ->andWhere(sprintf('%s = :id', $this->getIdField()))
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        if ($teamAffiliation === null) {
            throw new NotFoundHttpException(sprintf('Object with ID \'%s\' not found', $id));
        }

        $teamAffiliation->setClearLogo(true);

        $this->assetUpdater->updateAssets($teamAffiliation);
        if ($request->attributes->has('cid')) {
            $contestId = $this->getContestId($request);
        }
        $this->eventLogService->log('organizations', $teamAffiliation->getAffilid(), EventLogService::ACTION_UPDATE,
            $contestId);

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * Set the logo for the given organization.
     * @Rest\POST("contests/{cid}/organizations/{id}/logo", name="post_organization_logo")
     * @Rest\POST("organizations/{id}/logo")
     * @Rest\PUT("contests/{cid}/organizations/{id}/logo", name="put_organization_logo")
     * @Rest\PUT("organizations/{id}/logo")
     * @OA\RequestBody(
     *     required=true,
     *     @OA\MediaType(
     *         mediaType="multipart/form-data",
     *         @OA\Schema(
     *             required={"logo"},
     *             @OA\Property(
     *                 property="logo",
     *                 type="string",
     *                 format="binary",
     *                 description="The logo to use."
     *             )
     *         )
     *     )
     * )
     * @IsGranted("ROLE_ADMIN")
     * @OA\Response(response="204", description="Setting logo succeeded")
     * @OA\Parameter(ref="#/components/parameters/id")
     */
    public function setLogoAction(Request $request, string $id, ValidatorInterface $validator): Response
    {
        /** @var TeamAffiliation $teamAffiliation */
        $teamAffiliation = $this->getQueryBuilder($request)
            ->andWhere(sprintf('%s = :id', $this->getIdField()))
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        if ($teamAffiliation === null) {
            throw new NotFoundHttpException(sprintf('Object with ID \'%s\' not found', $id));
        }

        /** @var UploadedFile $logo */
        $logo = $request->files->get('logo');

        if (!$logo) {
            return new JsonResponse(['title' => 'Validation failed', 'errors' => ['Please supply a logo']], Response::HTTP_BAD_REQUEST);
        }

        $teamAffiliation->setLogoFile($logo);

        if ($errorResponse = $this->responseForErrors($validator->validate($teamAffiliation), true)) {
            return $errorResponse;
        }

        $this->assetUpdater->updateAssets($teamAffiliation);
        $contestId = null;
        if ($request->attributes->has('cid')) {
            $contestId = $this->getContestId($request);
        }
        $this->eventLogService->log('organizations', $teamAffiliation->getAffilid(), EventLogService::ACTION_UPDATE,
            $contestId);

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * Add a new organization.
     *
     * @Rest\Post("contests/{cid}/organizations")
     * @Rest\Post("organizations")
     * @IsGranted("ROLE_API_WRITER")
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

    protected function getQueryBuilder(Request $request): QueryBuilder
    {
        if ($request->attributes->has('cid')) {
            // Call getContestId to make sure we have an active contest.
            $this->getContestId($request);
        }
        $queryBuilder = $this->em->createQueryBuilder()
            ->from(TeamAffiliation::class, 'ta')
            ->select('ta')
            ->orderBy('ta.name');

        if ($request->query->has('country')) {
            $queryBuilder
                ->andWhere('ta.country = :country')
                ->setParameter('country', $request->query->get('country'));
        }

        return $queryBuilder;
    }

    protected function getIdField(): string
    {
        return sprintf('ta.%s', $this->eventLogService->externalIdFieldForEntity(TeamAffiliation::class) ?? 'affilid');
    }
}
