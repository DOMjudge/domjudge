<?php declare(strict_types=1);

namespace App\Controller\API;

use App\DataTransferObject\AddTeam;
use App\Entity\Contest;
use App\Entity\Team;
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
 * @extends AbstractRestController<Team, Team>
 */
#[Rest\Route('/')]
#[OA\Tag(name: 'Teams')]
#[OA\Parameter(ref: '#/components/parameters/cid')]
#[OA\Parameter(ref: '#/components/parameters/strict')]
#[OA\Response(ref: '#/components/responses/InvalidResponse', response: 400)]
#[OA\Response(ref: '#/components/responses/Unauthenticated', response: 401)]
#[OA\Response(ref: '#/components/responses/Unauthorized', response: 403)]
#[OA\Response(ref: '#/components/responses/NotFound', response: 404)]
class TeamController extends AbstractRestController
{
    public function __construct(
        EntityManagerInterface $entityManager,
        DOMJudgeService $dj,
        ConfigurationService $config,
        EventLogService $eventLogService,
        protected readonly AssetUpdateService $assetUpdater
    ) {
        parent::__construct($entityManager, $dj, $config, $eventLogService);
    }

    /**
     * Get all the teams for this contest.
     * @throws NonUniqueResultException
     */
    #[Rest\Get('contests/{cid}/teams')]
    #[Rest\Get('teams')]
    #[OA\Response(
        response: 200,
        description: 'Returns all the teams for this contest',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Team::class))
        )
    )]
    #[OA\Parameter(ref: '#/components/parameters/idlist')]
    #[OA\Parameter(
        name: 'category',
        description: 'Only show teams for the given category',
        in: 'query',
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'affiliation',
        description: 'Only show teams for the given affiliation / organization',
        in: 'query',
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'public',
        description: 'Only show visible teams, even for users with more permissions',
        in: 'query',
        schema: new OA\Schema(type: 'boolean')
    )]
    public function listAction(Request $request): Response
    {
        if (!$this->config->get('enable_ranking') && !$this->dj->checkrole('jury')) {
            throw new BadRequestHttpException("teams list not available.");
        }
        return parent::performListAction($request);
    }

    /**
     * Get the given team for this contest.
     * @throws NonUniqueResultException
     */
    #[Rest\Get('contests/{cid}/teams/{id}')]
    #[Rest\Get('teams/{id}')]
    #[OA\Response(
        response: 200,
        description: 'Returns the given team for this contest',
        content: new OA\JsonContent(ref: new Model(type: Team::class))
    )]
    #[OA\Parameter(ref: '#/components/parameters/id')]
    public function singleAction(Request $request, string $id): Response
    {
        if (!$this->config->get('enable_ranking') && !$this->dj->checkrole('jury')) {
            throw new BadRequestHttpException("team not available.");
        }
        return parent::performSingleAction($request, $id);
    }

    /**
     * Get the photo for the given team.
     */
    #[Rest\Get('contests/{cid}/teams/{id}/photo', name: 'team_photo')]
    #[Rest\Get('teams/{id}/photo', name: 'no_contest_team_photo')]
    #[OA\Response(
        response: 200,
        description: 'Returns the given team photo in PNG, JPG or SVG format',
        content: [
            new OA\MediaType(mediaType: 'image/png'),
            new OA\MediaType(mediaType: 'image/jpeg'),
            new OA\MediaType(mediaType: 'image/svg+xml'),
        ]
    )]
    #[OA\Parameter(ref: '#/components/parameters/id')]
    public function photoAction(Request $request, string $id): Response
    {
        if (!$this->config->get('enable_ranking') && !$this->dj->checkrole('jury')) {
            throw new BadRequestHttpException("team photo not available.");
        }
        /** @var Team|null $team */
        $team = $this->getQueryBuilder($request)
            ->andWhere('t.externalid = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        if ($team === null) {
            throw new NotFoundHttpException(sprintf('Object with ID \'%s\' not found', $id));
        }

        $teamPhoto = $this->dj->assetPath($id, 'team', true);

        if ($teamPhoto && file_exists($teamPhoto)) {
            return static::sendBinaryFileResponse($request, $teamPhoto);
        }
        throw new NotFoundHttpException('Team photo not found');
    }

    /**
     * Delete the photo for the given team.
     */
    #[IsGranted('ROLE_ADMIN')]
    #[Rest\Delete('contests/{cid}/teams/{id}/photo', name: 'delete_team_photo')]
    #[Rest\Delete('teams/{id}/photo')]
    #[OA\Response(response: 204, description: 'Deleting photo succeeded')]
    #[OA\Parameter(ref: '#/components/parameters/id')]
    public function deletePhotoAction(Request $request, string $id): Response
    {
        $contestId = null;
        /** @var Team|null $team */
        $team = $this->getQueryBuilder($request)
            ->andWhere('t.externalid = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        if ($team === null) {
            throw new NotFoundHttpException(sprintf('Object with ID \'%s\' not found', $id));
        }

        $team->setClearPhoto(true);

        $this->assetUpdater->updateAssets($team);
        if ($request->attributes->has('cid')) {
            $contestId = $this->getContestId($request);
        }
        $this->eventLogService->log('teams', $team->getTeamid(), EventLogService::ACTION_UPDATE,
            $contestId);

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * Set the photo for the given team.
     */
    #[IsGranted('ROLE_ADMIN')]
    #[Rest\Post('contests/{cid}/teams/{id}/photo', name: 'post_team_photo')]
    #[Rest\Post('teams/{id}/photo')]
    #[Rest\Put('contests/{cid}/teams/{id}/photo', name: 'put_team_photo')]
    #[Rest\Put('teams/{id}/photo')]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                required: ['photo'],
                properties: [
                    new OA\Property(
                        property: 'photo',
                        description: 'The photo to use.',
                        type: 'string',
                        format: 'binary'
                    ),
                ]
            )
        )
    )]
    #[OA\Response(response: 204, description: 'Setting photo succeeded')]
    #[OA\Parameter(ref: '#/components/parameters/id')]
    public function setPhotoAction(Request $request, string $id, ValidatorInterface $validator): Response
    {
        /** @var Team|null $team */
        $team = $this->getQueryBuilder($request)
            ->andWhere('t.externalid = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        if ($team === null) {
            throw new NotFoundHttpException(sprintf('Object with ID \'%s\' not found', $id));
        }

        /** @var UploadedFile|null $photo */
        $photo = $request->files->get('photo');

        if (!$photo) {
            return new JsonResponse(['title' => 'Validation failed', 'errors' => ['Please supply a photo']], Response::HTTP_BAD_REQUEST);
        }

        $team->setPhotoFile($photo);

        if ($errorResponse = $this->responseForErrors($validator->validate($team), true)) {
            return $errorResponse;
        }

        $this->assetUpdater->updateAssets($team);
        $contestId = null;
        if ($request->attributes->has('cid')) {
            $contestId = $this->getContestId($request);
        }
        $this->eventLogService->log('teams', $team->getTeamid(), EventLogService::ACTION_UPDATE,
            $contestId);

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * Add a new team.
     */
    #[IsGranted('ROLE_API_WRITER')]
    #[Rest\Post('contests/{cid}/teams')]
    #[Rest\Post('teams')]
    #[OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(ref: new Model(type: AddTeam::class))),
        ]
    )]
    #[OA\Response(
        response: 201,
        description: 'Returns the added team',
        content: new Model(type: Team::class)
    )]
    public function addAction(
        #[MapRequestPayload(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        AddTeam $addTeam,
        Request $request,
        ImportExportService $importExport
    ): Response {
        $saved = [];
        $importExport->importTeamsJson([
            [
                'id' => $addTeam->id,
                'icpc_id' => $addTeam->icpcId,
                'label' => $addTeam->label,
                'group_ids' => $addTeam->groupIds,
                'name' => $addTeam->name,
                'display_name' => $addTeam->displayName,
                'public_description' => $addTeam->publicDescription,
                'members' => $addTeam->members,
                'location' => [
                    'description' => $addTeam->location?->description,
                ],
                'organization_id' => $addTeam->organizationId,
            ],
        ], $message, $saved);
        if (!empty($message)) {
            throw new BadRequestHttpException("Error while adding team: $message");
        }

        /** @var Team $team */
        $team = $saved[0];
        $id = $team->getExternalid();

        return $this->renderCreateData($request, $saved[0], 'team', $id);
    }

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
                ->andWhere('t.category = :category')
                ->setParameter('category', $request->query->get('category'));
        }

        if ($request->query->has('affiliation')) {
            $queryBuilder
                ->andWhere('t.affiliation = :affiliation')
                ->setParameter('affiliation', $request->query->get('affiliation'));
        }

        if (!$this->dj->checkrole('api_reader') || $request->query->getBoolean('public')) {
            $queryBuilder->andWhere('tc.visible = 1');
        }

        if ($request->attributes->has('cid')) {
            $contest = $this->em->getRepository(Contest::class)->find($this->getContestId($request));
            if (!$contest->isOpenToAllTeams()) {
                $queryBuilder
                    ->andWhere('c.cid = :cid OR cc.cid = :cid')
                    ->setParameter('cid', $contest->getCid());
            }
        }

        return $queryBuilder;
    }

    protected function getIdField(): string
    {
        return 't.externalid';
    }
}
