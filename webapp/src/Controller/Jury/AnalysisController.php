<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\Judging;
use App\Entity\Problem;
use App\Entity\Submission;
use App\Entity\Team;
use App\Service\DOMJudgeService;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/jury/analysis")
 * @IsGranted("ROLE_JURY")
 */
class AnalysisController extends AbstractController
{
    /**
     * @var DOMJudgeService
     */
    private $dj;

    const FILTERS = [
        'visiblecat' => 'Teams from visible categories',
        'hiddencat' => 'Teams from hidden categories',
        'all' => 'All teams',
    ];

    private static function set_or_increment(array &$array, $index)
    {
        if (!array_key_exists($index, $array)) {
            $array[$index] = 0;
        }
        $array[$index]++;
    }

    public function __construct(DOMJudgeService $dj)
    {
        $this->dj = $dj;
    }

    /**
     * Apply the filter to the given query builder
     * @param QueryBuilder $queryBuilder
     * @param string       $filter
     * @return QueryBuilder
     */
    protected function applyFilter(QueryBuilder $queryBuilder, string $filter)
    {
        switch ($filter) {
            case 'visiblecat':
                $queryBuilder->andWhere('tc.visible = true');
                break;
            case 'hiddencat':
                $queryBuilder->andWhere('tc.visible = false');
                break;
        }

        return $queryBuilder;
    }

    /**
     * @Route("", name="analysis_index")
     */
    public function indexAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $contest = $this->dj->getCurrentContest();

        if ($contest == null) {
          return $this->render('jury/error.html.twig', [
              'error' => 'No contest selected',
          ]);
        }

        $filterKeys = array_keys(self::FILTERS);
        $view = $request->query->get('view') ?: reset($filterKeys);

        // First collect information about problems in this contest
        $problems = $em->createQueryBuilder()
          ->select('cp', 'p')
          ->from(ContestProblem::class, 'cp')
          ->join('cp.problem', 'p')
          ->andWhere('cp.contest = :contest')
          ->setParameter('contest', $contest)
          ->getQuery()->getResult();

        // Need to query directly the count, otherwise symfony memory explodes
        // I think because it tries to load the testdata if you do this the naive way.
        $results = $em->createQueryBuilder()
          ->select('p.probid, count(tc.testcaseid) as num_testcases')
          ->from(Contest::class, 'c')
          ->join('c.problems', 'cp')
          ->join('cp.problem', 'p')
          ->leftJoin('p.testcases', 'tc')
          ->andWhere('c = :contest')
          ->groupBy('p.probid')
          ->setParameter('contest', $contest)
          ->getQuery()->getResult();
        $num_testcases = [];
        foreach($results as $r) {
          $num_testcases[$r['probid']] = $r['num_testcases'];
        }

        // Next select information about the teams
        if ($contest->isOpenToAllTeams()) {
            $teams = $this->applyFilter($em->createQueryBuilder()
                                            ->select('t', 'ts', 'j', 'lang', 'a')
                                            ->from(Team::class, 't')
                                            ->join('t.category', 'tc')
                                            ->join('t.affiliation', 'a')
                                            ->join('t.submissions', 'ts')
                                            ->join('ts.judgings', 'j')
                                            ->join('ts.language', 'lang')
                                            ->orderBy('t.teamid'), $view)
                ->getQuery()->getResult();
        } else {
            $teams = $this->applyFilter($em->createQueryBuilder()
                                            ->select('t', 'c', 'ts', 'j', 'lang', 'a')
                                            ->from(Team::class, 't')
                                            ->leftJoin('t.contests', 'c')
                                            ->join('t.affiliation', 'a')
                                            ->join('t.category', 'tc')
                                            ->leftJoin('tc.contests', 'cc')
                                            ->join('t.submissions', 'ts')
                                            ->join('ts.judgings', 'j')
                                            ->join('ts.language', 'lang')
                                            ->andWhere('c = :contest OR cc = :contest'), $view)
                ->orderBy('t.teamid')
                ->setParameter('contest', $contest)
                ->getQuery()->getResult();
        }

        // Figure out how many submissions each team has
        $results = $this->applyFilter($em->createQueryBuilder()
                                          ->select('s.teamid as teamid, count(s.teamid) as num_submissions')
                                          ->from(Submission::class, 's')
                                          ->join('s.team', 't')
                                          ->join('t.category', 'tc')
                                          ->andWhere('s.contest = :contest'), $view)
            ->groupBy('s.teamid')
            ->setParameter('contest', $contest)
            ->getQuery()->getResult();
        $num_submissions = [];
        foreach($results as $r) {
          $num_submissions[$r['teamid']] = $r['num_submissions'];
        }


        // Come up with misc stats
        // find last correct judgement for a team, figure out how many minutes are left in the contest(or til now if now is earlier)
        $now = (new \DateTime())->getTimeStamp();
        $misc = [
          'total_submissions' => 0,
          'total_accepted' => 0,
          'num_teams' => count($teams),
          'problem_num_testcases' => $num_testcases,
          'team_num_submissions' => $num_submissions,

          'team_attempted_n_problems' => [],
          'teams_solved_n_problems' => [],

          'problem_attempts' => [],
          'problem_solutions' => [],

          'problem_stats' => [
            'teams_attempted' => [],
            'teams_solved' => [],
          ],

          'language_stats' => [
            'total_submissions' => [],
            'total_solutions' => [],
            'teams_attempted' => [],
            'teams_solved' => [],
          ],
        ];

        $total_misery_minutes = 0;
        $submissions = [];
        foreach($teams as $team) {
          $lastSubmission = null;
          $team_stats = [
            'total_submitted' => 0,
            'total_accepted' => 0,
            'problems_submitted' => [],
            'problems_accepted' => [],
          ];
          foreach ($team->getSubmissions() as $s) {
            if ($s->getContest() != $contest) continue;
            if ($s->getSubmitTime() > $contest->getEndTime()) continue;
            if ($s->getSubmitTime() < $contest->getStartTime()) continue;

            $submissions[] = $s;
            $misc['total_submissions']++;
            $team_stats['total_submitted']++;
            AnalysisController::set_or_increment($misc['problem_attempts'], $s->getProbId());
            AnalysisController::set_or_increment($team_stats['problems_submitted'], $s->getProbId());
            $misc['problem_stats']['teams_attempted'][$s->getProbId()][$team->getTeamId()] = $team->getTeamId();


            AnalysisController::set_or_increment($misc['language_stats']['total_submissions'], $s->getLangid());
            $misc['language_stats']['teams_attempted'][$s->getLangid()][$team->getTeamId()] = $team->getTeamId();

            if ($s->getResult() != 'correct') continue;
            $misc['total_accepted']++;
            $team_stats['total_accepted']++;
            AnalysisController::set_or_increment($team_stats['problems_accepted'], $s->getProbId());
            AnalysisController::set_or_increment($misc['problem_solutions'], $s->getProbId());
            $misc['problem_stats']['teams_solved'][$s->getProbId()][$team->getTeamId()] = $team->getTeamId();

            $misc['language_stats']['teams_solved'][$s->getLangid()][$team->getTeamId()] = $team->getTeamId();
            AnalysisController::set_or_increment($misc['language_stats']['total_solutions'], $s->getLangid());

            if ($lastSubmission == null || $s->getSubmitTime() > $lastSubmission->getSubmitTime()) {
              $lastSubmission = $s;
            }
          }
          $misc['team_stats'][$team->getTeamId()] = $team_stats;
          AnalysisController::set_or_increment($misc['team_attempted_n_problems'], count($team_stats['problems_submitted']));
          AnalysisController::set_or_increment($misc['teams_solved_n_problems'], $team_stats['total_accepted']);

          // Calculate how long it has been since their last submission
          if ($lastSubmission != null) {
            $misery_seconds = min(
              $contest->getEndTime() - $lastSubmission->getSubmitTime(),
              $now - $lastSubmission->getSubmitTime()
            );
          } else {
            $misery_seconds = $contest->getEndTime() - $contest->getStartTime();
          }
          $misery_minutes = ($misery_seconds / 60) * 3;

          $misc['team_stats'][$team->getTeamId()]['misery_index'] = $misery_minutes;
          $total_misery_minutes += $misery_minutes;
        }
        $misc['misery_index'] = count($teams) > 0 ? $total_misery_minutes/count($teams) : 0;
        usort($submissions, function($a, $b){
          if ($a->getSubmitTime() == $b->getSubmitTime()) {
            return 0;
          }
          return ($a->getSubmitTime() < $b->getSubmitTime()) ? -1: 1;
        });

        $maxDelayedJudgings = 10;
        $delayedTimeDiff = 5;
        $delayedJudgings = $em->createQueryBuilder()
            ->from(Submission::class, 's')
            ->innerJoin(Judging::class, 'j', Expr\Join::WITH, 's.submitid = j.submitid')
            ->select('s.submitid, MIN(j.judgingid) AS judgingid, s.submittime, MIN(j.starttime) - s.submittime AS timediff, COUNT(j.judgingid) AS num_judgings')
            ->andWhere('s.contest = :contest')
            ->setParameter('contest', $contest)
            ->groupBy('s.submitid')
            ->andHaving('timediff > :timediff')
            ->setParameter('timediff', $delayedTimeDiff)
            ->orderBy('timediff', 'DESC')
            ->getQuery()->getResult();

        return $this->render('jury/analysis/contest_overview.html.twig', [
            'contest' => $contest,
            'problems' => $problems,
            'teams' => $teams,
            'submissions' => $submissions,
            'delayed_judgings' => [
                'data' => array_slice($delayedJudgings, 0, $maxDelayedJudgings),
                'overflow' => count($delayedJudgings) - $maxDelayedJudgings,
                'delay' => $delayedTimeDiff,
            ],
            'misc' => $misc,
            'filters' => self::FILTERS,
            'view' => $view,
        ]);
    }
    /**
     * @Route("/team/{teamid}", name="analysis_team")
     */
    public function teamAction(Request $request, Team $team)
    {
        $em = $this->getDoctrine()->getManager();
        $contest = $this->dj->getCurrentContest();

        if ($contest == null) {
          return $this->render('jury/error.html.twig', [
              'error' => 'No contest selected',
          ]);
        }

        // Get a whole bunch of judgings(and related objects)
        // Where:
        //   - The judging is valid(NOT - for team pages it might be neat to see rejudgings/etc)
        //   - The judging submission is part of the selected contest
        //   - The judging submission matches the problem we're analyzing
        //   - The submission was made by a team in a visible category
        $judgings = $em->createQueryBuilder()
          ->select('j, jr','s','team', 'partial p.{timelimit,name,probid}')
          ->from(Judging::class, 'j')
          ->join('j.submission', 's')
          ->join('s.problem', 'p')
          ->join('j.runs', 'jr')
          ->join('s.team', 'team')
          ->join('team.category', 'tc')
          // ->andWhere('j.valid = true')
          ->andWhere('s.contest = :contest')
          ->andWhere('s.team = :team')
          // ->andWhere('tc.visible = true')
          ->setParameter('team', $team)
          ->setParameter('contest', $contest)
          ->getQuery()->getResult();
        ;

        // Create a summary of the results(how many correct, timelimit, wrong-answer, etc)
        $results = array();
        foreach($judgings as $j) {
          if (!$j->getValid()) continue;
          if ($j->getResult()) {
            AnalysisController::set_or_increment($results, $j->getResult() ?? 'pending');
          }
        }
        // Sort the judgings by runtime
        usort($judgings, function($a, $b) {
          if ($a->getMaxRuntime() == $b->getMaxRuntime()) {
            return 0;
          }
          return $a->getMaxRuntime() < $b->getMaxRuntime() ? -1 : 1;
        });

        // Go through the judgings we found, and get the submissions
        $submissions = [];
        $problems = [];
        foreach($judgings as $j) {
          if (!$j->getValid()) continue;

          $s = $j->getSubmission();
          $submissions[] = $s;
          if (!in_array($s->getProblem(), $problems)) {
            $problems[] = $s->getProblem();
          }

        }
        usort($submissions, function($a, $b){
          if ($a->getSubmitTime() == $b->getSubmitTime()) {
            return 0;
          }
          return ($a->getSubmitTime() < $b->getSubmitTime()) ? -1: 1;
        });
        usort($problems, function($a, $b){
          if ($a->getName() == $b->getName()) {
            return 0;
          }
          return ($a->getName() < $b->getName()) ? -1: 1;
        });
        usort($judgings, function($a, $b){
          if ($a->getJudgingid() == $b->getJudgingid()) {
            return 0;
          }
          return ($a->getJudgingid() < $b->getJudgingid()) ? -1: 1;
        });


        $misc = array();
        $misc['correct_percentage'] = array_key_exists('correct', $results) ? ($results['correct'] / count($judgings) )* 100.0 : 0;

        return $this->render('jury/analysis/team.html.twig', [
            'contest' => $contest,
            'team' => $team,
            'submissions' => $submissions,
            'problems' => $problems,
            'judgings' => $judgings,
            'results' => $results,
            'misc' => $misc,
        ]);
    }
    /**
     * @Route("/problem/{probid}", name="analysis_problem")
     */
    public function problemAction(Request $request, Problem $problem)
    {
        $em = $this->getDoctrine()->getManager();
        $contest = $this->dj->getCurrentContest();

        $filterKeys = array_keys(self::FILTERS);
        $view = $request->query->get('view') ?: reset($filterKeys);

        // Get a whole bunch of judgings(and related objects)
        // Where:
        //   - The judging is valid
        //   - The judging submission is part of the selected contest
        //   - The judging submission matches the problem we're analyzing
        //   - The submission was made by a team in a visible category
        $judgings = $this->applyFilter($em->createQueryBuilder()
                                           ->select('j, jr', 's', 'team', 'sj')
                                           ->from(Judging::class, 'j')
                                           ->join('j.submission', 's')
                                           ->join('s.problem', 'p')
                                           ->join('s.judgings', 'sj')
                                           ->join('j.runs', 'jr')
                                           ->join('s.team', 'team')
                                           ->join('team.category', 'tc')
                                           ->andWhere('j.valid = true')
                                           ->andWhere('j.result IS NOT NULL')
                                           ->andWhere('s.contest = :contest')
                                           ->andWhere('s.problem = :problem'), $view)
            ->setParameter('problem', $problem)
            ->setParameter('contest', $contest)
            ->getQuery()->getResult();

        // Create a summary of the results(how many correct, timelimit, wrong-answer, etc)
        $results = array();
        foreach($judgings as $j) {
            AnalysisController::set_or_increment($results, $j->getResult() ?? 'pending');
        }

        // Sort the judgings by runtime
        usort($judgings, function($a, $b) {
          if ($a->getMaxRuntime() == $b->getMaxRuntime()) {
            return 0;
          }
          return $a->getMaxRuntime() < $b->getMaxRuntime() ? -1 : 1;
        });

        // Go through the judgings we found, and get the submissions
        $submissions = [];
        foreach($judgings as $j) {
          $submissions[] = $j->getSubmission();
        }
        usort($submissions, function($a, $b){
          if ($a->getSubmitTime() == $b->getSubmitTime()) {
            return 0;
          }
          return ($a->getSubmitTime() < $b->getSubmitTime()) ? -1: 1;
        });


        $misc = array();
        $teams_correct = [];
        $teams_attempted = [];
        foreach($judgings as $judging) {
          $s = $judging->getSubmission();
          $teams_attempted[$s->getTeamID()] = $s->getTeamID();
          if ($judging->getResult() == 'correct') {
            $teams_correct[$s->getTeamID()] = $s->getTeamID();
          }
        }
        $misc['num_teams_attempted'] = count($teams_attempted);
        $misc['num_teams_correct'] = count($teams_correct);
        $misc['correct_percentage'] = array_key_exists('correct', $results) ? ($results['correct'] / count($judgings) )* 100.0 : 0;
        $misc['teams_correct_percentage'] = count($teams_attempted) > 0 ? (count($teams_correct) / count($teams_attempted) )* 100.0 : 0;

        return $this->render('jury/analysis/problem.html.twig', [
            'contest' => $contest,
            'problem' => $problem,
            'timelimit' => $problem->getTimelimit(),
            'submissions' => $submissions,
            'judgings' => $judgings,
            'results' => $results,
            'misc' => $misc,
            'filters' => self::FILTERS,
            'view' => $view,
        ]);
    }
}
