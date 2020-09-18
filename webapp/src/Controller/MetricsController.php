<?php declare(strict_types=1);

namespace App\Controller;

use App\Service\DOMJudgeService;
use App\Service\SubmissionService;
use App\Entity\Team;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;

/**
 * @Route("/prometheus/metrics")
 * @IsGranted("ROLE_ADMIN")
 */
class MetricsController extends AbstractController
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
     * @var SubmissionService
     */
    protected $submissionService;

    protected $registry;

    /**
     * MetricsController constructor.
     * @param EntityManagerInterface $em
     * @param DOMJudgeService        $dj
     * @param SubmissionService      $submissionService
     * @param CollectorRegistry      $registry
     */
    public function __construct(
        EntityManagerInterface $em,
        DOMJudgeService $dj,
        SubmissionService $submissionService,
        CollectorRegistry $registry
    ) {
        $this->em        = $em;
        $this->dj        = $dj;
        $this->submissionService = $submissionService;
        $this->registry = $registry;
    }

    /**
     * @Route("", name="prometheus_metrics")
     */
    public function indexAction(Request $request)
    {
        $registry = $this->registry;
        $em = $this->em;

        $m = [];
        $m['submissions_total']      = $registry->getOrRegisterGauge('domjudge', 'submissions_total', "Total number of all submissions", ['contest']);
        $m['submissions_correct']    = $registry->getOrRegisterGauge('domjudge', 'submissions_correct', "Number of correct submissions", ['contest']);
        $m['submissions_ignored']    = $registry->getOrRegisterGauge('domjudge', 'submissions_ignored', "Number of ignored submissions", ['contest']);
        $m['submissions_unverified'] = $registry->getOrRegisterGauge('domjudge', 'submissions_unverified', "Number of unverified submissions", ['contest']);
        $m['submissions_queued']     = $registry->getOrRegisterGauge('domjudge', 'submissions_queued', "Number of queued submissions", ['contest']);

        // Get global team login metrics
        $m['teams']           = $registry->getOrRegisterGauge('domjudge', 'teams', "Total number of teams", ['contest']);
        $m['teams_logged_in'] = $registry->getOrRegisterGauge('domjudge', 'teams_logged_in', "Number of teams logged in", ['contest']);
        $m['teams_submitted']  = $registry->getOrRegisterGauge('domjudge', 'teams_submitted', "Number of teams that have submitted at least once", ['contest']);
        $m['teams_correct']    = $registry->getOrRegisterGauge('domjudge', 'teams_correct', "Number of teams that have solved at least one problem", ['contest']);

        $allteams = $em
            ->createQueryBuilder()
            ->select('t', 'u')
            ->from(Team::class, 't')
            ->leftJoin('t.users', 'u')
            ->getQuery()
            ->getResult();

        // Compute some regular gauges(how many submissions pending, etc)
        $include_future = true;
        foreach ($this->dj->getCurrentContests(null, $include_future) as $contest ) {
            $labels = [$contest->getShortname()];

            // Get submissions stats for the contest
            list($submissions, $submissionCounts) = $this->submissionService->getSubmissionList([$contest->getCid() => $contest], [], 0);
            foreach ($submissionCounts as $kind => $count) {
                $m['submissions_' . $kind]->set($count, $labels);
            }
            // Get team submission stats for the contest
            $teamids_correct = [];
            $teamids_submitted = [];
            foreach($submissions as $s) {
                $result = $s->getResult();
                if ($s->getResult() == "correct") {
                    $teamids_correct[$s->getTeamid()] = 1;
                } else {
                    $teamids_submitted[$s->getTeamid()] = 1;
                }
            }
            $m['teams_submitted']->set(count($teamids_submitted), $labels);
            $m['teams_correct']->set(count($teamids_correct), $labels);

            // How many teams does the contest have?
            $teams = [];
            if ($contest->isOpenToAllTeams()) {
                $teams = $allteams;
            } else {
                $teams = $em
                    ->createQueryBuilder()
                    ->select('t', 'u')
                    ->from(Team::class, 't')
                    ->leftJoin('t.users', 'u')
                    ->leftJoin('t.contests', 'c')
                    ->join('t.category', 'cat')
                    ->leftJoin('cat.contests', 'cc')
                    ->andWhere('c.cid = :cid OR cc.cid = :cid')
                    ->setParameter(':cid', $contest->getCid())
                    ->getQuery()
                    ->getResult();
            }
            reset($teams);
            
            // Total number of teams in the contest
            $total_teams = sizeof($teams);
            $m['teams']->set($total_teams, $labels);

            // Figure out how many of the teams have users that logged in
            $teams_logged_in = 0;
            foreach ($teams as $t) {
                foreach ($t->getUsers() as $user) {
                    if ($user->getFirstLogin() != null) {
                        $teams_logged_in++;
                        break;
                    }
                }
            }
            $m['teams_logged_in']->set($teams_logged_in, $labels);
        }


        // Kinda ugly that we have to go to the registry directly to get the metrics out for
        // rendering, but it seems to work well enough
        $renderer = new RenderTextFormat();
        $result = $renderer->render($registry->getMetricFamilySamples());
        return new Response($result, 200, ['Content-Type' => RenderTextFormat::MIME_TYPE]);
    }
}