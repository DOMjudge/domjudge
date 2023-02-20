<?php declare(strict_types=1);

namespace App\Controller\API;

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
 * @OA\Tag(name="Teams")
 * @OA\Parameter(ref="#/components/parameters/cid")
 * @OA\Parameter(ref="#/components/parameters/strict")
 * @OA\Response(response="400", ref="#/components/responses/InvalidResponse")
 * @OA\Response(response="401", ref="#/components/responses/Unauthenticated")
 * @OA\Response(response="403", ref="#/components/responses/Unauthorized")
 * @OA\Response(response="404", ref="#/components/responses/NotFound")
 */
class TeamController extends AbstractRestController
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
     * Get all the teams for this contest.
     * @Rest\Get("contests/{cid}/teams")
     * @Rest\Get("teams")
     * @OA\Response(
     *     response="200",
     *     description="Returns all the teams for this contest",
     *     @OA\JsonContent(
     *         type="array",
     *         @OA\Items(
     *             allOf={
     *                 @OA\Schema(ref=@Model(type=Team::class)),
     *                 @OA\Schema(ref="#/components/schemas/Photo")
     *             }
     *         )
     *     )
     * )
     * @OA\Parameter(ref="#/components/parameters/idlist")
     * @OA\Parameter(
     *     name="category",
     *     in="query",
     *     description="Only show teams for the given category",
     *     @OA\Schema(type="string")
     * )
     * @OA\Parameter(
     *     name="affiliation",
     *     in="query",
     *     description="Only show teams for the given affiliation / organization",
     *     @OA\Schema(type="string")
     * )
     * @OA\Parameter(
     *     name="public",
     *     in="query",
     *     description="Only show visible teams, even for users with more permissions",
     *     @OA\Schema(type="boolean")
     * )
     * @throws NonUniqueResultException
     */
    public function listAction(Request $request): Response
    {
        return parent::performListAction($request);
    }

    /**
     * Get the given team for this contest.
     * @throws NonUniqueResultException
     * @Rest\Get("contests/{cid}/teams/{id}")
     * @Rest\Get("teams/{id}")
     * @OA\Response(
     *     response="200",
     *     description="Returns the given team for this contest",
     *     @OA\JsonContent(
     *         allOf={
     *             @OA\Schema(ref=@Model(type=Team::class)),
     *             @OA\Schema(ref="#/components/schemas/Photo")
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
     * Get the photo for the given team.
     * @Rest\Get("contests/{cid}/teams/{id}/photo", name="team_photo")
     * @Rest\Get("teams/{id}/photo")
     * @OA\Response(
     *     response="200",
     *     description="Returns the given team photo in PNG, JPG or SVG format",
     *     @OA\MediaType(mediaType="image/png"),
     *     @OA\MediaType(mediaType="image/jpeg"),
     *     @OA\MediaType(mediaType="image/svg+xml")
     * )
     * @OA\Parameter(ref="#/components/parameters/id")
     */
    public function photoAction(Request $request, string $id): Response
    {
        /** @var Team $team */
        $team = $this->getQueryBuilder($request)
            ->andWhere(sprintf('%s = :id', $this->getIdField()))
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
     * @Rest\Delete("contests/{cid}/teams/{id}/photo", name="delete_team_photo")
     * @Rest\Delete("teams/{id}/photo")
     * @IsGranted("ROLE_ADMIN")
     * @OA\Response(response="204", description="Deleting photo succeeded")
     * @OA\Parameter(ref="#/components/parameters/id")
     */
    public function deletePhotoAction(Request $request, string $id): Response
    {
        /** @var Team $team */
        $team = $this->getQueryBuilder($request)
            ->andWhere(sprintf('%s = :id', $this->getIdField()))
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
     * @Rest\POST("contests/{cid}/teams/{id}/photo", name="post_team_photo")
     * @Rest\POST("teams/{id}/photo")
     * @Rest\PUT("contests/{cid}/teams/{id}/photo", name="put_team_photo")
     * @Rest\PUT("teams/{id}/photo")
     * @OA\RequestBody(
     *     required=true,
     *     @OA\MediaType(
     *         mediaType="multipart/form-data",
     *         @OA\Schema(
     *             required={"photo"},
     *             @OA\Property(
     *                 property="photo",
     *                 type="string",
     *                 format="binary",
     *                 description="The photo to use."
     *             )
     *         )
     *     )
     * )
     * @IsGranted("ROLE_ADMIN")
     * @OA\Response(response="204", description="Setting photo succeeded")
     * @OA\Parameter(ref="#/components/parameters/id")
     */
    public function setPhotoAction(Request $request, string $id, ValidatorInterface $validator): Response
    {
        /** @var Team $team */
        $team = $this->getQueryBuilder($request)
            ->andWhere(sprintf('%s = :id', $this->getIdField()))
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        if ($team === null) {
            throw new NotFoundHttpException(sprintf('Object with ID \'%s\' not found', $id));
        }

        /** @var UploadedFile $photo */
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
     *
     * @Rest\Post("contests/{cid}/teams")
     * @Rest\Post("teams")
     * @IsGranted("ROLE_API_WRITER")
     * @OA\RequestBody(
     *     required=true,
     *     @OA\MediaType(
     *         mediaType="multipart/form-data",
     *         @OA\Schema(ref="#/components/schemas/Team")
     *     ),
     *     @OA\MediaType(
     *         mediaType="application/json",
     *         @OA\Schema(ref="#/components/schemas/Team")
     *     )
     * )
     * @OA\Response(
     *     response="201",
     *     description="Returns the added team",
     *     @Model(type=Team::class)
     * )
     */
    public function addAction(Request $request, ImportExportService $importExport): Response
    {
        $saved = [];
        $importExport->importTeamsJson([$request->request->all()], $message, $saved);
        if (!empty($message)) {
            throw new BadRequestHttpException("Error while adding team: $message");
        }

        $team = $saved[0];
        $idField = $this->eventLogService->externalIdFieldForEntity(Team::class) ?? 'teamid';
        $method = sprintf('get%s', ucfirst($idField));
        $id = call_user_func([$team, $method]);

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
        return sprintf('t.%s', $this->eventLogService->externalIdFieldForEntity(Team::class) ?? 'teamid');
    }
}
