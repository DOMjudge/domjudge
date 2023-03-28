<?php declare(strict_types=1);

namespace App\Controller\API;

use App\Entity\TeamCategory;
use App\Service\ImportExportService;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\QueryBuilder;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @Rest\Route("/contests/{cid}/groups")
 */
#[OA\Tag(name: 'Groups')]
#[OA\Parameter(ref: '#/components/parameters/cid')]
#[OA\Parameter(ref: '#/components/parameters/strict')]
#[OA\Response(ref: '#/components/responses/InvalidResponse', response: 400)]
#[OA\Response(ref: '#/components/responses/Unauthenticated', response: 401)]
#[OA\Response(ref: '#/components/responses/Unauthorized', response: 403)]
#[OA\Response(ref: '#/components/responses/NotFound', response: 404)]
class GroupController extends AbstractRestController
{
    /**
     * Get all the groups for this contest.
     * @Rest\Get("")
     * @throws NonUniqueResultException
     */
    #[OA\Parameter(ref: '#/components/parameters/idlist')]
    #[OA\Parameter(
        name: 'public',
        description: 'Only show public groups, even for users with more permissions',
        in: 'query',
        schema: new OA\Schema(type: 'boolean')
    )]
    #[OA\Response(
        response: 200,
        description: 'Returns all the groups for this contest',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: TeamCategory::class)
            )
        )
    )]
    public function listAction(Request $request): Response
    {
        return parent::performListAction($request);
    }

    /**
     * Get the given group for this contest
     * @throws NonUniqueResultException
     * @Rest\Get("/{id}")
     */
    #[OA\Response(
        response: 200,
        description: 'Returns the given group for this contest',
        content: new Model(type: TeamCategory::class)
    )]
    #[OA\Parameter(ref: '#/components/parameters/id')]
    public function singleAction(Request $request, string $id): Response
    {
        return parent::performSingleAction($request, $id);
    }

    /**
     * Add a new group
     *
     * @Rest\Post()
     * @IsGranted("ROLE_API_WRITER")
     */
    #[OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(ref: '#/components/schemas/TeamCategory')
            ),
            new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(ref: '#/components/schemas/TeamCategory')
            ),
        ]
    )]
    #[OA\Response(
        response: 201,
        description: 'Returns the added group',
        content: new Model(type: TeamCategory::class)
    )]
    public function addAction(Request $request, ImportExportService $importExport): Response
    {
        $saved = [];
        $importExport->importGroupsJson([$request->request->all()], $message, $saved);
        if (!empty($message)) {
            throw new BadRequestHttpException("Error while adding group: $message");
        }

        $group = $saved[0];
        $idField = $this->eventLogService->externalIdFieldForEntity(TeamCategory::class) ?? 'categoryid';
        $method = sprintf('get%s', ucfirst($idField));
        $id = call_user_func([$group, $method]);

        return $this->renderCreateData($request, $saved[0], 'group', $id);
    }

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

    protected function getIdField(): string
    {
        return sprintf('c.%s', $this->eventLogService->externalIdFieldForEntity(TeamCategory::class) ?? 'categoryid');
    }
}
