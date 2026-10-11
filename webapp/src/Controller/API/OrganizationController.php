<?php declare(strict_types=1);

namespace App\Controller\API;

use App\DataTransferObject\AddOrganization;
use App\DataTransferObject\PatchOrganization;
use App\DataTransferObject\PutOrganization;
use App\Entity\TeamAffiliation;
use App\Service\AssetUpdateService;
use App\Service\AuthorizedUserService;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Service\ImportExportService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\QueryBuilder;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @extends AbstractRestController<TeamAffiliation, TeamAffiliation>
 */
#[Rest\Route(path: '/')]
#[OA\Tag(name: 'Organizations')]
#[OA\Parameter(ref: '#/components/parameters/cid')]
#[OA\Parameter(ref: '#/components/parameters/strict')]
#[OA\Response(ref: '#/components/responses/InvalidResponse', response: 400)]
#[OA\Response(ref: '#/components/responses/Unauthenticated', response: 401)]
#[OA\Response(ref: '#/components/responses/Unauthorized', response: 403)]
#[OA\Response(ref: '#/components/responses/NotFound', response: 404)]
class OrganizationController extends AbstractRestController
{
    public function __construct(
        AuthorizedUserService $authService,
        EntityManagerInterface $em,
        DOMJudgeService $dj,
        ConfigurationService $config,
        EventLogService $eventLogService,
        protected readonly AssetUpdateService $assetUpdater
    ) {
        parent::__construct($authService, $em, $dj, $config, $eventLogService);
    }

    /**
     * Get all the organizations for this contest.
     * @throws NonUniqueResultException
     */
    #[Rest\Get(path: 'contests/{cid}/organizations')]
    #[Rest\Get(path: 'organizations')]
    #[OA\Response(
        response: 200,
        description: 'Returns all the organizations for this contest',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: TeamAffiliation::class))
        )
    )]
    #[OA\Parameter(ref: '#/components/parameters/idlist')]
    #[OA\Parameter(
        name: 'country',
        description: 'Only show organizations for the given country',
        in: 'query',
        schema: new OA\Schema(type: 'string')
    )]
    public function listAction(Request $request): Response
    {
        return parent::performListAction($request);
    }

    /**
     * Get the given organization for this contest.
     * @throws NonUniqueResultException
     */
    #[Rest\Get(path: 'contests/{cid}/organizations/{id}')]
    #[Rest\Get(path: 'organizations/{id}')]
    #[OA\Response(
        response: 200,
        description: 'Returns the given organization for this contest',
        content: new OA\JsonContent(ref: new Model(type: TeamAffiliation::class))
    )]
    #[OA\Parameter(ref: '#/components/parameters/id')]
    public function singleAction(Request $request, string $id): Response
    {
        return parent::performSingleAction($request, $id);
    }

    /**
     * Get the logo for the given organization.
     */
    #[Rest\Get(path: 'contests/{cid}/organizations/{id}/logo', name: 'organization_logo')]
    #[Rest\Get(path: 'organizations/{id}/logo', name: 'no_contest_organization_logo')]
    #[OA\Response(
        response: 200,
        description: 'Returns the given organization logo in PNG, JPG or SVG format',
        content: [
            new OA\MediaType(mediaType: 'image/png'),
            new OA\MediaType(mediaType: 'image/jpeg'),
            new OA\MediaType(mediaType: 'image/svg+xml'),
        ]
    )]
    #[OA\Parameter(ref: '#/components/parameters/id')]
    public function logoAction(Request $request, string $id): Response
    {
        /** @var TeamAffiliation|null $teamAffiliation */
        $teamAffiliation = $this->getQueryBuilder($request)
            ->andWhere('ta.externalid = :id')
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
     */
    #[IsGranted('ROLE_ADMIN')]
    #[Rest\Delete(path: 'contests/{cid}/organizations/{id}/logo', name: 'delete_organization_logo')]
    #[Rest\Delete(path: 'organizations/{id}/logo')]
    #[OA\Response(response: 204, description: 'Deleting logo succeeded')]
    #[OA\Parameter(ref: '#/components/parameters/id')]
    public function deleteLogoAction(Request $request, string $id): Response
    {
        $contestId = null;
        /** @var TeamAffiliation|null $teamAffiliation */
        $teamAffiliation = $this->getQueryBuilder($request)
            ->andWhere('ta.externalid = :id')
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
     */
    #[IsGranted('ROLE_ADMIN')]
    #[Rest\Post(path: 'contests/{cid}/organizations/{id}/logo', name: 'post_organization_logo')]
    #[Rest\Post(path: 'organizations/{id}/logo')]
    #[Rest\Put(path: 'contests/{cid}/organizations/{id}/logo', name: 'put_organization_logo')]
    #[Rest\Put(path: 'organizations/{id}/logo')]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                required: ['logo'],
                properties: [
                    new OA\Property(
                        property: 'logo',
                        description: 'The logo to use.',
                        type: 'string',
                        format: 'binary'
                    ),
                ]
            )
        )
    )]
    #[OA\Response(response: 204, description: 'Setting logo succeeded')]
    #[OA\Parameter(ref: '#/components/parameters/id')]
    public function setLogoAction(Request $request, string $id, ValidatorInterface $validator): Response
    {
        /** @var TeamAffiliation|null $teamAffiliation */
        $teamAffiliation = $this->getQueryBuilder($request)
            ->andWhere('ta.externalid = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        if ($teamAffiliation === null) {
            throw new NotFoundHttpException(sprintf('Object with ID \'%s\' not found', $id));
        }

        /** @var UploadedFile|null $logo */
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
     */
    #[IsGranted('ROLE_API_WRITER')]
    #[Rest\Post(path: 'contests/{cid}/organizations')]
    #[Rest\Post(path: 'organizations')]
    #[OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(ref: new Model(type: AddOrganization::class))
            ),
        ]
    )]
    #[OA\Response(
        response: 201,
        description: 'Returns the added organization',
        content: new Model(type: TeamAffiliation::class)
    )]
    public function addAction(
        #[MapRequestPayload(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        AddOrganization $addOrganization,
        Request $request,
        ImportExportService $importExport
    ): Response {
        if ($addOrganization->id !== null
            && $this->em->getRepository(TeamAffiliation::class)->findOneBy(['externalid' => $addOrganization->id])) {
            throw new BadRequestHttpException(sprintf("Organization with ID '%s' already exists", $addOrganization->id));
        }

        return $this->importSingle(
            $request,
            $importExport->importOrganizationsJson(...),
            $this->normalizePayload($addOrganization),
            'organization',
            created: true,
        );
    }

    /**
     * Replace an existing organization or create one with the given ID.
     */
    #[IsGranted('ROLE_API_WRITER')]
    #[Rest\Put(path: 'contests/{cid}/organizations/{id}')]
    #[Rest\Put(path: 'organizations/{id}')]
    #[OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(ref: new Model(type: PutOrganization::class))
            ),
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'Returns the replaced organization',
        content: new Model(type: TeamAffiliation::class)
    )]
    #[OA\Response(
        response: 201,
        description: 'Returns the added organization',
        content: new Model(type: TeamAffiliation::class)
    )]
    #[OA\Parameter(ref: '#/components/parameters/id')]
    public function updateAction(
        #[MapRequestPayload(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        PutOrganization $putOrganization,
        Request $request,
        ImportExportService $importExport,
        string $id,
    ): Response {
        $this->checkPayloadId($id, $putOrganization->id);

        return $this->importSingle(
            $request,
            $importExport->importOrganizationsJson(...),
            $this->normalizePayload($putOrganization),
            'organization',
            created: $this->em->getRepository(TeamAffiliation::class)->findOneBy(['externalid' => $id]) === null,
        );
    }

    /**
     * Update only the provided fields of an existing organization.
     */
    #[IsGranted('ROLE_API_WRITER')]
    #[Rest\Patch(path: 'contests/{cid}/organizations/{id}')]
    #[Rest\Patch(path: 'organizations/{id}')]
    #[OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(ref: new Model(type: PatchOrganization::class))
            ),
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'Returns the updated organization',
        content: new Model(type: TeamAffiliation::class)
    )]
    #[OA\Parameter(ref: '#/components/parameters/id')]
    public function patchAction(
        #[MapRequestPayload(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        PatchOrganization $patchOrganization,
        Request $request,
        ImportExportService $importExport,
        string $id,
    ): Response {
        $this->checkPayloadId($id, $patchOrganization->id ?? $id);

        /** @var TeamAffiliation|null $organization */
        $organization = $this->em->getRepository(TeamAffiliation::class)->findOneBy(['externalid' => $id]);
        if ($organization === null) {
            throw new NotFoundHttpException(sprintf("Organization with ID '%s' not found", $id));
        }

        // No `shortname` here: the import falls back to `name` for it, which is the short name in the API.
        $data = $this->mergePatch($request, $patchOrganization, [
            'id' => $organization->getExternalid(),
            'name' => $organization->getShortname(),
            'formal_name' => $organization->getName(),
            'country' => $organization->getCountry(),
            'icpc_id' => $organization->getIcpcid(),
        ]);

        return $this->importSingle(
            $request,
            $importExport->importOrganizationsJson(...),
            $data,
            'organization',
            created: false,
        );
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
        return 'ta.externalid';
    }
}
