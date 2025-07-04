<?php declare(strict_types=1);

namespace App\Controller\API;

use App\DataTransferObject\AddUser;
use App\DataTransferObject\UpdateUser;
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
use OpenApi\Attributes as OA;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * @extends AbstractRestController<User, User>
 */
#[Rest\Route('/users', defaults: ['_format' => 'json'])]
#[OA\Tag(name: 'Users')]
#[OA\Response(ref: '#/components/responses/InvalidResponse', response: 400)]
#[OA\Response(ref: '#/components/responses/Unauthenticated', response: 401)]
#[OA\Response(ref: '#/components/responses/Unauthorized', response: 403)]
#[OA\Response(ref: '#/components/responses/NotFound', response: 404)]
class UserController extends AbstractRestController
{
    public function __construct(
        EntityManagerInterface $entityManager,
        DOMJudgeService $dj,
        ConfigurationService $config,
        EventLogService $eventLogService,
        protected readonly ImportExportService $importExportService
    ) {
        parent::__construct($entityManager, $dj, $config, $eventLogService);
    }

    /**
     * Add one or more groups.
     */
    #[IsGranted('ROLE_ADMIN')]
    #[Rest\Post('/groups')]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                properties: [
                    new OA\Property(
                        property: 'tsv',
                        description: 'The groups.tsv files to import.',
                        type: 'string',
                        format: 'binary'
                    ),
                    new OA\Property(
                        property: 'json',
                        description: 'The groups.json files to import.',
                        type: 'string',
                        format: 'binary'
                    ),
                ]
            )
        )
    )]
    #[OA\Response(response: 200, description: 'Returns a (currently meaningless) status message.')]
    public function addGroupsAction(Request $request): string
    {
        /** @var UploadedFile|null $tsvFile */
        $tsvFile = $request->files->get('tsv');
        /** @var UploadedFile|null $jsonFile */
        $jsonFile = $request->files->get('json');
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
     */
    #[IsGranted('ROLE_ADMIN')]
    #[Rest\Post('/organizations')]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                required: ['json'],
                properties: [
                    new OA\Property(
                        property: 'json',
                        description: 'The organizations.json files to import.',
                        type: 'string',
                        format: 'binary'),
                ]
            )
        )
    )]
    #[OA\Response(response: 200, description: 'Returns a (currently meaningless) status message.')]
    public function addOrganizationsAction(Request $request): string
    {
        $message = null;
        /** @var UploadedFile|null $jsonFile */
        $jsonFile = $request->files->get('json');
        if ($jsonFile &&
            ($result = $this->importExportService->importJson('organizations', $jsonFile, $message)) &&
            $result >= 0
        ) {
            // TODO: better return all organizations here
            return "$result new organization(s) successfully added.";
        } else {
            throw new BadRequestHttpException("Error while adding organizations: $message");
        }
    }

    /**
     * Add one or more teams.
     */
    #[IsGranted('ROLE_ADMIN')]
    #[Rest\Post('/teams')]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                properties: [
                    new OA\Property(
                        property: 'tsv',
                        description: 'The teams.tsv files to import.',
                        type: 'string',
                        format: 'binary'
                    ),
                    new OA\Property(
                        property: 'json',
                        description: 'The teams.json files to import.',
                        type: 'string',
                        format: 'binary'
                    ),
                ]
            )
        )
    )]
    #[OA\Response(response: 200, description: 'Returns a (currently meaningless) status message.')]
    public function addTeamsAction(Request $request): string
    {
        /** @var UploadedFile|null $tsvFile */
        $tsvFile = $request->files->get('tsv');
        /** @var UploadedFile|null $jsonFile */
        $jsonFile = $request->files->get('json');
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
     * @throws BadRequestHttpException
     */
    #[IsGranted('ROLE_ADMIN')]
    #[Rest\Post('/accounts')]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                properties: [
                    new OA\Property(
                        property: 'tsv',
                        description: 'The accounts.tsv files to import.',
                        type: 'string',
                        format: 'binary'
                    ),
                    new OA\Property(
                        property: 'json',
                        description: 'The accounts.json files to import.',
                        type: 'string',
                        format: 'binary'
                    ),
                    new OA\Property(
                        property: 'yaml',
                        description: 'The accounts.yaml files to import.',
                        type: 'string',
                        format: 'binary'
                    ),
                ]
            )
        )
    )]
    #[OA\Response(ref: '#/components/responses/PostAccountResponse', response: 200)]
    public function addAccountsAction(Request $request): string
    {
        /** @var UploadedFile|null $tsvFile */
        $tsvFile = $request->files->get('tsv');
        /** @var UploadedFile|null $jsonFile */
        $jsonFile = $request->files->get('json');
        /** @var UploadedFile|null $yamlFile */
        $yamlFile      = $request->files->get('yaml');
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
     * @throws NonUniqueResultException
     */
    #[IsGranted(new Expression("is_granted('ROLE_ADMIN') or is_granted('ROLE_API_READER')"))]
    #[Rest\Get('')]
    #[OA\Response(
        response: 200,
        description: 'Returns all the users for this contest',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: User::class))
        )
    )]
    #[OA\Parameter(ref: '#/components/parameters/idlist')]
    #[OA\Parameter(
        name: 'team_id',
        description: 'Only show users for the given team',
        in: 'query',
        schema: new OA\Schema(type: 'string'))
    ]
    public function listAction(Request $request): Response
    {
        return parent::performListAction($request);
    }

    /**
     * Get the given user.
     * @throws NonUniqueResultException
     */
    #[IsGranted(new Expression("is_granted('ROLE_ADMIN') or is_granted('ROLE_API_READER')"))]
    #[Rest\Get('/{id}')]
    #[OA\Response(
        response: 200,
        description: 'Returns the given user',
        content: new Model(type: User::class)
    )]
    #[OA\Parameter(ref: '#/components/parameters/id')]
    public function singleAction(Request $request, string $id): Response
    {
        return parent::performSingleAction($request, $id);
    }

    /**
     * Add a new user.
     */
    #[IsGranted('ROLE_API_WRITER')]
    #[Rest\Post]
    #[OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(ref: new Model(type: AddUser::class))
            ),
        ]
    )]
    #[OA\Response(
        response: 201,
        description: 'Returns the added user',
        content: new Model(type: User::class)
    )]
    public function addAction(
        #[MapRequestPayload(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        AddUser $addUser,
        Request $request
    ): Response {
        return $this->addOrUpdateUser($addUser, $request);
    }

    /**
     * Update an existing User or create one with the given ID
     */
    #[IsGranted('ROLE_API_WRITER')]
    #[Rest\Put('/{id}')]
    #[OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(ref: new Model(type: UpdateUser::class))
            ),
        ]
    )]
    #[OA\Response(
        response: 201,
        description: 'Returns the added user',
        content: new Model(type: User::class)
    )]
    public function updateAction(
        #[MapRequestPayload(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        UpdateUser $updateUser,
        Request $request
    ): Response {
        return $this->addOrUpdateUser($updateUser, $request);
    }

    protected function addOrUpdateUser(AddUser $addUser, Request $request): Response
    {
        if ($addUser instanceof UpdateUser && !$addUser->id) {
            throw new BadRequestHttpException('`id` field is required');
        }

        if ($this->em->getRepository(User::class)->findOneBy(['username' => $addUser->username])) {
            throw new BadRequestHttpException(sprintf("User %s already exists", $addUser->username));
        }

        $user = new User();
        if ($addUser instanceof UpdateUser) {
            $existingUser = $this->em->getRepository(User::class)->findOneBy(['externalid' => $addUser->id]);
            if ($existingUser) {
                $user = $existingUser;
            }
        }
        $user
            ->setUsername($addUser->username)
            ->setName($addUser->name)
            ->setEmail($addUser->email)
            ->setIpAddress($addUser->ip)
            ->setPlainPassword($addUser->password)
            ->setEnabled($addUser->enabled ?? true);

        if ($addUser instanceof UpdateUser) {
            $user->setExternalid($addUser->id);
        }

        if ($addUser->teamId) {
            /** @var Team|null $team */
            $team = $this->em->createQueryBuilder()
                ->from(Team::class, 't')
                ->select('t')
                ->andWhere('t.externalid = :team')
                ->setParameter('team', $addUser->teamId)
                ->getQuery()
                ->getOneOrNullResult();

            if ($team === null) {
                throw new BadRequestHttpException(sprintf("Team %s not found", $addUser->teamId));
            }
            $user->setTeam($team);
        }

        $roles = $addUser->roles;
        // For the file import we change a CDS user to the roles needed for ICPC CDS.
        if ($user->getUsername() === 'cds') {
            $roles = ['cds'];
        }
        if (in_array('cds', $roles)) {
            $roles = ['api_source_reader', 'api_writer', 'api_reader', ...array_diff($roles, ['cds'])];
        }
        foreach ($roles as $djRole) {
            if ($djRole === '') {
                continue;
            }
            if ($djRole === 'judge') {
                $djRole = 'jury';
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
        return 'u.externalid';
    }
}
