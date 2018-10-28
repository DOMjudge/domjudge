<?php declare(strict_types=1);

namespace DOMJudgeBundle\Controller\Jury;

use DOMJudgeBundle\Service\DOMJudgeService;
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
     * @var DOMJudgeService
     */
    private $DOMJudgeService;

    public function __construct(DOMJudgeService $DOMJudgeService)
    {
        $this->DOMJudgeService = $DOMJudgeService;
    }

    /**
     * @Route("/teams/", name="jury_teams")
     */
    public function indexAction(Request $request, Packages $assetPackage)
    {
        $em    = $this->getDoctrine()->getManager();
        $teams = $em->createQueryBuilder()
            ->select('t', 'c', 'a', 'cat')
            ->from('DOMJudgeBundle:Team', 't')
            ->leftJoin('t.contests', 'c')
            ->leftJoin('t.affiliation', 'a')
            ->join('t.category', 'cat')
            ->orderBy('cat.sortorder', 'ASC')
            ->addOrderBy('t.name', 'ASC')
            ->getQuery()->getResult();

        $contests             = $this->DOMJudgeService->getCurrentContests();
        $num_public_contests  = $em->createQueryBuilder()
            ->select('count(c.cid) as num_contests')
            ->from('DOMJudgeBundle:Contest', 'c')
            ->andWhere('c.public = 1')
            ->getQuery()->getSingleResult()['num_contests'];
        $teams_that_submitted = $em->createQueryBuilder()
            ->select('t.teamid as teamid, count(t.teamid) as num_submitted')
            ->from('DOMJudgeBundle:Team', 't')
            ->join('t.submissions', 's')
            ->groupBy('s.team')
            ->andWhere('s.contest in (:contests)')
            ->setParameter('contests', $contests)
            ->getQuery()->getResult();
        $teams_that_submitted = array_column($teams_that_submitted, 'num_submitted', 'teamid');

        $teams_that_solved = $em->createQueryBuilder()
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
        if ($this->isGranted('ROLE_ADMIN')) {
            $table_fields = array_merge($table_fields, [
                'edit' => ['title' => '', 'sort' => false,],
                'delete' => ['title' => '', 'sort' => false,],
            ]);
        }
        $table_fields['send'] = ['title' => '', 'sort' => false,];

        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $teams_table      = [];
        foreach ($teams as $t) {
            $teamdata = [];
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
            $editvalue   = '<i class="fas fa-edit" title="edit this team"></i>';
            $editlink    = $this->generateUrl('legacy.jury_team', [
                'cmd' => 'edit',
                'id' => $t->getTeamId(),
                'referrer' => 'teams/'
            ]);
            $deletevalue = '<i class="fas fa-trash-alt" title="delete this team"></i>';
            $deletelink  = $this->generateUrl('legacy.jury_delete', [
                'table' => 'team',
                'teamid' => $t->getTeamId(),
                'referrer' => '',
                'desc' => $t->getName(),
            ]);
            $sendvalue   = '<i class="fas fa-envelope" title="send clarification to this team"></i>';
            $sendlink    = $this->generateUrl('legacy.jury_clarification', [
                'teamto' => $t->getTeamId(),
            ]);
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
                'edit' => ['value' => $editvalue, 'link' => $editlink,],
                'delete' => ['value' => $deletevalue, 'link' => $deletelink,],
                'send' => ['value' => $sendvalue, 'link' => $sendlink,],
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
                'link' => $this->generateUrl('legacy.jury_team', ['id' => $t->getTeamId()]),
                'cssclass' => "category" . $t->getCategory()->getCategoryId()
            ];
        }
        return $this->render('@DOMJudge/jury/teams.html.twig', [
            'teams' => $teams_table,
            'table_fields' => $table_fields,
        ]);
    }

    /**
     * @Route("/teams.php", name="jury_teams_php_redirect")
     */
    public function teamsRedirectAction(Request $request)
    {
        return $this->redirectToRoute('jury_teams');
    }
}
