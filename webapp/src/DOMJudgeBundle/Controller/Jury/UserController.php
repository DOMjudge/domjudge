<?php declare(strict_types=1);

namespace DOMJudgeBundle\Controller\Jury;

use Doctrine\ORM\EntityManagerInterface;
use DOMJudgeBundle\Entity\Role;
use DOMJudgeBundle\Entity\User;
use DOMJudgeBundle\Service\DOMJudgeService;
use DOMJudgeBundle\Utils\Utils;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * @Route("/jury")
 * @Security("has_role('ROLE_JURY')")
 */
class UserController extends Controller
{
    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var DOMJudgeService
     */
    private $DOMJudgeService;

    public function __construct(
        EntityManagerInterface $entityManager,
        DOMJudgeService $DOMJudgeService
    ) {
        $this->entityManager   = $entityManager;
        $this->DOMJudgeService = $DOMJudgeService;
    }

    /**
     * @Route("/users/", name="jury_users")
     */
    public function indexAction(Request $request, Packages $assetPackage)
    {
        /** @var User[] $users */
        $users = $this->entityManager->createQueryBuilder()
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
            'bubble' => ['title' => '', 'sort' => false],
        ];

        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $users_table      = [];
        foreach ($users as $u) {
            $userdata    = [];
            $useractions = [];
            // Get whatever fields we can from the user object itself
            foreach ($table_fields as $k => $v) {
                if ($propertyAccessor->isReadable($u, $k)) {
                    $userdata[$k] = ['value' => $propertyAccessor->getValue($u, $k)];
                }
            }

            $statusclass = 'team-nocon';
            $statustitle = "no connections made";
            if ($u->getLastLogin()) {
                $statusclass = "team-ok";
                $timeFormat  = (string)$this->DOMJudgeService->dbconfig_get('time_format', '%H:%M');
                $statustitle = sprintf('logged in: %s', Utils::printtime($u->getLastLogin(), $timeFormat));
            }

            if ($u->getTeam()) {
                $userdata['team'] = [
                    'value' => 't' . $u->getTeamid(),
                    'link' => $this->generateUrl('legacy.jury_team', [
                        'id' => $u->getTeamid(),
                    ]),
                    'linktitle' => $u->getTeam()->getName(),
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
                    'link' => $this->generateUrl('legacy.jury_user', [
                        'cmd' => 'edit',
                        'id' => $u->getUserid(),
                        'referrer' => 'users'
                    ])
                ];
                $useractions[] = [
                    'icon' => 'trash-alt',
                    'title' => 'delete this user',
                    'link' => $this->generateUrl('legacy.jury_delete', [
                        'table' => 'user',
                        'userid' => $u->getUserid(),
                        'referrer' => '',
                        'desc' => $u->getName(),
                    ])
                ];
            }

            // merge in the rest of the data
            $userdata = array_merge($userdata, [
                'bubble' => [
                    'value' => "\u{25CF}",
                    'cssclass' => $statusclass,
                    'linktitle' => $statustitle,
                ],
            ]);
            // Save this to our list of rows
            $users_table[] = [
                'data' => $userdata,
                'actions' => $useractions,
                'link' => $this->generateUrl('legacy.jury_user', ['id' => $u->getUserid()]),
            ];
        }

        return $this->render('@DOMJudge/jury/users.html.twig', [
            'users' => $users_table,
            'table_fields' => $table_fields,
            'num_actions' => $this->isGranted('ROLE_ADMIN') ? 2 : 0,
        ]);
    }
}
