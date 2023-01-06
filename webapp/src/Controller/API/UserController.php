<?php declare(strict_types=1);

namespace App\Controller\API;

use App\Entity\Role;
use App\Entity\Team;
use App\Entity\User;
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
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @Rest\Route("/users", defaults={"_format" = "json"})
 * @OA\Tag(name="Users")
 * @OA\Response(response="400", ref="#/components/responses/InvalidResponse")
 * @OA\Response(response="401", ref="#/components/responses/Unauthenticated")
 * @OA\Response(response="403", ref="#/components/responses/Unauthorized")
 * @OA\Response(response="404", ref="#/components/responses/NotFound")
 */
class UserController extends AbstractRestController
{
    protected ImportExportService $importExportService;

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
     * @Rest\Post("/groups")
     * @IsGranted("ROLE_ADMIN")
     * @OA\RequestBody(
     *     required=true,
     *     @OA\MediaType(
     *         mediaType="multipart/form-data",
     *         @OA\Schema(
     *             @OA\Property(
     *                 property="tsv",
     *                 type="string",
     *                 format="binary",
     *                 description="The groups.tsv files to import."
     *             ),
     *             @OA\Property(
     *                 property="json",
     *                 type="string",
     *                 format="binary",
     *                 description="The groups.json files to import."
     *             )
     *         )
     *     )
     * )
     * @OA\Response(
     *     response="200",
     *     description="Returns a (currently meaningless) status message.",
     * )
     */
    public function addGroupsAction(Request $request): string
    {
        /** @var UploadedFile $tsvFile */
        $tsvFile = $request->files->get('tsv') ?: [];
        /** @var UploadedFile $jsonFile */
        $jsonFile = $request->files->get('json') ?: [];
        if ((!$tsvFile && !$jsonFile) || ($tsvFile && $jsonFile)) {
            throw new BadRequestHttpException('Supply exactly one of \'json\' or \'tsv\'');
        }
        $message = null;
        $result = -1;
        if ((($tsvFile && ($result = $this->importExportService->importTsv('groups', $tsvFile, $message))) ||
             ($jsonFile && ($result = $this->importExportService->importJson('groups', $jsonFile, $message)))) &&
            $result >= 0) {
             return "$result new group(s) successfully added.";
        } else {
            throw new BadRequestHttpException("Error while adding groups: $message");
        }
    }

    /**
     * Add one or more organizations.
     *
     * @Rest\Post("/organizations")
     * @IsGranted("ROLE_ADMIN")
     * @OA\RequestBody(
     *     required=true,
     *     @OA\MediaType(
     *         mediaType="multipart/form-data",
     *         @OA\Schema(
     *             required={"json"},
     *             @OA\Property(
     *                 property="json",
     *                 type="string",
     *                 format="binary",
     *                 description="The organizations.json files to import."
     *             )
     *         )
     *     )
     * )
     * @OA\Response(
     *     response="200",
     *     description="Returns a (currently meaningless) status message.",
     * )
     */
    public function addOrganizationsAction(Request $request): string
    {
        /** @var UploadedFile $jsonFile */
        $jsonFile = $request->files->get('json') ?: [];
        $message = null;
        if ($result = $this->importExportService->importJson('organizations', $jsonFile, $message) && $result >= 0) {
            // TODO: better return all organizations here
            return "$result new organization(s) successfully added.";
        } else {
            throw new BadRequestHttpException("Error while adding organizations: $message");
        }
    }

    /**
     * Add one or more teams.
     * @Rest\Post("/teams")
     * @IsGranted("ROLE_ADMIN")
     * @OA\RequestBody(
     *     required=true,
     *     @OA\MediaType(
     *         mediaType="multipart/form-data",
     *         @OA\Schema(
     *             @OA\Property(
     *                 property="tsv",
     *                 type="string",
     *                 format="binary",
     *                 description="The teams.tsv files to import."
     *             ),
     *             @OA\Property(
     *                 property="json",
     *                 type="string",
     *                 format="binary",
     *                 description="The teams.json files to import."
     *             )
     *         )
     *     )
     * )
     * @OA\Response(
     *     response="200",
     *     description="Returns a (currently meaningless) status message.",
     * )
     */
    public function addTeamsAction(Request $request): string
    {
        /** @var UploadedFile $tsvFile */
        $tsvFile = $request->files->get('tsv') ?: [];
        /** @var UploadedFile $jsonFile */
        $jsonFile = $request->files->get('json') ?: [];
        if ((!$tsvFile && !$jsonFile) || ($tsvFile && $jsonFile)) {
            throw new BadRequestHttpException('Supply exactly one of \'json\' or \'tsv\'');
        }
        $message = null;
        if ((($tsvFile && ($result = $this->importExportService->importTsv('teams', $tsvFile, $message))) ||
             ($jsonFile && ($result = $this->importExportService->importJson('teams', $jsonFile, $message)))) &&
            $result >= 0) {
            // TODO: better return all teams here?
            return "$result new team(s) successfully added.";
        } else {
            throw new BadRequestHttpException("Error while adding teams: $message");
        }
    }

    /**
     * Add accounts to teams.
     * @Rest\Post("/accounts")
     * @IsGranted("ROLE_ADMIN")
     * @OA\RequestBody(
     *     required=true,
     *     @OA\MediaType(
     *         mediaType="multipart/form-data",
     *         @OA\Schema(
     *             @OA\Property(
     *                 property="tsv",
     *                 type="string",
     *                 format="binary",
     *                 description="The accounts.tsv files to import."
     *             ),
     *             @OA\Property(
     *                 property="json",
     *                 type="string",
     *                 format="binary",
     *                 description="The accounts.json files to import."
     *             ),
     *             @OA\Property(
     *                 property="yaml",
     *                 type="string",
     *                 format="binary",
     *                 description="The accounts.yaml files to import."
     *             )
     *         )
     *     )
     * )
     * @OA\Response(
     *     response="200",
     *     ref="#/components/responses/PostAccountResponse"
     * )
     *
     * @throws BadRequestHttpException
     */
    public function addAccountsAction(Request $request): string
    {
        /** @var UploadedFile $tsvFile */
        $tsvFile = $request->files->get('tsv') ?: [];
        /** @var UploadedFile $jsonFile */
        $jsonFile = $request->files->get('json') ?: [];
        /** @var UploadedFile $yamlFile */
        $yamlFile      = $request->files->get('yaml') ?: [];
        $providedFiles = array_filter([$tsvFile, $jsonFile, $yamlFile]);
        if (count($providedFiles) !== 1) {
            throw new BadRequestHttpException('Supply exactly one of \'json\', \'yaml\' or \'tsv\'');
        }

        // Treat the YAML as JSON, since we can parse both.
        if ($yamlFile) {
            $jsonFile = $yamlFile;
        }

        $message = null;
        if ((($tsvFile && ($result = $this->importExportService->importTsv('accounts', $tsvFile, $message))) ||
             ($jsonFile && ($result = $this->importExportService->importJson('accounts', $jsonFile, $message)))) &&
            $result >= 0) {
            // TODO: better return all accounts here?
            return "$result new account(s) successfully added.";
        } else {
            throw new BadRequestHttpException("Error while adding accounts: $message");
        }
    }

    /**
     * Get all the users.
     * @Rest\Get("")
     * @Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_API_READER')")
     * @OA\Response(
     *     response="200",
     *     description="Returns all the users for this contest",
     *     @OA\JsonContent(
     *         type="array",
     *         @OA\Items(ref=@Model(type=User::class))
     *     )
     * )
     * @OA\Parameter(ref="#/components/parameters/idlist")
     * @OA\Parameter(
     *     name="team_id",
     *     in="query",
     *     description="Only show users for the given team",
     *     @OA\Schema(type="string")
     * )
     * @throws NonUniqueResultException
     */
    public function listAction(Request $request): Response
    {
        return parent::performListAction($request);
    }

    /**
     * Get the given user.
     * @throws NonUniqueResultException
     * @Rest\Get("/{id}")
     * @Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_API_READER')")
     * @OA\Response(
     *     response="200",
     *     description="Returns the given user",
     *     @Model(type=User::class)
     * )
     * @OA\Parameter(ref="#/components/parameters/id")
     */
    public function singleAction(Request $request, string $id): Response
    {
        return parent::performSingleAction($request, $id);
    }

    /**
     * Add a new user.
     *
     * @Rest\Post()
     * @IsGranted("ROLE_API_WRITER")
     * @OA\RequestBody(
     *     required=true,
     *     @OA\MediaType(
     *         mediaType="multipart/form-data",
     *         @OA\Schema(ref="#/components/schemas/AddUser")
     *     ),
     *     @OA\MediaType(
     *         mediaType="application/json",
     *         @OA\Schema(ref="#/components/schemas/AddUser")
     *     )
     * )
     * @OA\Response(
     *     response="201",
     *     description="Returns the added user",
     *     @Model(type=User::class)
     * )
     */
    public function addAction(Request $request): Response
    {
        $required = [
            'username',
            'name',
            'password',
            'roles',
        ];

        foreach ($required as $argument) {
            if (!$request->request->has($argument)) {
                throw new BadRequestHttpException(
                    sprintf("Argument '%s' is mandatory", $argument));
            }
        }

        if ($this->em->getRepository(User::class)->findOneBy(['username' => $request->request->get('username')])) {
            throw new BadRequestHttpException(sprintf("User %s already exists", $request->request->get('username')));
        }

        $user = new User();
        $user
            ->setUsername($request->request->get('username'))
            ->setName($request->request->get('name'))
            ->setEmail($request->request->get('email'))
            ->setIpAddress($request->request->get('ip'))
            ->setPlainPassword($request->request->get('password'))
            ->setEnabled($request->request->getBoolean('enabled', true));

        if ($request->request->get('team_id')) {
            /** @var Team $team */
            $team = $this->em->createQueryBuilder()
                ->from(Team::class, 't')
                ->select('t')
                ->andWhere(sprintf('t.%s = :team',
                    $this->eventLogService->externalIdFieldForEntity(Team::class) ?? 'teamid'))
                ->setParameter('team', $request->request->get('team_id'))
                ->getQuery()
                ->getOneOrNullResult();

            if ($team === null) {
                throw new BadRequestHttpException(sprintf("Team %s not found", $request->request->get('team_id')));
            }
            $user->setTeam($team);
        }

        $roles = (array)$request->request->get('roles');
        foreach ($roles as $djRole) {
            if ($djRole === '') {
                continue;
            }
            $role = $this->em->getRepository(Role::class)->findOneBy(['dj_role' => $djRole]);
            if ($role === null) {
                throw new BadRequestHttpException(sprintf("Role %s not found", $djRole));
            }
            $user->addUserRole($role);
        }

        $this->em->persist($user);
        $this->em->flush();
        $this->dj->auditlog('user', $user->getUserid(), 'added');

        return $this->renderCreateData($request, $user, 'user', $user->getUserid());
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
