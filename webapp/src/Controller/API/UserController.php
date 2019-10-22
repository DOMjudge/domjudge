<?php declare(strict_types=1);

namespace App\Controller\API;

use App\Entity\User;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\QueryBuilder;
use Exception;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\Model;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Rest\Route("/api/v4/users", defaults={"_format" = "json"})
 * @Rest\Prefix("/api/users")
 * @Rest\NamePrefix("user_")
 * @SWG\Tag(name="Users")
 * @SWG\Response(response="404", ref="#/definitions/NotFound")
 * @SWG\Response(response="401", ref="#/definitions/Unauthorized")
 */
class UserController extends AbstractRestController
{
    /**
     * Get all the users
     * @param Request $request
     * @return Response
     * @Rest\Get("")
     * @IsGranted({"ROLE_ADMIN", "ROLE_API_READER"})
     * @SWG\Response(
     *     response="200",
     *     description="Returns all the users for this contest",
     *     @SWG\Schema(
     *         type="array",
     *         @SWG\Items(ref=@Model(type=User::class))
     *     )
     * )
     * @SWG\Parameter(ref="#/parameters/idlist")
     * @SWG\Parameter(
     *     name="team_id",
     *     in="query",
     *     type="string",
     *     description="Only show users for the given team"
     * )
     * @throws NonUniqueResultException
     */
    public function listAction(Request $request)
    {
        return parent::performListAction($request);
    }

    /**
     * Get the given user
     * @param Request $request
     * @param string  $id
     * @return Response
     * @throws NonUniqueResultException
     * @Rest\Get("/{id}")
     * @IsGranted({"ROLE_ADMIN", "ROLE_API_READER"})
     * @SWG\Response(
     *     response="200",
     *     description="Returns the given user",
     *     @Model(type=User::class)
     * )
     * @SWG\Parameter(ref="#/parameters/id")
     */
    public function singleAction(Request $request, string $id)
    {
        return parent::performSingleAction($request, $id);
    }

    /**
     * @inheritdoc
     */
    protected function getQueryBuilder(Request $request): QueryBuilder
    {
        $queryBuilder = $this->em->createQueryBuilder()
                                 ->from(User::class, 'u')
                                 ->select('u');

        if ($request->query->has('team')) {
            $queryBuilder
                ->andWhere('u.team = :team')
                ->setParameter(':team', $request->query->get('team'));
        }

        return $queryBuilder;
    }

    /**
     * @inheritdoc
     * @throws Exception
     */
    protected function getIdField(): string
    {
        return 'u.userid';
    }
}
