<?php declare(strict_types=1);

namespace App\Controller\API;

use App\Entity\User;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Service\ImportExportService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\QueryBuilder;
use Exception;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\Model;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

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
     * @var ImportExportService
     */
    protected $importExportService;

    /**
     * @param ImportExportService    $importExportService
     */
    public function __construct(EntityManagerInterface $entityManager, DOMJudgeService $dj, EventLogService $eventLogService, ImportExportService $importExportService) {
        parent::__construct($entityManager, $dj, $eventLogService);
        $this->importExportService = $importExportService;
    }

    /**
     * Add one or more groups.
     * @param Request $request
     * @return string
     * @Rest\Post("/groups")
     * @IsGranted("ROLE_ADMIN")
     * @SWG\Post(consumes={"multipart/form-data"})
     * @SWG\Parameter(
     *     name="tsv",
     *     in="formData",
     *     type="file",
     *     required=true,
     *     description="The groups.tsv files to import."
     * )
     * @SWG\Response(
     *     response="200",
     *     description="Returns a (currently meaningless) status message.",
     * )
     * @throws BadRequestHttpException
     */
    public function addGroupAction(Request $request)
    {
        /** @var UploadedFile $tsvFile */
        $tsvFile = $request->files->get('tsv') ?: [];
        if ($this->importExportService->importTsv('groups', $tsvFile, $message)) {
            // TODO: better return all groups here
            return "New groups successfully added.";
        } else {
            throw new BadRequestHttpException("Error while adding groups: $message");
        }
    }

    /**
     * Add one or more teams.
     * @param Request $request
     * @return string
     * @Rest\Post("/teams")
     * @IsGranted("ROLE_ADMIN")
     * @SWG\Post(consumes={"multipart/form-data"})
     * @SWG\Parameter(
     *     name="tsv",
     *     in="formData",
     *     type="file",
     *     required=true,
     *     description="The teams2.tsv files to import."
     * )
     * @SWG\Response(
     *     response="200",
     *     description="Returns a (currently meaningless) status message.",
     * )
     * @throws BadRequestHttpException
     */
    public function addTeamsAction(Request $request)
    {
        /** @var UploadedFile $tsvFile */
        $tsvFile = $request->files->get('tsv') ?: [];
        if ($this->importExportService->importTsv('teams', $tsvFile, $message)) {
            // TODO: better return all teams here?
            return "New teams successfully added.";
        } else {
            throw new BadRequestHttpException("Error while adding teams: $message");
        }
    }

    /**
     * Add accounts to teams.
     * @param Request $request
     * @return string
     * @Rest\Post("/accounts")
     * @IsGranted("ROLE_ADMIN")
     * @SWG\Post(consumes={"multipart/form-data"})
     * @SWG\Parameter(
     *     name="tsv",
     *     in="formData",
     *     type="file",
     *     required=true,
     *     description="The accounts.tsv files to import."
     * )
     * @SWG\Response(
     *     response="200",
     *     description="Returns a (currently meaningless) status message.",
     * )
     * @throws BadRequestHttpException
     * @throws Exception
     */
    public function addAccountsAction(Request $request)
    {
        /** @var UploadedFile $tsvFile */
        $tsvFile = $request->files->get('tsv') ?: [];
        $ret = $this->importExportService->importTsv('accounts', $tsvFile, $message);
        if ($ret >= 0) {
            // TODO: better return all teams here?
            return "$ret new accounts added successfully.";
        } else {
            throw new BadRequestHttpException("Error while adding accounts: $message");
        }
    }

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
