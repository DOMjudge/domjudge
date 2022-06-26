<?php declare(strict_types=1);

namespace App\Controller\API;

use App\Entity\User;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\QueryBuilder;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Annotations as OA;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @Rest\Route("/contests/{cid}")
 * @OA\Tag(name="Accounts")
 * @OA\Parameter(ref="#/components/parameters/cid")
 * @OA\Response(response="404", ref="#/components/responses/NotFound")
 * @OA\Response(response="400", ref="#/components/responses/InvalidResponse")
 * @OA\Response(response="401", ref="#/components/responses/Unauthenticated")
 * @OA\Response(response="403", ref="#/components/responses/Unauthorized")
 */
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
     * @Rest\Get("/accounts")
     * @Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_API_READER')")
     * @OA\Response(
     *     response="200",
     *     description="Returns all the accounts for this contest",
     *     @OA\JsonContent(
     *         type="array",
     *         @OA\Items(ref=@Model(type=User::class))
     *     )
     * )
     * @OA\Parameter(ref="#/components/parameters/idlist")
     * @OA\Parameter(ref="#/components/parameters/strict")
     * @OA\Parameter(
     *     name="team_id",
     *     in="query",
     *     description="Only show accounts for the given team",
     *     @OA\Schema(type="string")
     * )
     * @throws NonUniqueResultException
     */
    public function listAction(Request $request): Response
    {
        // Get the contest ID to make sure the contest exists
        $this->getContestId($request);
        return parent::performListAction($request);
    }

    /**
     * Get the given account.
     * @throws NonUniqueResultException
     * @Rest\Get("/accounts/{id}")
     * @Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_API_READER')")
     * @OA\Response(
     *     response="200",
     *     description="Returns the given account",
     *     @Model(type=User::class)
     * )
     * @OA\Parameter(ref="#/components/parameters/id")
     * @OA\Parameter(ref="#/components/parameters/strict")
     */
    public function singleAction(Request $request, string $id): Response
    {
        // Get the contest ID to make sure the contest exists
        $this->getContestId($request);
        return parent::performSingleAction($request, $id);
    }

    /**
     * Get information about the currently logged in account.
     * @Rest\Get("/account")
     * @OA\Response(
     *     response="200",
     *     description="Information about the logged in account",
     *     @Model(type=User::class)
     * )
     * @OA\Parameter(ref="#/components/parameters/strict")
     */
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
                ->andWhere('u.team = :team')
                ->setParameter('team', $request->query->get('team'));
        }

        return $queryBuilder;
    }

    protected function getIdField(): string
    {
        return sprintf('u.%s', $this->eventLogService->externalIdFieldForEntity(User::class) ?? 'userid');
    }
}
