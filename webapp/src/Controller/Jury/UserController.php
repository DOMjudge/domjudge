<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Controller\BaseController;
use App\Entity\Role;
use App\Entity\Team;
use App\Entity\User;
use App\Form\Type\GeneratePasswordsType;
use App\Form\Type\UserType;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * @Route("/jury/users")
 * @IsGranted("ROLE_JURY")
 */
class UserController extends BaseController
{
    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * @var DOMJudgeService
     */
    protected $dj;

    /**
     * @var ConfigurationService
     */
    protected $config;

    /**
     * @var KernelInterface
     */
    protected $kernel;

    /**
     * @var EventLogService
     */
    protected $eventLogService;

    /**
     * @var TokenStorageInterface
     */
    protected $tokenStorage;

    /**
     * UserController constructor.
     *
     * @param EntityManagerInterface $em
     * @param DOMJudgeService        $dj
     * @param ConfigurationService   $config
     * @param KernelInterface        $kernel
     * @param EventLogService        $eventLogService
     * @param TokenStorageInterface  $tokenStorage
     */
    public function __construct(
        EntityManagerInterface $em,
        DOMJudgeService $dj,
        ConfigurationService $config,
        KernelInterface $kernel,
        EventLogService $eventLogService,
        TokenStorageInterface $tokenStorage
    ) {
        $this->em              = $em;
        $this->dj              = $dj;
        $this->config          = $config;
        $this->eventLogService = $eventLogService;
        $this->tokenStorage    = $tokenStorage;
        $this->kernel          = $kernel;
    }

    /**
     * @Route("", name="jury_users")
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    public function indexAction()
    {
        /** @var User[] $users */
        $users = $this->em->createQueryBuilder()
            ->select('u', 'r', 't')
            ->from(User::class, 'u')
            ->leftJoin('u.user_roles', 'r')
            ->leftJoin('u.team', 't')
            ->orderBy('u.username', 'ASC')
            ->getQuery()->getResult();

        $table_fields = [
            'username' => ['title' => 'username', 'sort' => true, 'default_sort' => true],
            'name' => ['title' => 'name', 'sort' => true],
            'email' => ['title' => 'email', 'sort' => true],
            'user_roles' => ['title' => 'roles', 'sort' => true],
            'team' => ['title' => 'team', 'sort' => true],
            'status' => ['title' => '', 'sort' => true],
        ];

        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $users_table      = [];
        $timeFormat  = (string)$this->config->get('time_format');
        foreach ($users as $u) {
            $userdata    = [];
            $useractions = [];
            // Get whatever fields we can from the user object itself
            foreach ($table_fields as $k => $v) {
                if ($propertyAccessor->isReadable($u, $k)) {
                    $userdata[$k] = ['value' => $propertyAccessor->getValue($u, $k)];
                }
            }

            $status = 'noconn';
            $statustitle = "no connections made";
            if ($u->getLastLogin()) {
                $status = "ok";
                $statustitle = sprintf('logged in: %s', Utils::printtime($u->getLastLogin(), $timeFormat));
            }

            if ($u->getTeam()) {
                $userdata['team'] = [
                    'value' => $u->getTeamid(),
                    'sortvalue' => $u->getTeamid(),
                    'link' => $this->generateUrl('jury_team', [
                        'teamId' => $u->getTeamid(),
                    ]),
                    'title' => $u->getTeam()->getEffectiveName(),
                ];
            }

            $userdata['user_roles'] = [
                'value' => implode(', ', array_map(function (Role $role) {
                    return $role->getDjRole();
                }, $u->getUserRoles()))
            ];

            // Create action links
            if ($this->isGranted('ROLE_ADMIN')) {
                $useractions[] = [
                    'icon' => 'edit',
                    'title' => 'edit this user',
                    'link' => $this->generateUrl('jury_user_edit', [
                        'userId' => $u->getUserid(),
                    ])
                ];
                $useractions[] = [
                    'icon' => 'trash-alt',
                    'title' => 'delete this user',
                    'link' => $this->generateUrl('jury_user_delete', [
                        'userId' => $u->getUserid(),
                    ]),
                    'ajaxModal' => true,
                ];
            }

            // merge in the rest of the data
            $userdata = array_merge($userdata, [
                'status' => [
                    'value' => $status,
                    'sortvalue' => $status,
                    'title' => $statustitle,
                ],
            ]);
            // Save this to our list of rows
            $users_table[] = [
                'data' => $userdata,
                'actions' => $useractions,
                'link' => $this->generateUrl('jury_user', ['userId' => $u->getUserid()]),
                'cssclass' => $u->getEnabled() ? '' : 'disabled',
            ];
        }

        return $this->render('jury/users.html.twig', [
            'users' => $users_table,
            'table_fields' => $table_fields,
            'num_actions' => $this->isGranted('ROLE_ADMIN') ? 2 : 0,
        ]);
    }

    /**
     * @Route("/{userId<\d+>}", name="jury_user")
     * @param Request $request
     * @param int     $userId
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function viewAction(Request $request, int $userId)
    {
        /** @var User $user */
        $user = $this->em->getRepository(User::class)->find($userId);
        if (!$user) {
            throw new NotFoundHttpException(sprintf('User with ID %s not found', $userId));
        }

        return $this->render('jury/user.html.twig', ['user' => $user]);
    }

    /**
     * @Route("/{userId<\d+>}/edit", name="jury_user_edit")
     * @IsGranted("ROLE_ADMIN")
     * @param Request $request
     * @param int     $userId
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    public function editAction(Request $request, int $userId)
    {
        /** @var User $user */
        $user = $this->em->getRepository(User::class)->find($userId);
        if (!$user) {
            throw new NotFoundHttpException(sprintf('User with ID %s not found', $userId));
        }

        $form = $this->createForm(UserType::class, $user);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->saveEntity($this->em, $this->eventLogService, $this->dj, $user,
                              $user->getUserid(),
                              false);

            // If we save the currently logged in used, update the login token
            if ($user->getUserid() === $this->dj->getUser()->getUserid()) {
                $token = new UsernamePasswordToken(
                    $user,
                    null,
                    'main',
                    $user->getRoles()
                );

                $this->tokenStorage->setToken($token);
            }

            return $this->redirect($this->generateUrl(
                'jury_user',
                ['userId' => $user->getUserid()]
            ));
        }

        return $this->render('jury/user_edit.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{userId<\d+>}/delete", name="jury_user_delete")
     * @IsGranted("ROLE_ADMIN")
     * @param Request $request
     * @param int     $userId
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    public function deleteAction(Request $request, int $userId)
    {
        /** @var User $user */
        $user = $this->em->getRepository(User::class)->find($userId);
        if (!$user) {
            throw new NotFoundHttpException(sprintf('User with ID %s not found', $userId));
        }

        return $this->deleteEntity($request, $this->em, $this->dj, $this->eventLogService, $this->kernel,
                                   $user, $user->getName(), $this->generateUrl('jury_users'));
    }

    /**
     * @Route("/add", name="jury_user_add")
     * @IsGranted("ROLE_ADMIN")
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    public function addAction(Request $request)
    {
        $user = new User();
        if ($request->query->has('team')) {
            $user->setTeam($this->em->getRepository(Team::class)->find($request->query->get('team')));
        }

        $form = $this->createForm(UserType::class, $user);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($user);
            $this->saveEntity($this->em, $this->eventLogService, $this->dj, $user,
                              $user->getUserid(),
                              true);
            return $this->redirect($this->generateUrl(
                'jury_user',
                ['userId' => $user->getUserid()]
            ));
        }

        return $this->render('jury/user_add.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/generate-passwords", name="jury_generate_passwords")
     * @IsGranted("ROLE_ADMIN")
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function generatePasswordsAction(Request $request)
    {
        $form = $this->createForm(GeneratePasswordsType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $groups = $form->get('group')->getData();

            /** @var User[] $users */
            $users = $this->em->getRepository(User::class)->findAll();

            $changes = [];
            foreach ($users as $user) {
                 $doit = false;
                 $roles = $user->getRoleList();

                 $isjury = in_array('jury', $roles);
                 $isadmin = in_array('admin', $roles);

                 if ( in_array('team', $groups) || in_array('team_nopass', $groups) ) {
                     if ( $user->getTeamid() && ! $isjury && ! $isadmin ) {
                         if ( in_array('team', $groups) || empty($user->getPassword()) ) {
                             $doit = true;
                             $role = 'team';
                         }
                     }
                 }

                 if ( (in_array('judge', $groups) && $isjury) ||
                    (in_array('admin', $groups) && $isadmin))
                 {
                     $doit = true;
                     $role = in_array('admin', $groups) ? 'admin' : 'judge';
                 }

                if ( $doit ) {
                    $newpass = Utils::generatePassword(false);
                    $user->setPlainPassword($newpass);
                    $this->dj->auditlog('user', $user->getUserid(), 'set password');
                    $changes[] = [
                            'type' => $role,
                            'fullname' => $user->getName(),
                            'username' => $user->getUsername(),
                            'password' => $newpass,
                    ];
                }
            }
            $this->em->flush();
            $response = $this->render('jury/tsv/accounts.tsv.twig', [
                'data' => $changes,
            ]);
            $disposition = $response->headers->makeDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                'accounts.tsv');
            $response->headers->set('Content-Disposition', $disposition);
            $response->headers->set('Content-Type', 'text/plain');
            return $response;
        }

        return $this->render('jury/user_generate_passwords.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
