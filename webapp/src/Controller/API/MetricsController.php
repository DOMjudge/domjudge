<?php declare(strict_types=1);

namespace App\Controller\API;

use App\DataTransferObject\SubmissionRestriction;
use App\Entity\Balloon;
use App\Entity\QueueTask;
use App\Entity\Submission;
use App\Entity\Team;
use App\Entity\User;
use App\Service\DOMJudgeService;
use App\Service\SubmissionService;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use OpenApi\Attributes as OA;
use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_API_READER')]
#[Route(path: '/metrics')]
#[OA\Tag(name: 'Metrics')]
#[OA\Response(ref: '#/components/responses/Unauthenticated', response: 401)]
#[OA\Response(ref: '#/components/responses/Unauthorized', response: 403)]
class MetricsController extends AbstractFOSRestController
{
    public function __construct(
        protected readonly EntityManagerInterface $em,
        protected readonly DOMJudgeService $dj,
        protected readonly SubmissionService $submissionService,
        protected readonly CollectorRegistry $registry
    ) {}

    /**
     * Metrics of this installation for use by Prometheus.
     */
    #[Rest\Get('/prometheus')]
    #[OA\Response(
        response: 200,
        description: 'Metrics of this installation for use by Prometheus',
        content: new OA\MediaType(mediaType: 'text/plain')
    )]
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
        $m['queuetasks']             = $registry->getOrRegisterGauge('domjudge', 'queuetasks', 'Number of queued tasks for judgehosts');

        // Get global team login metrics.
        $m['teams_users']           = $registry->getOrRegisterGauge('domjudge', 'teams_users', "Total number of users in teams", ['contest']);
        $m['teams_users_logged_in'] = $registry->getOrRegisterGauge('domjudge', 'teams_users_logged_in', "Number of users of teams logged in", ['contest']);
        $m['teams']                 = $registry->getOrRegisterGauge('domjudge', 'teams', "Total number of teams", ['contest']);
        $m['teams_logged_in']       = $registry->getOrRegisterGauge('domjudge', 'teams_logged_in', "Number of teams logged in", ['contest']);
        $m['teams_logged_in_ui']    = $registry->getOrRegisterGauge('domjudge', 'teams_logged_in_ui', "Number of teams logged in via UI", ['contest']);
        $m['teams_logged_in_api']   = $registry->getOrRegisterGauge('domjudge', 'teams_logged_in_api', "Number of teams logged in via API", ['contest']);
        $m['teams_submitted']       = $registry->getOrRegisterGauge('domjudge', 'teams_submitted', "Number of teams that have submitted at least once", ['contest']);
        $m['teams_correct']         = $registry->getOrRegisterGauge('domjudge', 'teams_correct', "Number of teams that have solved at least one problem", ['contest']);

        // Get Balloon statistics
        $m['balloons_longest_waitingtime'] = $registry->getOrRegisterGauge('domjudge', 'balloons_longest_waitingtime', "Current longest waiting time for a balloon", ['contest']);
        $m['balloons_waiting']             = $registry->getOrRegisterGauge('domjudge', 'balloons_waiting', "Balloons left todo", ['contest']);


        $allteams = $em
            ->createQueryBuilder()
            ->select('t', 'u')
            ->from(Team::class, 't')
            ->leftJoin('t.users', 'u')
            ->join('t.category', 'cat')
            ->andWhere('cat.visible = true')
            ->getQuery()
            ->getResult();

        $allteamsusers = $em
            ->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->leftJoin('u.team', 't')
            ->join('t.category', 'cat')
            ->andWhere('cat.visible = true')
            ->getQuery()
            ->getResult();

        // Compute some regular gauges (how many submissions pending, etc).
        foreach ($this->dj->getCurrentContests(alsofuture: true) as $contest) {
            $labels = [$contest->getShortname()];

            // Get submissions stats for the contest.
            /** @var Submission[] $submissions */
            [$submissions, $submissionCounts] = $this->submissionService->getSubmissionList(
                [$contest->getCid() => $contest],
                new SubmissionRestriction(visible: true),
                paginated: false
            );
            foreach ($submissionCounts as $kind => $count) {
                if (!array_key_exists('submissions_' . $kind, $m)) {
                         continue;
                }
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

            $teamsusers = [];
            if ($contest->isOpenToAllTeams()) {
                $teamsusers = $allteamsusers;
            } else {
                $teamsusers = $em
                    ->createQueryBuilder()
                    ->select('t', 'u')
                    ->from(User::class, 'u')
                    ->leftJoin('u.team', 't')
                    ->leftJoin('t.contests', 'c')
                    ->join('t.category', 'cat')
                    ->leftJoin('cat.contests', 'cc')
                    ->andWhere('c.cid = :cid OR cc.cid = :cid')
                    ->andWhere('cat.visible = true')
                    ->setParameter('cid', $contest->getCid())
                    ->getQuery()
                    ->getResult();
            }
            reset($teamsusers);

            // Total number of teams in the contest.
            $total_teams = sizeof($teams);
            $m['teams']->set($total_teams, $labels);

            // Total number of users in teams in the contest.
            $total_teams_users = sizeof($teamsusers);
            $m['teams_users']->set($total_teams_users, $labels);

            // Figure out how many of the teams have users that logged in.
            $teams_logged_in = 0;
            foreach ($teams as $t) {
                foreach ($t->getUsers() as $user) {
                    if ($user->getFirstLogin() != null or
                        $user->getLastApiLogin() != null
                    ) {
                        $teams_logged_in++;
                        break;
                    }
                }
            }
            $m['teams_logged_in']->set($teams_logged_in, $labels);

            $teams_logged_in_ui = 0;
            foreach ($teams as $t) {
                foreach ($t->getUsers() as $user) {
                    if ($user->getFirstLogin() != null) {
                        $teams_logged_in_ui++;
                        break;
                    }
                }
            }
            $m['teams_logged_in_ui']->set($teams_logged_in_ui, $labels);

            $teams_logged_in_api = 0;
            foreach ($teams as $t) {
                foreach ($t->getUsers() as $user) {
                    if ($user->getLastApiLogin() != null) {
                        $teams_logged_in_api++;
                        break;
                    }
                }
            }
            $m['teams_logged_in_api']->set($teams_logged_in_api, $labels);

            $teams_users_logged_in = 0;
            foreach ($teams as $t) {
                foreach ($t->getUsers() as $user) {
                    if ($user->getFirstLogin() != null or
                        $user->getLastApiLogin() != null
                    ) {
                        $teams_users_logged_in++;
                    }
                }
            }
            $m['teams_users_logged_in']->set($teams_users_logged_in, $labels);

            $balloons_waiting = $em
                ->createQueryBuilder()
                ->select('b', 's')
                ->from(Balloon::class, 'b')
                ->join('b.submission', 's')
                ->join('s.contest', 'c')
                ->join('s.team', 't')
                ->join('t.category', 'cat')
                ->andWhere('b.done = false')
                ->andWhere('c.cid = :cid')
                ->andWhere('cat.visible = true')
                ->setParameter('cid', $contest->getCid())
                ->getQuery()
                ->getResult();
            $m['balloons_waiting']->set(sizeof($balloons_waiting), $labels);

            $balloons_longest_waitingtime = 0.0;
            $n = Utils::now();
            foreach ($balloons_waiting as $b) {
                $t = $b->getSubmission()->getSubmittime();
                $t2 = $n-$t;
                $balloons_longest_waitingtime = max($t2, $balloons_longest_waitingtime);
            }
            $m['balloons_longest_waitingtime']->set($balloons_longest_waitingtime, $labels);
        }
        $queueTasks = $this->em->createQueryBuilder()
            ->select('qt')
            ->from(QueueTask::class, 'qt')
            ->getQuery()->getResult();

        $m['queuetasks']->set(sizeof($queueTasks));

        // Kinda ugly that we have to go to the registry directly to get the metrics out for
        // rendering, but it seems to work well enough.
        $renderer = new RenderTextFormat();
        $result = $renderer->render($registry->getMetricFamilySamples());
        return new Response($result, 200, ['Content-Type' => RenderTextFormat::MIME_TYPE]);
    }
}
