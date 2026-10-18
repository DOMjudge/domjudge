<?php declare(strict_types=1);

namespace App\Controller\API;

use App\DataTransferObject\AddUser;
use App\DataTransferObject\ChangePasswordRequest;
use App\DataTransferObject\PatchUser;
use App\DataTransferObject\UpdateUser;
use App\Entity\Role;
use App\Entity\Team;
use App\Entity\User;
use App\Service\AuthorizedUserService;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Service\ImportExportService;
use App\Service\PasswordChangeService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\QueryBuilder;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @extends AbstractRestController<User, User>
 */
#[Rest\Route(path: '/users', defaults: ['_format' => 'json'])]
#[OA\Tag(name: 'Users')]
#[OA\Response(ref: '#/components/responses/InvalidResponse', response: 400)]
#[OA\Response(ref: '#/components/responses/Unauthenticated', response: 401)]
#[OA\Response(ref: '#/components/responses/Unauthorized', response: 403)]
#[OA\Response(ref: '#/components/responses/NotFound', response: 404)]
class UserController extends AbstractRestController
{
    public function __construct(
        AuthorizedUserService $authService,
        EntityManagerInterface $em,
        DOMJudgeService $dj,
        ConfigurationService $config,
        EventLogService $eventLogService,
        protected readonly ImportExportService $importExportService,
        protected readonly ValidatorInterface $validator,
        protected readonly PasswordChangeService $passwordChangeService,
    ) {
        parent::__construct($authService, $em, $dj, $config, $eventLogService);
    }

    /**
     * Add one or more groups.
     */
    #[IsGranted('ROLE_ADMIN')]
    #[Rest\Post(path: '/groups')]
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
    #[Rest\Post(path: '/organizations')]
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
    #[Rest\Post(path: '/teams')]
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
    #[Rest\Post(path: '/accounts')]
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
    #[Rest\Get(path: '')]
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
    #[Rest\Get(path: '/{id}')]
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
                mediaType: 'application/x-www-form-urlencoded',
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
        return $this->saveUser($request, new User(), $addUser, created: true);
    }

    /**
     * Replace an existing user or create one with the given ID.
     *
     * Fields that are not provided are cleared, except for the password of an existing user, which is kept
     * unless a new one is provided. Use PATCH to remove a password.
     */
    #[IsGranted('ROLE_API_WRITER')]
    #[Rest\Put(path: '/{id}')]
    #[OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(ref: new Model(type: UpdateUser::class))
            ),
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'Returns the replaced user',
        content: new Model(type: User::class)
    )]
    #[OA\Response(
        response: 201,
        description: 'Returns the added user',
        content: new Model(type: User::class)
    )]
    public function updateAction(
        #[MapRequestPayload(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        UpdateUser $updateUser,
        Request $request,
        string $id,
    ): Response {
        $this->checkPayloadId($id, $updateUser->id);

        $user = $this->em->getRepository(User::class)->findOneBy(['externalid' => $id]);
        if ($user === null) {
            return $this->saveUser($request, (new User())->setExternalid($id), $updateUser, created: true);
        }

        return $this->saveUser($request, $user, $updateUser, created: false, setPassword: $updateUser->password !== null);
    }

    /**
     * Change password of a user.
     */
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Rest\Post(path: '/{id}/change-password')]
    #[OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(ref: new Model(type: ChangePasswordRequest::class))
            ),
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(ref: new Model(type: ChangePasswordRequest::class))
            ),
        ]
    )]
    #[OA\Response(
        response: 204,
        description: 'Password changed successfully'
    )]
    #[OA\Response(
        response: 400,
        description: 'Current password is incorrect or new password does not meet requirements'
    )]
    #[OA\Response(
        response: 403,
        description: 'User is not permitted to change password or attempting to change another user\'s password'
    )]
    #[OA\Response(
        response: 404,
        description: 'User not found'
    )]
    #[OA\Parameter(ref: '#/components/parameters/id')]
    public function changePasswordAction(
        #[MapRequestPayload(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        ChangePasswordRequest $requestPayload,
        string $id
    ): Response {
        /** @var User|null $targetUser */
        $targetUser = $this->em->getRepository(User::class)->findOneBy(['externalid' => $id]);
        if ($targetUser === null) {
            throw new NotFoundHttpException(sprintf('User with ID %s not found', $id));
        }

        $currentUser = $this->authService->getUser();
        if ($currentUser === null || $currentUser->getUserid() !== $targetUser->getUserid()) {
            throw new AccessDeniedHttpException('You can only change your own password.');
        }

        $this->passwordChangeService->changePassword(
            $targetUser,
            $requestPayload->currentPassword,
            $requestPayload->newPassword
        );

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Update only the provided fields of an existing user.
     */
    #[IsGranted('ROLE_API_WRITER')]
    #[Rest\Patch(path: '/{id}')]
    #[OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(ref: new Model(type: PatchUser::class))
            ),
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'Returns the updated user',
        content: new Model(type: User::class)
    )]
    public function patchAction(
        #[MapRequestPayload(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        PatchUser $patchUser,
        Request $request,
        string $id,
    ): Response {
        $this->checkPayloadId($id, $patchUser->id ?? $id);

        $user = $this->em->getRepository(User::class)->findOneBy(['externalid' => $id]);
        if ($user === null) {
            throw new NotFoundHttpException(sprintf("User with ID '%s' not found", $id));
        }

        $data = $this->mergePatch($request, $patchUser, [
            'username' => $user->getUsername(),
            'name' => $user->getName(),
            'ip' => $user->getIpAddress(),
            'enabled' => $user->getEnabled(),
            'team_id' => $user->getTeam()?->getExternalid(),
            'roles' => $user->getRoleList(),
        ]);
        $merged = new UpdateUser(
            id: $id,
            username: (string)$data['username'],
            name: (string)$data['name'],
            ip: $data['ip'],
            password: $patchUser->password,
            enabled: $data['enabled'],
            teamId: $data['team_id'],
            roles: $data['roles'] ?? [],
        );

        return $this->saveUser(
            $request,
            $user,
            $merged,
            created: false,
            setPassword: array_key_exists('password', $request->getPayload()->all()),
        );
    }

    protected function saveUser(
        Request $request,
        User $user,
        AddUser $data,
        bool $created,
        bool $setPassword = true,
    ): Response {
        if (!$data->roles) {
            throw new BadRequestHttpException('`roles` field is required');
        }

        $existingUsername = $this->em->getRepository(User::class)->findOneBy(['username' => $data->username]);
        if ($existingUsername && $existingUsername !== $user) {
            throw new BadRequestHttpException(sprintf("User %s already exists", $data->username));
        }

        $user
            ->setUsername($data->username)
            ->setName($data->name)
            ->setIpAddress($data->ip)
            ->setEnabled($data->enabled ?? true);

        if ($setPassword) {
            $user->setPlainPassword($data->password);
        }

        $team = null;
        if ($data->teamId) {
            /** @var Team|null $team */
            $team = $this->em->createQueryBuilder()
                ->from(Team::class, 't')
                ->select('t')
                ->andWhere('t.externalid = :team')
                ->setParameter('team', $data->teamId)
                ->getQuery()
                ->getOneOrNullResult();

            if ($team === null) {
                throw new BadRequestHttpException(sprintf("Team %s not found", $data->teamId));
            }
        }
        $user->setTeam($team);

        // Clear existing roles to avoid duplicate join table entries.
        foreach ($user->getUserRoles() as $existingRole) {
            $user->removeUserRole($existingRole);
        }

        $roles = $data->roles;
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

        $errors = $this->validator->validate($user);
        if ($errors->count()) {
            $messages = [];
            foreach ($errors as $error) {
                $messages[] = sprintf('%s: %s', $error->getPropertyPath(), $error->getMessage());
            }
            throw new BadRequestHttpException(implode("\n", $messages));
        }

        $this->em->persist($user);
        $this->em->flush();
        $this->dj->auditlog('user', $user->getExternalid(), $created ? 'added' : 'updated');

        return $this->renderSavedData($request, $user, 'user', $user->getExternalid(), $created);
    }

    protected function getQueryBuilder(Request $request): QueryBuilder
    {
        $queryBuilder = $this->em->createQueryBuilder()
                                 ->from(User::class, 'u')
                                 ->select('u');

        if ($request->query->has('team_id')) {
            $queryBuilder
                ->leftJoin('u.team', 't')
                ->andWhere('t.externalid = :team_id')
                ->setParameter('team_id', $request->query->get('team_id'));
        } elseif ($request->query->has('team')) {
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
