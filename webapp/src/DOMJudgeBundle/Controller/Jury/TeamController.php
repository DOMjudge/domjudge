<?php declare(strict_types=1);

namespace DOMJudgeBundle\Controller\Jury;

use Doctrine\ORM\EntityManagerInterface;
use DOMJudgeBundle\Controller\BaseController;
use DOMJudgeBundle\Entity\Role;
use DOMJudgeBundle\Entity\Team;
use DOMJudgeBundle\Entity\User;
use DOMJudgeBundle\Form\Type\TeamType;
use DOMJudgeBundle\Service\DOMJudgeService;
use DOMJudgeBundle\Service\EventLogService;
use DOMJudgeBundle\Service\ScoreboardService;
use DOMJudgeBundle\Service\SubmissionService;
use DOMJudgeBundle\Utils\Utils;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\Annotation\Route;


/**
 * @Route("/jury/teams")
 * @Security("has_role('ROLE_JURY')")
 */
class TeamController extends BaseController
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

    public function __construct(
        EntityManagerInterface $em,
        DOMJudgeService $dj,
        EventLogService $eventLogService
    ) {
        $this->em              = $em;
        $this->dj              = $dj;
        $this->eventLogService = $eventLogService;
    }

    /**
     * @Route("", name="jury_teams")
     */
    public function indexAction(Request $request, Packages $assetPackage)
    {
        /** @var Team[] $teams */
        $teams = $this->em->createQueryBuilder()
            ->select('t', 'c', 'a', 'cat')
            ->from('DOMJudgeBundle:Team', 't')
            ->leftJoin('t.contests', 'c')
            ->leftJoin('t.affiliation', 'a')
            ->join('t.category', 'cat')
            ->orderBy('cat.sortorder', 'ASC')
            ->addOrderBy('t.name', 'ASC')
            ->getQuery()->getResult();

        $contests             = $this->dj->getCurrentContests();
        $num_public_contests  = $this->em->createQueryBuilder()
            ->select('count(c.cid) as num_contests')
            ->from('DOMJudgeBundle:Contest', 'c')
            ->andWhere('c.public = 1')
            ->getQuery()->getSingleResult()['num_contests'];
        $teams_that_submitted = $this->em->createQueryBuilder()
            ->select('t.teamid as teamid, count(t.teamid) as num_submitted')
            ->from('DOMJudgeBundle:Team', 't')
            ->join('t.submissions', 's')
            ->groupBy('s.team')
            ->andWhere('s.contest in (:contests)')
            ->setParameter('contests', $contests)
            ->getQuery()->getResult();
        $teams_that_submitted = array_column($teams_that_submitted, 'num_submitted', 'teamid');

        $teams_that_solved = $this->em->createQueryBuilder()
            ->select('t.teamid as teamid, count(t.teamid) as num_correct')
            ->from('DOMJudgeBundle:Team', 't')
            ->join('t.submissions', 's')
            ->join('s.judgings', 'j')
            ->groupBy('s.team')
            ->andWhere('s.contest in (:contests)')
            ->andWhere('j.valid = 1')
            ->andWhere('j.result = :result')
            ->setParameter('contests', $contests)
            ->setParameter('result', "correct")
            ->getQuery()->getResult();
        // turn that into an array with key of teamid, value as number correct
        $teams_that_solved = array_column($teams_that_solved, 'num_correct', 'teamid');

        $table_fields = [
            'teamid' => ['title' => 'ID', 'sort' => true,],
            'externalid' => ['title' => 'external ID', 'sort' => true,],
            'name' => ['title' => 'teamname', 'sort' => true, 'default_sort' => true],
            'category' => ['title' => 'category', 'sort' => true,],
            'affiliation' => ['title' => 'affiliation', 'sort' => true,],
            'num_contests' => ['title' => '# contests', 'sort' => true,],
            'ip_address' => ['title' => 'ip', 'sort' => true,],
            'room' => ['title' => 'room', 'sort' => true,],
            'status' => ['title' => '', 'sort' => false,],
            'stats' => ['title' => 'stats', 'sort' => true,],
        ];

        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $teams_table      = [];
        foreach ($teams as $t) {
            $teamdata    = [];
            $teamactions = [];
            // Get whatever fields we can from the team object itself
            foreach ($table_fields as $k => $v) {
                if ($propertyAccessor->isReadable($t, $k)) {
                    $teamdata[$k] = ['value' => $propertyAccessor->getValue($t, $k)];
                }
            }

            // Add some elements for the solved status
            $num_solved    = 0;
            $num_submitted = 0;
            $status = 'noconn';
            $statustitle = "no connections made";
            if (!$t->getUsers()->isEmpty() && $t->getUsers()->first()->getFirstLogin()) {
                $status = 'crit';
                $statustitle = "teampage viewed, no submissions";
            }
            if (isset($teams_that_submitted[$t->getTeamId()]) && $teams_that_submitted[$t->getTeamId()] > 0) {
                $status = "warn";
                $statustitle   = "submitted, none correct";
                $num_submitted = $teams_that_submitted[$t->getTeamId()];
            }
            if (isset($teams_that_solved[$t->getTeamId()]) && $teams_that_solved[$t->getTeamId()] > 0) {
                $status = "ok";
                $statustitle = "correct submissions(s)";
                $num_solved  = $teams_that_solved[$t->getTeamId()];
            }

            // Create action links
            if ($this->isGranted('ROLE_ADMIN')) {
                $teamactions[] = [
                    'icon' => 'edit',
                    'title' => 'edit this team',
                    'link' => $this->generateUrl('jury_team_edit', [
                        'teamId' => $t->getTeamid(),
                    ]),
                ];
                $teamactions[] = [
                    'icon' => 'trash-alt',
                    'title' => 'delete this team',
                    'link' => $this->generateUrl('jury_team_delete', [
                        'teamId' => $t->getTeamId(),
                    ]),
                    'ajaxModal' => true,
                ];
            }
            $teamactions[] = [
                'icon' => 'envelope',
                'title' => 'send clarification to this team',
                'link' => $this->generateUrl('jury_clarification_send', [
                    'teamto' => $t->getTeamId(),
                ])
            ];

            // Add the rest of our row data for the table

            // Fix affiliation rendering
            if ($t->getAffiliation()) {
                $teamdata['affiliation'] = [
                    'value' => $t->getAffiliation()->getShortname(),
                    'title' => $t->getAffiliation()->getName()
                ];
            } else {
                $teamdata['affiliation'] = ['value' => '&nbsp;'];
            }

            // render IP address nicely
            if (!$t->getUsers()->isEmpty() && $t->getUsers()->first()->getLastIpAddress()) {
                $teamdata['ip_address']['value'] = Utils::printhost($t->getUsers()->first()->getLastIpAddress());
            }
            $teamdata['ip_address']['default']  = '-';
            $teamdata['ip_address']['cssclass'] = 'text-monospace small';

            // merge in the rest of the data
            $teamdata = array_merge($teamdata, [
                'num_contests' => ['value' => (int)($t->getContests()->count()) + $num_public_contests],
                'status' => [
                    'value' => $status,
                    'title' => $statustitle,
                ],
                'stats' => [
                    'cssclass' => 'text-right',
                    'value' => "$num_solved/$num_submitted",
                    'title' => "$num_solved correct / $num_submitted submitted",
                ],
            ]);
            // Save this to our list of rows
            $teams_table[] = [
                'data' => $teamdata,
                'actions' => $teamactions,
                'link' => $this->generateUrl('jury_team', ['teamId' => $t->getTeamId()]),
                'cssclass' => "category" . $t->getCategory()->getCategoryId() .
                    ($t->getEnabled() ? '' : ' disabled'),
            ];
        }
        return $this->render('@DOMJudge/jury/teams.html.twig', [
            'teams' => $teams_table,
            'table_fields' => $table_fields,
            'num_actions' => $this->isGranted('ROLE_ADMIN') ? 3 : 1,
        ]);
    }

    /**
     * @Route("/{teamId}", name="jury_team", requirements={"teamId": "\d+"})
     * @param int               $teamId
     * @param ScoreboardService $scoreboardService
     * @param SubmissionService $submissionService
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    public function viewAction(
        Request $request,
        int $teamId,
        ScoreboardService $scoreboardService,
        SubmissionService $submissionService
    ) {
        /** @var Team $team */
        $team = $this->em->getRepository(Team::class)->find($teamId);
        if (!$team) {
            throw new NotFoundHttpException(sprintf('Team with ID %s not found', $teamId));
        }

        $data = [
            'refresh' => [
                'after' => 15,
                'url' => $request->getRequestUri(),
                'ajax' => true,
            ],
            'team' => $team,
            'showAffiliations' => (bool)$this->dj->dbconfig_get('show_affiliations', true),
            'showFlags' => (bool)$this->dj->dbconfig_get('show_flags', true),
            'showContest' => count($this->dj->getCurrentContests()) > 1,
            'maxWidth' => $this->dj->dbconfig_get("team_column_width", 0),
        ];

        $currentContest = $this->dj->getCurrentContest();
        if ($request->query->has('cid')) {
            if (isset($this->dj->getCurrentContests()[$request->query->get('cid')])) {
                $currentContest = $this->dj->getCurrentContests()[$request->query->get('cid')];
            }
        }

        if ($currentContest) {
            $data['scoreboard']           = $scoreboardService->getTeamScoreboard($currentContest, $teamId, true);
            $data['showFlags']            = $this->dj->dbconfig_get('show_flags', true);
            $data['showAffiliationLogos'] = $this->dj->dbconfig_get('show_affiliation_logos', false);
            $data['showAffiliations']     = $this->dj->dbconfig_get('show_affiliations', true);
            $data['showPending']          = $this->dj->dbconfig_get('show_pending', false);
            $data['showTeamSubmissions']  = $this->dj->dbconfig_get('show_teams_submissions', true);
            $data['scoreInSeconds']       = $this->dj->dbconfig_get('score_in_seconds', false);
            $data['limitToTeams']         = [$team];
        }

        // We need to clear the entity manager, because loading the team scoreboard seems to break getting submission
        // contestproblems for the contest we get the scoreboard for
        $this->em->clear();

        $restrictions    = [];
        $restrictionText = null;
        if ($request->query->has('restrict')) {
            $restrictions     = $request->query->get('restrict');
            $restrictionTexts = [];
            foreach ($restrictions as $key => $value) {
                switch ($key) {
                    case 'probid':
                        $restrictionKeyText = 'problem';
                        break;
                    case 'langid':
                        $restrictionKeyText = 'language';
                        break;
                    case 'judgehost':
                        $restrictionKeyText = 'judgehost';
                        break;
                    default:
                        throw new BadRequestHttpException(sprintf('Restriction on %s not allowed.', $key));
                }
                $restrictionTexts[] = sprintf('%s %s', $restrictionKeyText, $value);
            }
            $restrictionText = implode(', ', $restrictionTexts);
        }
        $restrictions['teamid'] = $teamId;
        list($submissions, $submissionCounts) = $submissionService->getSubmissionList($this->dj->getCurrentContests(),
                                                                                      $restrictions);
        $data['restrictionText']    = $restrictionText;
        $data['submissions']        = $submissions;
        $data['submissionCounts']   = $submissionCounts;
        $data['showExternalResult'] = $this->dj->dbconfig_get('data_source', 0) == 2;

        if ($request->isXmlHttpRequest()) {
            $data['displayRank'] = true;
            $data['jury']        = true;
            return $this->render('@DOMJudge/jury/partials/team_score_and_submissions.html.twig', $data);
        }

        return $this->render('@DOMJudge/jury/team.html.twig', $data);
    }

    /**
     * @Route("/{teamId}/edit", name="jury_team_edit", requirements={"teamId": "\d+"})
     * @Security("has_role('ROLE_ADMIN')")
     * @param Request $request
     * @param int     $teamId
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    public function editAction(Request $request, int $teamId)
    {
        /** @var Team $team */
        $team = $this->em->getRepository(Team::class)->find($teamId);
        if (!$team) {
            throw new NotFoundHttpException(sprintf('Team with ID %s not found', $teamId));
        }

        $form = $this->createForm(TeamType::class, $team);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->saveEntity($this->em, $this->eventLogService, $this->dj, $team,
                              $team->getTeamid(), false);
            return $this->redirect($this->generateUrl('jury_team',
                                                      ['teamId' => $team->getTeamid()]));
        }

        return $this->render('@DOMJudge/jury/team_edit.html.twig', [
            'team' => $team,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{teamId}/delete", name="jury_team_delete", requirements={"teamId": "\d+"})
     * @Security("has_role('ROLE_ADMIN')")
     * @param Request $request
     * @param int     $teamId
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    public function deleteAction(Request $request, int $teamId)
    {
        /** @var Team $team */
        $team = $this->em->getRepository(Team::class)->find($teamId);
        if (!$team) {
            throw new NotFoundHttpException(sprintf('Team with ID %s not found', $teamId));
        }

        return $this->deleteEntity($request, $this->em, $this->dj, $team, $team->getName(),
                                   $this->generateUrl('jury_teams'));
    }

    /**
     * @Route("/add", name="jury_team_add")
     * @Security("has_role('ROLE_ADMIN')")
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Exception
     */
    public function addAction(Request $request)
    {
        $team = new Team();
        $team->setAddUserForTeam(true);
        $team->addUser(new User());
        $form = $this->createForm(TeamType::class, $team);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var User $user */
            $user = $team->getUsers()->first();
            if (!$team->getAddUserForTeam()) {
                // If we do not want to add a user, remove it again
                $team->removeUser($user);
            } else {
                // Otherwise, add the team role to it
                /** @var Role $role */
                $role = $this->em->createQueryBuilder()
                    ->from('DOMJudgeBundle:Role', 'r')
                    ->select('r')
                    ->andWhere('r.dj_role = :team')
                    ->setParameter(':team', 'team')
                    ->getQuery()
                    ->getOneOrNullResult();
                $user->addRole($role);
                $user->setTeam($team);
                // Also set the user's name to the team name
                $user->setName($team->getName());
                $this->em->persist($user);
            }
            $this->em->persist($team);
            $this->saveEntity($this->em, $this->eventLogService, $this->dj, $team,
                              $team->getTeamid(), true);
            return $this->redirect($this->generateUrl('jury_team',
                                                      ['teamId' => $team->getTeamid()]));
        }

        return $this->render('@DOMJudge/jury/team_add.html.twig', [
            'team' => $team,
            'form' => $form->createView(),
        ]);
    }
}
