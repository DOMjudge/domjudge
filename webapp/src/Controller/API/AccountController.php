<?php declare(strict_types=1);

namespace App\Controller\API;

use App\Entity\User;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\QueryBuilder;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * @extends AbstractRestController<User, User>
 */
#[Rest\Route(path: '/contests/{cid}')]
#[OA\Tag(name: 'Accounts')]
#[OA\Parameter(ref: '#/components/parameters/cid')]
#[OA\Parameter(ref: '#/components/parameters/strict')]
#[OA\Response(ref: '#/components/responses/NotFound', response: 404)]
#[OA\Response(ref: '#/components/responses/InvalidResponse', response: 400)]
#[OA\Response(ref: '#/components/responses/Unauthenticated', response: 401)]
#[OA\Response(ref: '#/components/responses/Unauthorized', response: 403)]
class AccountController extends AbstractRestController
{
    // Note: this controller is basically a copy of the UserController but then with account endpoints.
    // There are two differences that make it impossible to add it to that controller or extend it:
    // - The /api/contests/<cid> prefix to load the contest
    // - The fact that we have /api/contests/<cid>/account
    // Also it seems to not be possible to overwrite the OA\Tag or the base controller route when
    // extending a controller.
    /**
     * Get all the accounts.
     * @throws NonUniqueResultException
     */
    #[IsGranted(new Expression("is_granted('ROLE_ADMIN') or is_granted('ROLE_API_READER')"))]
    #[Rest\Get(path: '/accounts')]
    #[OA\Response(
        response: 200,
        description: 'Returns all the accounts for this contest',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: User::class))
        )
    )]
    #[OA\Parameter(ref: '#/components/parameters/idlist')]
    #[OA\Parameter(
        name: 'team_id',
        description: 'Only show accounts for the given team',
        in: 'query',
        schema: new OA\Schema(type: 'string')
    )]
    public function listAction(Request $request): Response
    {
        // Get the contest ID to make sure the contest exists
        $this->getContestId($request);
        return parent::performListAction($request);
    }

    /**
     * Get the given account.
     * @throws NonUniqueResultException
     */
    #[IsGranted(new Expression("is_granted('ROLE_ADMIN') or is_granted('ROLE_API_READER')"))]
    #[Rest\Get(path: '/accounts/{id}')]
    #[OA\Response(
        response: 200,
        description: 'Returns the given account',
        content: new Model(type: User::class)
    )]
    #[OA\Parameter(ref: '#/components/parameters/id')]
    public function singleAction(Request $request, string $id): Response
    {
        // Get the contest ID to make sure the contest exists
        $this->getContestId($request);
        return parent::performSingleAction($request, $id);
    }

    /**
     * Get information about the currently logged in account.
     */
    #[Rest\Get(path: '/account')]
    #[OA\Response(
        response: 200,
        description: 'Information about the logged in account',
        content: new Model(type: User::class)
    )]
    public function getCurrentAction(Request $request): Response
    {
        // Get the contest ID to make sure the contest exists.
        $this->getContestId($request);

        $user = $this->dj->getUser();
        if ($user === null) {
            throw new NotFoundHttpException();
        }

        return $this->renderData($request, $user);
    }

    protected function getQueryBuilder(Request $request): QueryBuilder
    {
        $queryBuilder = $this->em->createQueryBuilder()
                                 ->from(User::class, 'u')
                                 ->select('u');

        if ($request->query->has('team')) {
            $queryBuilder
                ->leftJoin('u.team', 't')
                ->andWhere('t.externalid = :team')
                ->setParameter('team', $request->query->get('team'));
        }

        return $queryBuilder;
    }

    protected function getIdField(): string
    {
        return 'u.externalid';
    }
}
