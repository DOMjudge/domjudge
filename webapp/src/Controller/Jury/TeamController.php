<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Controller\BaseController;
use App\Entity\Contest;
use App\Entity\Role;
use App\Entity\Team;
use App\Entity\User;
use App\Form\Type\TeamType;
use App\Service\AssetUpdateService;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Service\ScoreboardService;
use App\Service\SubmissionService;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/jury/teams")
 * @IsGranted("ROLE_JURY")
 */
class TeamController extends BaseController
{
    protected EntityManagerInterface $em;
    protected DOMJudgeService $dj;
    protected ConfigurationService $config;
    protected KernelInterface $kernel;
    protected EventLogService $eventLogService;
    protected AssetUpdateService $assetUpdater;

    public function __construct(
        EntityManagerInterface $em,
        DOMJudgeService $dj,
        ConfigurationService $config,
        KernelInterface $kernel,
        EventLogService $eventLogService,
        AssetUpdateService $assetUpdater
    ) {
        $this->em              = $em;
        $this->dj              = $dj;
        $this->config          = $config;
        $this->eventLogService = $eventLogService;
        $this->kernel          = $kernel;
        $this->assetUpdater    = $assetUpdater;
    }

    /**
     * @Route("", name="jury_teams")
     */
    public function indexAction(): Response
    {
        /** @var Team[] $teams */
        $teams = $this->em->createQueryBuilder()
            ->select('t', 'c', 'a', 'cat')
            ->from(Team::class, 't')
            ->leftJoin('t.contests', 'c')
            ->leftJoin('t.affiliation', 'a')
            ->leftJoin('t.category', 'cat')
            ->leftJoin('cat.contests', 'cc')
            ->orderBy('cat.sortorder', 'ASC')
            ->addOrderBy('t.name', 'ASC')
            ->getQuery()->getResult();

        $contests                       = $this->dj->getCurrentContests();
        $num_open_to_all_teams_contests = $this->em->createQueryBuilder()
            ->select('count(c.cid) as num_contests')
            ->from(Contest::class, 'c')
            ->andWhere('c.openToAllTeams = 1')
            ->getQuery()->getSingleResult()['num_contests'];
        $teams_that_submitted   = $this->em->createQueryBuilder()
            ->select('t.teamid as teamid, count(t.teamid) as num_submitted')
            ->from(Team::class, 't')
            ->join('t.submissions', 's')
            ->groupBy('s.team')
            ->andWhere('s.contest in (:contests)')
            ->setParameter('contests', $contests)
            ->getQuery()->getResult();
        $teams_that_submitted   = array_column($teams_that_submitted, 'num_submitted', 'teamid');

        $teams_that_solved = $this->em->createQueryBuilder()
            ->select('t.teamid as teamid, count(t.teamid) as num_correct')
            ->from(Team::class, 't')
            ->join('t.submissions', 's')
            ->join('s.judgings', 'j')
            ->groupBy('s.team')
            ->andWhere('s.contest in (:contests)')
            ->andWhere('j.valid = 1')
            ->andWhere('j.result = :result')
            ->setParameter('contests', $contests)
            ->setParameter('result', "correct")
            ->getQuery()->getResult();
        // Turn that into an array with key of teamid, value as number correct.
        $teams_that_solved = array_column($teams_that_solved, 'num_correct', 'teamid');

        $table_fields = [
            'teamid' => ['title' => 'ID', 'sort' => true, 'default_sort' => true],
            'icpcid' => ['title' => 'ICPC ID', 'sort' => true,],
            'effective_name' => ['title' => 'name', 'sort' => true,],
            'category' => ['title' => 'category', 'sort' => true,],
            'affiliation' => ['title' => 'affiliation', 'sort' => true,],
            'num_contests' => ['title' => '# contests', 'sort' => true,],
            'ip_address' => ['title' => 'last IP', 'sort' => true,],
            'room' => ['title' => 'room', 'sort' => true,],
            'status' => ['title' => '', 'sort' => false,],
            'stats' => ['title' => 'stats', 'sort' => true,],
        ];

        // Insert external ID field when configured to use it.
        if ($externalIdField = $this->eventLogService->externalIdFieldForEntity(Team::class)) {
            $table_fields = array_slice($table_fields, 0, 1, true) +
                [$externalIdField => ['title' => 'external ID', 'sort' => true]] +
                array_slice($table_fields, 1, null, true);
        }

        $userDataPerTeam = $this->em->createQueryBuilder()
            ->from(Team::class, 't', 't.teamid')
            ->leftJoin('t.users', 'u')
            ->select('t.teamid', 'u.last_ip_address', 'u.first_login')
            ->groupBy('t.teamid', 'u.last_ip_address', 'u.first_login')
            ->getQuery()
            ->getResult();

        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $teams_table      = [];
        foreach ($teams as $t) {
            $teamdata    = [];
            $teamactions = [];
            // Get whatever fields we can from the team object itself.
            foreach ($table_fields as $k => $v) {
                if ($propertyAccessor->isReadable($t, $k)) {
                    $teamdata[$k] = ['value' => $propertyAccessor->getValue($t, $k)];
                }
            }

            // Add some elements for the solved status.
            $num_solved    = 0;
            $num_submitted = 0;
            $status = 'noconn';
            $statustitle = "no connections made";
            if ($userDataPerTeam[$t->getTeamid()]['first_login'] ?? null) {
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

            // Create action links.
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

            // Add the rest of our row data for the table.

            // Fix affiliation rendering.
            if ($t->getAffiliation()) {
                $teamdata['affiliation'] = [
                    'value' => $t->getAffiliation()->getShortname(),
                    'title' => $t->getAffiliation()->getName()
                ];
            }

            // Render IP address nicely.
            if ($userDataPerTeam[$t->getTeamid()]['last_ip_address'] ?? null) {
                $teamdata['ip_address']['value'] = Utils::printhost($userDataPerTeam[$t->getTeamid()]['last_ip_address']);
            }
            $teamdata['ip_address']['default']  = '-';
            $teamdata['ip_address']['cssclass'] = 'text-monospace small';

            $teamContests = [];
            foreach ($t->getContests() as $c) {
                $teamContests[$c->getCid()] = true;
            }
            if ($t->getCategory()) {
                foreach ($t->getCategory()->getContests() as $c) {
                    $teamContests[$c->getCid()] = true;
                }
            }
            // Merge in the rest of the data.
            $teamdata = array_merge($teamdata, [
                'num_contests' => ['value' => count($teamContests) + $num_open_to_all_teams_contests],
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
            // Save this to our list of rows.
            $teams_table[] = [
                'data' => $teamdata,
                'actions' => $teamactions,
                'link' => $this->generateUrl('jury_team', ['teamId' => $t->getTeamId()]),
                'cssclass' => ($t->getCategory() ? ("category" . $t->getCategory()->getCategoryId()) : '') .
                    ($t->getEnabled() ? '' : ' disabled'),
            ];
        }
        return $this->render('jury/teams.html.twig', [
            'teams' => $teams_table,
            'table_fields' => $table_fields,
        ]);
    }

    /**
     * @Route("/{teamId<\d+>}", name="jury_team")
     */
    public function viewAction(
        Request $request,
        int $teamId,
        ScoreboardService $scoreboardService,
        SubmissionService $submissionService
    ): Response {
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
            'showAffiliations' => (bool)$this->config->get('show_affiliations'),
            'showFlags' => (bool)$this->config->get('show_flags'),
            'showContest' => count($this->dj->getCurrentContests()) > 1,
            'maxWidth' => $this->config->get("team_column_width"),
        ];

        $currentContest = $this->dj->getCurrentContest();
        if ($request->query->has('cid')
            && isset($this->dj->getCurrentContests()[$request->query->get('cid')])
        ) {
            $currentContest = $this->dj->getCurrentContests()[$request->query->get('cid')];
        }

        if ($currentContest) {
            $scoreboard = $scoreboardService
                ->getTeamScoreboard($currentContest, $teamId, true);
            $data = array_merge(
                $data,
                $scoreboardService->getScoreboardTwigData(
                    $request, null, '', true, false, false,
                    $currentContest, $scoreboard
                )
            );
            $data['limitToTeams'] = [$team];
        }

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
        list($submissions, $submissionCounts) =
            $submissionService->getSubmissionList($this->dj->getCurrentContests(), $restrictions);

        $data['restrictionText']    = $restrictionText;
        $data['submissions']        = $submissions;
        $data['submissionCounts']   = $submissionCounts;
        $data['showExternalResult'] = $this->config->get('data_source') ===
            DOMJudgeService::DATA_SOURCE_CONFIGURATION_AND_LIVE_EXTERNAL;

        if ($request->isXmlHttpRequest()) {
            $data['displayRank'] = true;
            $data['jury']        = true;
            return $this->render('jury/partials/team_score_and_submissions.html.twig', $data);
        }

        return $this->render('jury/team.html.twig', $data);
    }

    /**
     * @Route("/{teamId<\d+>}/edit", name="jury_team_edit")
     * @IsGranted("ROLE_ADMIN")
     */
    public function editAction(Request $request, int $teamId): Response
    {
        /** @var Team $team */
        $team = $this->em->getRepository(Team::class)->find($teamId);
        if (!$team) {
            throw new NotFoundHttpException(sprintf('Team with ID %s not found', $teamId));
        }

        $form = $this->createForm(TeamType::class, $team);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->assetUpdater->updateAssets($team);
            $this->saveEntity($this->em, $this->eventLogService, $this->dj, $team,
                              $team->getTeamid(), false);
            return $this->redirect($this->generateUrl(
                'jury_team',
                ['teamId' => $team->getTeamid()]
            ));
        }

        return $this->render('jury/team_edit.html.twig', [
            'team' => $team,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{teamId<\d+>}/delete", name="jury_team_delete")
     * @IsGranted("ROLE_ADMIN")
     */
    public function deleteAction(Request $request, int $teamId): Response
    {
        /** @var Team $team */
        $team = $this->em->getRepository(Team::class)->find($teamId);
        if (!$team) {
            throw new NotFoundHttpException(sprintf('Team with ID %s not found', $teamId));
        }

        return $this->deleteEntities($request, $this->em, $this->dj, $this->eventLogService, $this->kernel,
                                     [$team], $this->generateUrl('jury_teams'));
    }

    /**
     * @Route("/add", name="jury_team_add")
     * @IsGranted("ROLE_ADMIN")
     */
    public function addAction(Request $request): Response
    {
        $team = new Team();
        $team->setAddUserForTeam(Team::CREATE_NEW_USER);
        $form = $this->createForm(TeamType::class, $team);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var User $user */
            $user = $team->getUsers()->first();
            if ($team->getAddUserForTeam() === Team::CREATE_NEW_USER) {
                // Create a user for the team.
                $user = new User();
                $user->setUsername($team->getNewUsername());
                $team->addUser($user);
                // Make sure the user has the team role to make validation work.
                $role = $this->em->getRepository(Role::class)->findOneBy(['dj_role' => 'team']);
                $user->addUserRole($role);
                // Set the user's name to the team name when creating a new user.
                $user->setName($team->getEffectiveName());
            } elseif ($team->getAddUserForTeam() === Team::ADD_EXISTING_USER) {
                $team->addUser($team->getExistingUser());
            }
            $this->em->persist($team);
            $this->assetUpdater->updateAssets($team);
            $this->saveEntity($this->em, $this->eventLogService, $this->dj, $team, null, true);
            return $this->redirect($this->generateUrl(
                'jury_team',
                ['teamId' => $team->getTeamid()]
            ));
        }

        return $this->render('jury/team_add.html.twig', [
            'team' => $team,
            'form' => $form->createView(),
        ]);
    }
}
