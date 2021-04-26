<?php declare(strict_types=1);

namespace App\Controller\API;

use App\Entity\Contest;
use App\Entity\Team;
use App\Service\ImportExportService;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\QueryBuilder;
use Exception;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Annotations as OA;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @Rest\Route("/contests/{cid}/teams")
 * @OA\Tag(name="Teams")
 * @OA\Parameter(ref="#/components/parameters/cid")
 * @OA\Response(response="404", ref="#/components/schemas/NotFound")
 * @OA\Response(response="401", ref="#/components/schemas/Unauthorized")
 */
class TeamController extends AbstractRestController
{
    /**
     * Get all the teams for this contest
     * @Rest\Get("")
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
     * @OA\Parameter(ref="#/components/parameters/strict")
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
     * Get the given team for this contest
     * @throws NonUniqueResultException
     * @Rest\Get("/{id}")
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
     * @OA\Parameter(ref="#/components/parameters/strict")
     */
    public function singleAction(Request $request, string $id) : Response
    {
        return parent::performSingleAction($request, $id);
    }

    /**
     * Get the photo for the given team
     * @Rest\Get("/{id}/photo.jpg", name="team_photo")
     * @OA\Response(
     *     response="200",
     *     description="Returns the given team photo in JPG format",
     *     @OA\MediaType(mediaType="image/jpeg")
     * )
     * @OA\Parameter(ref="#/components/parameters/id")
     */
    public function photoAction(Request $request, string $id): Response
    {
        /** @var Team $team */
        $team = $this->getQueryBuilder($request)
            ->andWhere(sprintf('%s = :id', $this->getIdField()))
            ->setParameter(':id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        if ($team === null) {
            throw new NotFoundHttpException(sprintf('Object with ID \'%s\' not found', $id));
        }

        $teamPhoto = $this->dj->assetPath($id, 'team', true);

        if (!file_exists($teamPhoto)) {
            throw new NotFoundHttpException('team photo not found');
        }

        $response = new BinaryFileResponse($teamPhoto);
        $response->headers->set('Content-Type', 'image/jpeg');
        return $response;
    }

    /**
     * Add a new team
     *
     * @Rest\Post()
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
                ->setParameter(':category', $request->query->get('category'));
        }

        if ($request->query->has('affiliation')) {
            $queryBuilder
                ->andWhere('t.affiliation = :affiliation')
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
     * @throws Exception
     */
    protected function getIdField(): string
    {
        return sprintf('t.%s', $this->eventLogService->externalIdFieldForEntity(Team::class) ?? 'teamid');
    }
}
