<?php declare(strict_types=1);

namespace App\Controller\API;

use App\Entity\User;
use App\Service\ConfigurationService;
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
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
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
     * @param EntityManagerInterface $entityManager
     * @param DOMJudgeService        $dj
     * @param ConfigurationService   $config
     * @param EventLogService        $eventLogService
     * @param ImportExportService    $importExportService
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        DOMJudgeService $dj,
        ConfigurationService $config,
        EventLogService $eventLogService,
        ImportExportService $importExportService
    ) {
        parent::__construct($entityManager, $dj, $config, $eventLogService);
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
     *     required=false,
     *     description="The groups.tsv files to import."
     * )
     * @SWG\Parameter(
     *     name="json",
     *     in="formData",
     *     type="file",
     *     required=false,
     *     description="The groups.json files to import."
     * )
     * @SWG\Response(
     *     response="200",
     *     description="Returns a (currently meaningless) status message.",
     * )
     * @throws Exception
     */
    public function addGroupsAction(Request $request)
    {
        /** @var UploadedFile $tsvFile */
        $tsvFile = $request->files->get('tsv') ?: [];
        /** @var UploadedFile $jsonFile */
        $jsonFile = $request->files->get('json') ?: [];
        if ((!$tsvFile && !$jsonFile) || ($tsvFile && $jsonFile)) {
            throw new BadRequestHttpException('Supply exactly one of \'json\' or \'tsv\'');
        }
        $message = null;
        if ($tsvFile && ($result = $this->importExportService->importTsv('groups', $tsvFile, $message))) {
            // TODO: better return all groups here
            return "$result new group(s) successfully added.";
        } elseif ($jsonFile && ($result = $this->importExportService->importJson('groups', $jsonFile, $message))) {
            // TODO: better return all groups here
            return "$result new group(s) successfully added.";
        } else {
            throw new BadRequestHttpException("Error while adding groups: $message");
        }
    }

    /**
     * Add one or more organizations.
     *
     * @param Request $request
     *
     * @return string
     * @Rest\Post("/organizations")
     * @IsGranted("ROLE_ADMIN")
     * @SWG\Post(consumes={"multipart/form-data"})
     * @SWG\Parameter(
     *     name="json",
     *     in="formData",
     *     type="file",
     *     required=true,
     *     description="The organizations.json files to import."
     * )
     * @SWG\Response(
     *     response="200",
     *     description="Returns a (currently meaningless) status message.",
     * )
     * @throws Exception
     */
    public function addOrganizationsAction(Request $request)
    {
        /** @var UploadedFile $jsonFile */
        $jsonFile = $request->files->get('json') ?: [];
        $message = null;
        if ($result = $this->importExportService->importJson('organizations', $jsonFile, $message)) {
            // TODO: better return all organizations here
            return "$result new organization(s) successfully added.";
        } else {
            throw new BadRequestHttpException("Error while adding organizations: $message");
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
     *     required=false,
     *     description="The teams.tsv files to import."
     * )
     * @SWG\Parameter(
     *     name="json",
     *     in="formData",
     *     type="file",
     *     required=false,
     *     description="The teams.json files to import."
     * )
     * @SWG\Response(
     *     response="200",
     *     description="Returns a (currently meaningless) status message.",
     * )
     * @throws Exception
     */
    public function addTeamsAction(Request $request)
    {
        /** @var UploadedFile $tsvFile */
        $tsvFile = $request->files->get('tsv') ?: [];
        /** @var UploadedFile $jsonFile */
        $jsonFile = $request->files->get('json') ?: [];
        if ((!$tsvFile && !$jsonFile) || ($tsvFile && $jsonFile)) {
            throw new BadRequestHttpException('Supply exactly one of \'json\' or \'tsv\'');
        }
        $message = null;
        if ($tsvFile && ($result = $this->importExportService->importTsv('teams', $tsvFile, $message))) {
            // TODO: better return all teams here?
            return "$result new team(s) successfully added.";
        } elseif ($jsonFile && ($result = $this->importExportService->importJson('teams', $jsonFile, $message))) {
            // TODO: better return all teams here?
            return "$result new team(s) successfully added.";
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
     * @throws Exception
     */
    public function addAccountsAction(Request $request)
    {
        /** @var UploadedFile $tsvFile */
        $tsvFile = $request->files->get('tsv') ?: [];
        $ret = $this->importExportService->importTsv('accounts', $tsvFile, $message);
        if ($ret >= 0) {
            // TODO: better return all teams here?
            return "$ret new account(s) added successfully.";
        } else {
            throw new BadRequestHttpException("Error while adding accounts: $message");
        }
    }

    /**
     * Get all the users
     * @param Request $request
     * @return Response
     * @Rest\Get("")
     * @Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_API_READER')")
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
     * @Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_API_READER')")
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
