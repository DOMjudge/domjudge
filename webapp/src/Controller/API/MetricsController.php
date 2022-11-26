<?php declare(strict_types=1);

namespace App\Controller\API;

use App\Entity\Submission;
use App\Service\DOMJudgeService;
use App\Service\SubmissionService;
use App\Entity\Team;
use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use OpenApi\Annotations as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;

/**
 * @Route("/metrics")
 * @IsGranted("ROLE_API_READER")
 * @OA\Response(response="401", ref="#/components/responses/Unauthenticated")
 * @OA\Response(response="403", ref="#/components/responses/Unauthorized")
 * @OA\Tag(name="Metrics")
 */
class MetricsController extends AbstractFOSRestController
{
    protected EntityManagerInterface $em;
    protected DOMJudgeService $dj;
    protected SubmissionService $submissionService;
    protected CollectorRegistry $registry;

    public function __construct(
        EntityManagerInterface $em,
        DOMJudgeService $dj,
        SubmissionService $submissionService,
        CollectorRegistry $registry
    ) {
        $this->em                = $em;
        $this->dj                = $dj;
        $this->submissionService = $submissionService;
        $this->registry          = $registry;
    }

    /**
     * Metrics of this installation for use by Prometheus.
     * @Rest\Get("/prometheus")
     * @OA\Response(
     *     response="200",
     *     description="Metrics of this installation for use by Prometheus",
     *     @OA\MediaType(mediaType="text/plain"),
     * )
     */
    public function prometheusAction(): Response
    {
        $registry = $this->registry;
        $em = $this->em;

        $m = [];
        $m['submissions_total']      = $registry->getOrRegisterGauge('domjudge', 'submissions_total', "Total number of all submissions", ['contest']);
        $m['submissions_correct']    = $registry->getOrRegisterGauge('domjudge', 'submissions_correct', "Number of correct submissions", ['contest']);
        $m['submissions_ignored']    = $registry->getOrRegisterGauge('domjudge', 'submissions_ignored', "Number of ignored submissions", ['contest']);
        $m['submissions_unverified'] = $registry->getOrRegisterGauge('domjudge', 'submissions_unverified', "Number of unverified submissions", ['contest']);
        $m['submissions_queued']     = $registry->getOrRegisterGauge('domjudge', 'submissions_queued', "Number of queued submissions", ['contest']);
        $m['submissions_perteam']    = $registry->getOrRegisterGauge('domjudge', 'submissions_perteam', "Number of teams that have a queued submission", ['contest']);
        $m['submissions_judging']    = $registry->getOrRegisterGauge('domjudge', 'submissions_judging', 'Number of submissions that are actively judged', ['contest']);

        // Get global team login metrics.
        $m['teams']           = $registry->getOrRegisterGauge('domjudge', 'teams', "Total number of teams", ['contest']);
        $m['teams_logged_in'] = $registry->getOrRegisterGauge('domjudge', 'teams_logged_in', "Number of teams logged in", ['contest']);
        $m['teams_submitted']  = $registry->getOrRegisterGauge('domjudge', 'teams_submitted', "Number of teams that have submitted at least once", ['contest']);
        $m['teams_correct']    = $registry->getOrRegisterGauge('domjudge', 'teams_correct', "Number of teams that have solved at least one problem", ['contest']);

        $allteams = $em
            ->createQueryBuilder()
            ->select('t', 'u')
            ->from(Team::class, 't')
            ->leftJoin('t.users', 'u')
            ->join('t.category', 'cat')
            ->andWhere('cat.visible = true')
            ->getQuery()
            ->getResult();

        // Compute some regular gauges (how many submissions pending, etc).
        $include_future = true;
        foreach ($this->dj->getCurrentContests(null, $include_future) as $contest) {
            $labels = [$contest->getShortname()];

            // Get submissions stats for the contest.
            /** @var Submission[] $submissions */
            list($submissions, $submissionCounts) = $this->submissionService->getSubmissionList([$contest->getCid() => $contest], ['visible' => true], 0);
            foreach ($submissionCounts as $kind => $count) {
                $m['submissions_' . $kind]->set((int)$count, $labels);
            }
            // Get team submission stats for the contest.
            $teamids_correct = [];
            $teamids_submitted = [];
            foreach ($submissions as $s) {
                if ($s->getResult() == "correct") {
                    $teamids_correct[$s->getTeam()->getTeamid()] = 1;
                }
                $teamids_submitted[$s->getTeam()->getTeamid()] = 1;
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
                    ->andWhere('cat.visible = true')
                    ->setParameter('cid', $contest->getCid())
                    ->getQuery()
                    ->getResult();
            }
            reset($teams);

            // Total number of teams in the contest.
            $total_teams = sizeof($teams);
            $m['teams']->set($total_teams, $labels);

            // Figure out how many of the teams have users that logged in.
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
        // rendering, but it seems to work well enough.
        $renderer = new RenderTextFormat();
        $result = $renderer->render($registry->getMetricFamilySamples());
        return new Response($result, 200, ['Content-Type' => RenderTextFormat::MIME_TYPE]);
    }
}
