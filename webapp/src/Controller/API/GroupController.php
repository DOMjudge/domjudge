<?php declare(strict_types=1);

namespace App\Controller\API;

use App\DataTransferObject\TeamCategoryPost;
use App\DataTransferObject\TeamCategoryPut;
use App\Entity\TeamCategory;
use App\Service\ImportExportService;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\QueryBuilder;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * @extends AbstractRestController<TeamCategory, TeamCategory>
 */
#[Rest\Route('/contests/{cid}/groups')]
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
     * @throws NonUniqueResultException
     */
    #[Rest\Get('')]
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
     */
    #[Rest\Get('/{id}')]
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
     */
    #[IsGranted('ROLE_API_WRITER')]
    #[Rest\Post]
    #[OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(ref: new Model(type: TeamCategoryPost::class))
            ),
        ]
    )]
    #[OA\Response(
        response: 201,
        description: 'Returns the added group',
        content: new Model(type: TeamCategory::class)
    )]
    public function addAction(
        #[MapRequestPayload(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        TeamCategoryPost $teamCategoryPost,
        Request $request,
        ImportExportService $importExport,
    ): Response {
        $saved = [];
        $groupData = [
            'name' => $teamCategoryPost->name,
            'hidden' => $teamCategoryPost->hidden,
            'icpc_id' => $teamCategoryPost->icpcId,
            'sortorder' => $teamCategoryPost->sortorder,
            'color' => $teamCategoryPost->color,
            'allow_self_registration' => $teamCategoryPost->allowSelfRegistration,
        ];
        $importExport->importGroupsJson([$groupData], $message, $saved);
        if (!empty($message)) {
            throw new BadRequestHttpException("Error while adding group: $message");
        }

        /** @var TeamCategory $group */
        $group = $saved[0];
        $id = $group->getexternalid();

        return $this->renderCreateData($request, $saved[0], 'group', $id);
    }

    /**
     * Update an existing group or create one with the given ID
     */
    #[IsGranted('ROLE_API_WRITER')]
    #[Rest\Put('/{id}')]
    #[OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(ref: new Model(type: TeamCategoryPut::class))
            ),
        ]
    )]
    #[OA\Response(
        response: 201,
        description: 'Returns the updated / added group',
        content: new Model(type: TeamCategory::class)
    )]
    public function updateAction(
        #[MapRequestPayload(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        TeamCategoryPut $teamCategoryPut,
        Request $request,
        ImportExportService $importExport,
        string $id,
    ): Response {
        $saved = [];
        $groupData = [
            'id' => $teamCategoryPut->id,
            'name' => $teamCategoryPut->name,
            'hidden' => $teamCategoryPut->hidden,
            'icpc_id' => $teamCategoryPut->icpcId,
            'sortorder' => $teamCategoryPut->sortorder,
            'color' => $teamCategoryPut->color,
            'allow_self_registration' => $teamCategoryPut->allowSelfRegistration,
        ];
        if ($id !== $teamCategoryPut->id) {
            throw new BadRequestHttpException('ID in URL does not match ID in payload');
        }
        $importExport->importGroupsJson([$groupData], $message, $saved);
        if (!empty($message)) {
            throw new BadRequestHttpException("Error while adding group: $message");
        }

        /** @var TeamCategory $group */
        $group = $saved[0];
        $id = $group->getexternalid();

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
        return 'c.externalid';
    }
}
