<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\Judging;
use App\Entity\Problem;
use App\Entity\Submission;
use App\Entity\Team;
use App\Utils\Utils;
use DateInterval;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

/**
 * Class StatisticsService
 *
 * Service to display statistics data.
 *
 * @package App\Service
 */
class StatisticsService
{
    const NUM_GROUPED_BINS = 20;

    const FILTERS = [
        'visiblecat' => 'Teams from visible categories',
        'hiddencat' => 'Teams from hidden categories',
        'all' => 'All teams',
    ];

    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * Get the problems for the given contest
     *
     * @return ContestProblem[]
     */
    public function getContestProblems(Contest $contest): array
    {
        return $this->em->createQueryBuilder()
            ->select('cp', 'p')
            ->from(ContestProblem::class, 'cp')
            ->join('cp.problem', 'p')
            ->andWhere('cp.contest = :contest')
            ->setParameter('contest', $contest)
            ->getQuery()->getResult();
    }

    /**
     * Get the teams for the given contest and view filter.
     *
     * @return Team[]
     */
    public function getTeams(Contest $contest, string $filter): array
    {
        if ($contest->isOpenToAllTeams()) {
            return $this->applyFilter($this->em->createQueryBuilder()
                ->select('t', 'ts', 'j', 'lang', 'a')
                ->from(Team::class, 't')
                ->join('t.category', 'tc')
                ->leftJoin('t.affiliation', 'a')
                ->join('t.submissions', 'ts')
                ->join('ts.judgings', 'j')
                ->andWhere('j.valid = true')
                ->join('ts.language', 'lang')
                ->orderBy('t.teamid'), $filter)
                ->getQuery()->getResult();
        } else {
            return $this->applyFilter($this->em->createQueryBuilder()
                ->select('t', 'c', 'ts', 'j', 'lang', 'a')
                ->from(Team::class, 't')
                ->leftJoin('t.contests', 'c')
                ->leftJoin('t.affiliation', 'a')
                ->join('t.category', 'tc')
                ->leftJoin('tc.contests', 'cc')
                ->join('t.submissions', 'ts')
                ->join('ts.judgings', 'j')
                ->andWhere('j.valid = true')
                ->join('ts.language', 'lang')
                ->andWhere('c = :contest OR cc = :contest'), $filter)
                ->orderBy('t.teamid')
                ->setParameter('contest', $contest)
                ->getQuery()->getResult();
        }
    }

    /**
     * Get miscellaneous contest statistics.
     *
     * @param Team[]  $teams
     * @param bool    $noFrozen             Do not show frozen data
     * @param bool    $verificationRequired Only show verified submissions
     */
    public function getMiscContestStatistics(
        Contest $contest,
        array $teams,
        string $filter,
        bool $noFrozen = false,
        bool $verificationRequired = false
    ): array {
        $numTestcases = $this->getNumTestcases($contest);
        $numSubmissions = $this->getTeamNumSubmissions($contest, $filter);

        // Come up with misc stats.
        // Find last correct judgement for a team, figure out how many minutes are left in the contest
        // (or til now if now is earlier).
        $now = (new DateTime())->getTimeStamp();
        $misc = [
            'total_submissions' => 0,
            'total_accepted' => 0,
            'num_teams' => count($teams),
            'problem_num_testcases' => $numTestcases,
            'team_num_submissions' => $numSubmissions,

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

        $totalMiseryMinutes = 0;
        $submissions = [];
        foreach ($teams as $team) {
            $lastSubmission = null;
            $teamStats = [
                'total_submitted' => 0,
                'total_accepted' => 0,
                'problems_submitted' => [],
                'problems_accepted' => [],
            ];
            /** @var Submission $s */
            foreach ($team->getSubmissions() as $s) {
                if ($s->getContest() != $contest) {
                    continue;
                }
                if ($s->getSubmitTime() > $contest->getEndTime()) {
                    continue;
                }
                if ($s->getSubmitTime() < $contest->getStartTime()) {
                    continue;
                }

                if ($noFrozen && $s->getSubmittime() > $contest->getFreezetime()) {
                    continue;
                }

                if ($verificationRequired && !$s->getJudgings()->first()->getVerified()) {
                    continue;
                }

                $submissions[] = $s;
                $misc['total_submissions']++;
                $teamStats['total_submitted']++;
                static::setOrIncrement($misc['problem_attempts'],
                    $s->getProblem()->getProbId());
                static::setOrIncrement($teamStats['problems_submitted'],
                    $s->getProblem()->getProbId());
                $misc['problem_stats']['teams_attempted'][$s->getProblem()->getProbId()][$team->getTeamId()] = $team->getTeamId();

                static::setOrIncrement($misc['language_stats']['total_submissions'],
                    $s->getLanguage()->getLangid());
                $misc['language_stats']['teams_attempted'][$s->getLanguage()->getLangid()][$team->getTeamId()] = $team->getTeamId();

                if ($s->getResult() != 'correct') {
                    continue;
                }
                $misc['total_accepted']++;
                $teamStats['total_accepted']++;
                static::setOrIncrement($teamStats['problems_accepted'],
                    $s->getProblem()->getProbId());
                static::setOrIncrement($misc['problem_solutions'],
                    $s->getProblem()->getProbId());
                $misc['problem_stats']['teams_solved'][$s->getProblem()->getProbId()][$team->getTeamId()] = $team->getTeamId();

                $misc['language_stats']['teams_solved'][$s->getLanguage()->getLangid()][$team->getTeamId()] = $team->getTeamId();
                static::setOrIncrement($misc['language_stats']['total_solutions'],
                    $s->getLanguage()->getLangid());

                if ($lastSubmission == null || $s->getSubmitTime() > $lastSubmission->getSubmitTime()) {
                    $lastSubmission = $s;
                }
            }
            $misc['team_stats'][$team->getTeamId()] = $teamStats;
            static::setOrIncrement($misc['team_attempted_n_problems'],
                count($teamStats['problems_submitted']));
            static::setOrIncrement($misc['teams_solved_n_problems'],
                $teamStats['total_accepted']);

            // Calculate how long it has been since their last submission.
            if ($lastSubmission != null) {
                $miserySeconds = min(
                    $contest->getEndTime() - $lastSubmission->getSubmitTime(),
                    $now - $lastSubmission->getSubmitTime()
                );
            } else {
                $miserySeconds = $contest->getEndTime() - $contest->getStartTime();
            }
            $miseryMinutes = ($miserySeconds / 60) * 3;

            $misc['team_stats'][$team->getTeamId()]['misery_index'] = $miseryMinutes;
            $totalMiseryMinutes += $miseryMinutes;
        }
        $misc['misery_index'] = count($teams) > 0 ? $totalMiseryMinutes / count($teams) : 0;
        usort($submissions, function ($a, $b) {
            if ($a->getSubmitTime() == $b->getSubmitTime()) {
                return 0;
            }
            return ($a->getSubmitTime() < $b->getSubmitTime()) ? -1 : 1;
        });

        $misc['submissions'] = $submissions;

        return $misc;
    }

    /**
     * Get the team statistics for the given team.
     */
    public function getTeamStats(Contest $contest, Team $team): array
    {
        // Get a whole bunch of judgings (and related objects).
        // Where:
        //   - The judging is valid(NOT - for team pages it might be neat to see rejudgings/etc)
        //   - The judging submission is part of the selected contest
        //   - The judging submission matches the problem we're analyzing
        //   - The submission was made by a team in a visible category
        $judgings = $this->em->createQueryBuilder()
            ->select('j, jr', 's', 'team', 'partial p.{timelimit,name,probid}')
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

        // Create a summary of the results (how many correct, timelimit, wrong-answer, etc).
        $results = [];
        foreach ($judgings as $j) {
            if (!$j->getValid()) {
                continue;
            }
            if ($j->getResult()) {
                static::setOrIncrement($results, $j->getResult() ?? 'pending');
            }
        }
        // Sort the judgings by runtime.
        usort($judgings, function ($a, $b) {
            if ($a->getMaxRuntime() == $b->getMaxRuntime()) {
                return 0;
            }
            return $a->getMaxRuntime() < $b->getMaxRuntime() ? -1 : 1;
        });

        // Go through the judgings we found, and get the submissions.
        $submissions = [];
        $problems = [];
        foreach ($judgings as $j) {
            if (!$j->getValid()) {
                continue;
            }

            $s = $j->getSubmission();
            $submissions[] = $s;
            if (!in_array($s->getProblem(), $problems)) {
                $problems[] = $s->getProblem();
            }
        }
        usort($submissions, function ($a, $b) {
            if ($a->getSubmitTime() == $b->getSubmitTime()) {
                return 0;
            }
            return ($a->getSubmitTime() < $b->getSubmitTime()) ? -1 : 1;
        });
        usort($problems, function ($a, $b) {
            if ($a->getName() == $b->getName()) {
                return 0;
            }
            return ($a->getName() < $b->getName()) ? -1 : 1;
        });
        usort($judgings, function ($a, $b) {
            if ($a->getJudgingid() == $b->getJudgingid()) {
                return 0;
            }
            return ($a->getJudgingid() < $b->getJudgingid()) ? -1 : 1;
        });

        $misc = [];
        $misc['correct_percentage'] = array_key_exists('correct',
            $results) ? ($results['correct'] / count($judgings)) * 100.0 : 0;

        return [
            'contest' => $contest,
            'team' => $team,
            'submissions' => $submissions,
            'problems' => $problems,
            'judgings' => $judgings,
            'results' => $results,
            'misc' => $misc,
        ];
    }

    public function getProblemStats(
        Contest $contest,
        Problem $problem,
        string $view
    ): array {
        // Get a whole bunch of judgings (and related objects).
        // Where:
        //   - The judging is valid
        //   - The judging submission is part of the selected contest
        //   - The judging submission matches the problem we're analyzing
        //   - The submission was made by a team in a visible category
        /** @var Judging[] $judgings */
        $judgings = $this->applyFilter($this->em->createQueryBuilder()
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

        // Create a summary of the results (how many correct, timelimit, wrong-answer, etc).
        $results = [];
        foreach ($judgings as $j) {
            static::setOrIncrement($results, $j->getResult() ?? 'pending');
        }

        // Sort the judgings by runtime.
        usort($judgings, function ($a, $b) {
            if ($a->getMaxRuntime() == $b->getMaxRuntime()) {
                return 0;
            }
            return $a->getMaxRuntime() < $b->getMaxRuntime() ? -1 : 1;
        });

        // Go through the judgings we found, and get the submissions.
        $submissions = [];
        foreach ($judgings as $j) {
            $submissions[] = $j->getSubmission();
        }
        usort($submissions, function ($a, $b) {
            if ($a->getSubmitTime() == $b->getSubmitTime()) {
                return 0;
            }
            return ($a->getSubmitTime() < $b->getSubmitTime()) ? -1 : 1;
        });

        $misc = [];
        $teamsCorrect = [];
        $teamsAttempted = [];
        foreach ($judgings as $judging) {
            $s = $judging->getSubmission();
            $teamsAttempted[$s->getTeam()->getTeamid()] = $s->getTeam()->getTeamid();
            if ($judging->getResult() == 'correct') {
                $teamsCorrect[$s->getTeam()->getTeamid()] = $s->getTeam()->getTeamid();
            }
        }
        $misc['num_teams_attempted'] = count($teamsAttempted);
        $misc['num_teams_correct'] = count($teamsCorrect);
        $misc['correct_percentage'] = array_key_exists('correct',
            $results) ? ($results['correct'] / count($judgings)) * 100.0 : 0;
        $misc['teams_correct_percentage'] = count($teamsAttempted) > 0 ? (count($teamsCorrect) / count($teamsAttempted)) * 100.0 : 0;

        return [
            'contest' => $contest,
            'problem' => $problem,
            'timelimit' => $problem->getTimelimit(),
            'submissions' => $submissions,
            'judgings' => $judgings,
            'results' => $results,
            'misc' => $misc,
            'filters' => StatisticsService::FILTERS,
            'view' => $view,
        ];
    }

    /**
     * @param Problem[] $problems
     */
    public function getGroupedProblemsStats(
        Contest $contest,
        array $problems,
        bool $showVerdictsInFreeze,
        bool $verificationRequired
    ): array {
        $stats = [
            'problems' => [],
            'numBuckets' => static::NUM_GROUPED_BINS,
        ];
        // Get a whole bunch of judgings (and related objects).
        // Where:
        //   - The judging is valid
        //   - The judging submission is part of the selected contest
        //   - The judging submission matches the problem we're analyzing
        //   - The submission was made by a team in a visible category
        $judgingsQueryBuilder = $this->em->createQueryBuilder()
            ->select('COUNT(j) AS count, p.probid')
            ->from(Judging::class, 'j')
            ->join('j.submission', 's')
            ->join('s.problem', 'p')
            ->join('s.team', 'team')
            ->join('team.category', 'tc')
            ->andWhere('j.valid = true')
            ->andWhere('j.result IS NOT NULL')
            ->andWhere('s.contest = :contest')
            ->andWhere('s.problem IN (:problems)')
            ->andWhere('tc.visible = true')
            ->setParameter('problems', $problems)
            ->setParameter('contest', $contest)
            ->groupBy('s.problem');

        if ($verificationRequired) {
            $judgingsQueryBuilder->andWhere('j.verified = true');
        }

        // Determine the bins to use.
        $duration = $contest->getEndtime() - $contest->getStarttime(false);
        $binDuration = round($duration / static::NUM_GROUPED_BINS, 0);

        for ($bin = 0; $bin < static::NUM_GROUPED_BINS; $bin++) {
            $start = new DateTime(Utils::absTime($contest->getStarttime(false) + $bin * $binDuration));
            $end = (clone $start)->add(new DateInterval(sprintf('PT%dS',
                $binDuration)));
            foreach ([true, false] as $correct) {
                $queryBuilder = clone $judgingsQueryBuilder;
                $queryBuilder->andWhere('s.submittime >= :starttime');
                $queryBuilder->andWhere('s.submittime < :endtime');
                if ($showVerdictsInFreeze || $end->getTimestamp() <= $contest->getFreezetime()) {
                    // When we don't want to show frozen correct/incorrect submissions,
                    // get the same data for both correct and incorrect.
                    // This logic assumes the freeze matches with the start of a bucket.
                    // If this is not the case, the whole bucket that contains the freeze
                    // will be showed as frozen.
                    if ($correct) {
                        $queryBuilder->andWhere('j.result = :correct');
                    } else {
                        $queryBuilder->andWhere('j.result != :correct');
                    }
                    $queryBuilder->setParameter('correct', 'correct');
                }
                $queryBuilder
                    ->setParameter('starttime', $start->getTimestamp())
                    ->setParameter('endtime', $end->getTimestamp());

                $statsIndex = $correct ? 'correct' : 'incorrect';

                $result = $queryBuilder->getQuery()->getArrayResult();
                foreach ($result as $resultItem) {
                    $stats['problems'][$resultItem['probid']][$statsIndex][$bin] = [
                        'start' => $start,
                        'end' => $end,
                        'count' => $resultItem['count'],
                    ];
                }

                foreach ($problems as $problem) {
                    if (!isset($stats['problems'][$problem->getProbid()][$statsIndex][$bin])) {
                        $stats['problems'][$problem->getProbid()][$statsIndex][$bin] = [
                            'start' => $start,
                            'end' => $end,
                            'count' => 0,
                        ];
                    }
                }
            }
        }

        $maxBucketSizeCorrect = 0;
        $maxBucketSizeIncorrect = 0;
        foreach ($stats['problems'] as $problemStats) {
            foreach ([true, false] as $correct) {
                $statsIndex = $correct ? 'correct' : 'incorrect';
                foreach ($problemStats[$statsIndex] as $statItem) {
                    if ($correct) {
                        $maxBucketSizeCorrect = max($maxBucketSizeCorrect, $statItem['count']);
                    } else {
                        $maxBucketSizeIncorrect = max($maxBucketSizeIncorrect, $statItem['count']);
                    }
                }
            }
        }

        $stats['maxBucketSizeCorrect'] = $maxBucketSizeCorrect;
        $stats['maxBucketSizeIncorrect'] = $maxBucketSizeIncorrect;

        return $stats;
    }

    /**
     * Apply the filter to the given query builder.
     */
    protected function applyFilter(QueryBuilder $queryBuilder, string $filter): QueryBuilder
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

    protected static function setOrIncrement(array &$array, $index): void
    {
        if (!array_key_exists($index, $array)) {
            $array[$index] = 0;
        }
        $array[$index]++;
    }

    /**
     * Get the number of testcases per problem.
     *
     * @return int[]
     */
    protected function getNumTestcases(Contest $contest): array
    {
        // Need to query directly the count, otherwise symfony memory explodes
        // I think because it tries to load the testdata if you do this the naive way.
        $results = $this->em->createQueryBuilder()
            ->select('p.probid, count(tc.testcaseid) as num_testcases')
            ->from(Contest::class, 'c')
            ->join('c.problems', 'cp')
            ->join('cp.problem', 'p')
            ->leftJoin('p.testcases', 'tc')
            ->andWhere('c = :contest')
            ->groupBy('p.probid')
            ->setParameter('contest', $contest)
            ->getQuery()->getResult();
        $numTestcases = [];
        foreach ($results as $r) {
            $numTestcases[$r['probid']] = $r['num_testcases'];
        }

        return $numTestcases;
    }

    /**
     * Get the number of submissions per team.
     *
     * @return array
     */
    protected function getTeamNumSubmissions(Contest $contest, string $filter): array
    {
        // Figure out how many submissions each team has.
        $results = $this->applyFilter($this->em->createQueryBuilder()
            ->select('t.teamid as teamid, count(t.teamid) as num_submissions')
            ->from(Submission::class, 's')
            ->join('s.team', 't')
            ->join('t.category', 'tc')
            ->andWhere('s.contest = :contest'), $filter)
            ->groupBy('t.teamid')
            ->setParameter('contest', $contest)
            ->getQuery()->getResult();
        $numSubmissions = [];
        foreach ($results as $r) {
            $numSubmissions[$r['teamid']] = $r['num_submissions'];
        }

        return $numSubmissions;
    }
}
