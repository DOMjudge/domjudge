<?php declare(strict_types=1);

namespace DOMJudgeBundle\Controller\Jury;

use Doctrine\ORM\EntityManagerInterface;
use DOMJudgeBundle\Controller\BaseController;
use DOMJudgeBundle\Entity\Role;
use DOMJudgeBundle\Entity\Team;
use DOMJudgeBundle\Entity\User;
use DOMJudgeBundle\Form\Type\GeneratePasswordsType;
use DOMJudgeBundle\Form\Type\UserType;
use DOMJudgeBundle\Service\DOMJudgeService;
use DOMJudgeBundle\Service\EventLogService;
use DOMJudgeBundle\Utils\Utils;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * @Route("/jury/users")
 * @Security("has_role('ROLE_JURY')")
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
    private $dj;

    /**
     * @var EventLogService
     */
    protected $eventLogService;

    /**
     * @var TokenStorageInterface
     */
    protected $tokenStorage;

    public function __construct(
        EntityManagerInterface $em,
        DOMJudgeService $dj,
        EventLogService $eventLogService,
        TokenStorageInterface $tokenStorage
    ) {
        $this->em              = $em;
        $this->dj              = $dj;
        $this->eventLogService = $eventLogService;
        $this->tokenStorage    = $tokenStorage;
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
            ->from('DOMJudgeBundle:User', 'u')
            ->leftJoin('u.roles', 'r')
            ->leftJoin('u.team', 't')
            ->orderBy('u.username', 'ASC')
            ->getQuery()->getResult();

        $table_fields = [
            'username' => ['title' => 'username', 'sort' => true, 'default_sort' => true],
            'name' => ['title' => 'name', 'sort' => true],
            'email' => ['title' => 'email', 'sort' => true],
            'roles' => ['title' => 'roles', 'sort' => true],
            'team' => ['title' => 'team', 'sort' => true],
            'status' => ['title' => '', 'sort' => true],
        ];

        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $users_table      = [];
        $timeFormat  = (string)$this->dj->dbconfig_get('time_format', '%H:%M');
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
                    'title' => $u->getTeam()->getName(),
                ];
            }

            $userdata['roles'] = [
                'value' => implode(', ', array_map(function (Role $role) {
                    return $role->getDjRole();
                }, $u->getRoles()))
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

        return $this->render('@DOMJudge/jury/users.html.twig', [
            'users' => $users_table,
            'table_fields' => $table_fields,
            'num_actions' => $this->isGranted('ROLE_ADMIN') ? 2 : 0,
        ]);
    }

    /**
     * @Route("/{userId}", name="jury_user", requirements={"userId": "\d+"})
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

        return $this->render('@DOMJudge/jury/user.html.twig', ['user' => $user]);
    }

    /**
     * @Route("/{userId}/edit", name="jury_user_edit", requirements={"userId": "\d+"})
     * @Security("has_role('ROLE_ADMIN')")
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

            return $this->redirect($this->generateUrl('jury_user',
                                                      ['userId' => $user->getUserid()]));
        }

        return $this->render('@DOMJudge/jury/user_edit.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{userId}/delete", name="jury_user_delete", requirements={"userId": "\d+"})
     * @Security("has_role('ROLE_ADMIN')")
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

        return $this->deleteEntity($request, $this->em, $this->dj, $user, $user->getName(),
                                   $this->generateUrl('jury_users'));
    }

    /**
     * @Route("/add", name="jury_user_add")
     * @Security("has_role('ROLE_ADMIN')")
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
            return $this->redirect($this->generateUrl('jury_user',
                                                      ['userId' => $user->getUserid()]));
        }

        return $this->render('@DOMJudge/jury/user_add.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/generate-passwords", name="jury_generate_passwords")
     * @Security("has_role('ROLE_ADMIN')")
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function generatePasswordsAction(Request $request)
    {
        $form = $this->createForm(GeneratePasswordsType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $groups = $form->get('group')->getData();

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
                    $newpass = Utils::generatePassword();
                    $user->setPlainPassword($newpass);
                    $this->dj->auditlog('user', $user->getUserid(), 'set password');
                    $changes[] = [
                            'type' => $role,
                            'id' => $user->getUserid(),
                            'fullname' => $user->getName(),
                            'username' => $user->getUsername(),
                            'password' => $newpass,
                    ];
                }
            }
            $this->em->flush();
            $response = $this->render('@DOMJudge/jury/tsv/userdata.tsv.twig', [
                'data' => $changes,
            ]);
            $disposition = $response->headers->makeDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                'userdata.tsv');
            $response->headers->set('Content-Disposition', $disposition);
            $response->headers->set('Content-Type', 'text/plain');
            return $response;
        }

        return $this->render('@DOMJudge/jury/user_generate_passwords.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
