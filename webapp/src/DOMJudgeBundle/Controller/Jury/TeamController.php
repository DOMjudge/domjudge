<?php declare(strict_types=1);

namespace DOMJudgeBundle\Controller\Jury;

use Doctrine\ORM\EntityManagerInterface;
use DOMJudgeBundle\Entity\Team;
use DOMJudgeBundle\Service\DOMJudgeService;
use DOMJudgeBundle\Service\EventLogService;
use DOMJudgeBundle\Utils\Utils;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\Annotation\Route;


/**
 * @Route("/jury")
 * @Security("has_role('ROLE_JURY')")
 */
class TeamController extends Controller
{
    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var DOMJudgeService
     */
    private $DOMJudgeService;

    /**
     * @var EventLogService
     */
    protected $eventLogService;

    public function __construct(
        EntityManagerInterface $entityManager,
        DOMJudgeService $DOMJudgeService,
        EventLogService $eventLogService
    ) {
        $this->entityManager   = $entityManager;
        $this->DOMJudgeService = $DOMJudgeService;
        $this->eventLogService = $eventLogService;
    }

    /**
     * @Route("/teams/", name="jury_teams")
     */
    public function indexAction(Request $request, Packages $assetPackage)
    {
        /** @var Team[] $teams */
        $teams = $this->entityManager->createQueryBuilder()
            ->select('t', 'c', 'a', 'cat')
            ->from('DOMJudgeBundle:Team', 't')
            ->leftJoin('t.contests', 'c')
            ->leftJoin('t.affiliation', 'a')
            ->join('t.category', 'cat')
            ->orderBy('cat.sortorder', 'ASC')
            ->addOrderBy('t.name', 'ASC')
            ->getQuery()->getResult();

        $contests             = $this->DOMJudgeService->getCurrentContests();
        $num_public_contests  = $this->entityManager->createQueryBuilder()
            ->select('count(c.cid) as num_contests')
            ->from('DOMJudgeBundle:Contest', 'c')
            ->andWhere('c.public = 1')
            ->getQuery()->getSingleResult()['num_contests'];
        $teams_that_submitted = $this->entityManager->createQueryBuilder()
            ->select('t.teamid as teamid, count(t.teamid) as num_submitted')
            ->from('DOMJudgeBundle:Team', 't')
            ->join('t.submissions', 's')
            ->groupBy('s.team')
            ->andWhere('s.contest in (:contests)')
            ->setParameter('contests', $contests)
            ->getQuery()->getResult();
        $teams_that_submitted = array_column($teams_that_submitted, 'num_submitted', 'teamid');

        $teams_that_solved = $this->entityManager->createQueryBuilder()
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
            'name' => ['title' => 'teamname', 'sort' => true, 'default_sort' => true],
            'category' => ['title' => 'category', 'sort' => true,],
            'affiliation' => ['title' => 'affiliation', 'sort' => true,],
            'num_contests' => ['title' => '# contests', 'sort' => true,],
            'hostname' => ['title' => 'host', 'sort' => true,],
            'room' => ['title' => 'room', 'sort' => true,],
            'bubble' => ['title' => '', 'sort' => false,],
            'status' => ['title' => 'status', 'sort' => true,],
        ];

        // Insert external ID field when configured to use it
        if ($externalIdField = $this->eventLogService->externalIdFieldForEntity(Team::class)) {
            $table_fields = array_slice($table_fields, 0, 1, true) +
                [$externalIdField => ['title' => 'external ID', 'sort' => true]] +
                array_slice($table_fields, 1, null, true);
        }

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
            $statusclass   = 'team-nocon';
            $statustitle   = "no connections made";
            if ($t->getTeampageFirstVisited()) {
                $statusclass = 'team-nosub';
                $statustitle = "teampage viewed, no submissions";
            }
            if (isset($teams_that_submitted[$t->getTeamId()]) && $teams_that_submitted[$t->getTeamId()] > 0) {
                $statusclass   = "team-nocor";
                $statustitle   = "submitted, none correct";
                $num_submitted = $teams_that_submitted[$t->getTeamId()];
            }
            if (isset($teams_that_solved[$t->getTeamId()]) && $teams_that_solved[$t->getTeamId()] > 0) {
                $statusclass = "team-ok";
                $statustitle = "correct submissions(s)";
                $num_solved  = $teams_that_solved[$t->getTeamId()];
            }

            // Create action links
            if ($this->isGranted('ROLE_ADMIN')) {
                $teamactions[] = [
                    'icon' => 'edit',
                    'title' => 'edit this team',
                    'link' => $this->generateUrl('legacy.jury_team', [
                        'cmd' => 'edit',
                        'id' => $t->getTeamId(),
                        'referrer' => 'teams'
                    ])
                ];
                $teamactions[] = [
                    'icon' => 'trash-alt',
                    'title' => 'delete this team',
                    'link' => $this->generateUrl('legacy.jury_delete', [
                        'table' => 'team',
                        'teamid' => $t->getTeamId(),
                        'referrer' => '',
                        'desc' => $t->getName(),
                    ])
                ];
            }
            $teamactions[] = [
                'icon' => 'envelope',
                'title' => 'send clarification to this team',
                'link' => $this->generateUrl('legacy.jury_clarification', [
                    'teamto' => $t->getTeamId(),
                ])
            ];

            // Add the rest of our row data for the table

            // Fix affiliation rendering
            if ($t->getAffiliation()) {
                $teamdata['affiliation'] = [
                    'value' => $t->getAffiliation()->getShortname(),
                    'linktitle' => $t->getAffiliation()->getName()
                ];
            } else {
                $teamdata['affiliation'] = ['value' => '&nbsp;'];
            }

            // render hostname nicely
            if ($teamdata['hostname']['value']) {
                $teamdata['hostname']['value'] = Utils::printhost($teamdata['hostname']['value']);
            }
            $teamdata['hostname']['default']  = '-';
            $teamdata['hostname']['cssclass'] = 'text-monospace small';

            // merge in the rest of the data
            $teamdata = array_merge($teamdata, [
                'num_contests' => ['value' => (int)($t->getContests()->count()) + $num_public_contests],
                'teamid' => ['value' => 't' . $t->getTeamId()],
                'bubble' => [
                    'value' => "\u{25CF}",
                    'cssclass' => $statusclass,
                    'linktitle' => $statustitle,
                ],
                'status' => [
                    'cssclass' => 'text-right',
                    'value' => "$num_solved/$num_submitted",
                    'linktitle' => "$num_solved correct / $num_submitted submitted",
                ],
            ]);
            // Save this to our list of rows
            $teams_table[] = [
                'data' => $teamdata,
                'actions' => $teamactions,
                'link' => $this->generateUrl('legacy.jury_team', ['id' => $t->getTeamId()]),
                'cssclass' => "category" . $t->getCategory()->getCategoryId()
            ];
        }
        return $this->render('@DOMJudge/jury/teams.html.twig', [
            'teams' => $teams_table,
            'table_fields' => $table_fields,
            'num_actions' => $this->isGranted('ROLE_ADMIN') ? 3 : 1,
        ]);
    }
}
