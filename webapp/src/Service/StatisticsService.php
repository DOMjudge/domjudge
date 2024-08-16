<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\Judging;
use App\Entity\Language;
use App\Entity\Problem;
use App\Entity\Submission;
use App\Entity\Team;
use App\Utils\Utils;
use DateInterval;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

class StatisticsService
{
    final public const NUM_GROUPED_BINS = 20;

    final public const FILTERS = [
        'visiblecat' => 'Teams from visible categories',
        'hiddencat' => 'Teams from hidden categories',
        'all' => 'All teams',
    ];

    public function __construct(private readonly EntityManagerInterface $em)
    {
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
                ->join('ts.language', 'l')
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
                ->join('ts.language', 'l')
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
     *
     * @return array{total_submissions: int, total_accepted: int, num_teams: int,
     *               problem_num_testcases: int[], team_num_submissions: int[],
     *               team_attempted_n_problems: int[], teams_solved_n_problems: int[],
     *               problem_attempts: int[], problem_solutions: int[],
     *               problem_stats: array{teams_attempted: array<int[]>, teams_solved: array<int[]>},
     *               submissions: Submission[], misery_index: float,
     *               team_stats: array<array{total_submitted: int, total_accepted: int,
     *                                       problems_submitted: int[], problems_accepted: int[]}>,
     *               language_stats: array{total_submissions: array<string, int>, total_solutions: array<string, int>,
     *                                     teams_attempted: array<string, int[]>, teams_solved: array<string, int[]>}}
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
        usort($submissions, static fn($a, $b) => $a->getSubmitTime() <=> $b->getSubmitTime());

        $misc['submissions'] = $submissions;

        return $misc;
    }

    /**
     * Get the team statistics for the given team.
     *
     * @return array{contest: Contest, team: Team, submissions: Submission[],
     *               problems: Problem[], judgings: Judging[], misc: array{correct_percentage: float},
     *               results: array{no-output: int, compiler-error: int, wrong-answer: int, correct: int,
     *                              run-error: int, timelimit: int, output-limit: int}}
     */
    public function getTeamStats(Contest $contest, Team $team): array
    {
        // Get a whole bunch of judgings (and related objects).
        // Where:
        //   - The judging is valid(NOT - for team pages it might be neat to see rejudgings/etc)
        //   - The judging submission is part of the selected contest
        //   - The judging submission matches the problem we're analyzing
        //   - The submission was made by a team in a visible category
        /** @var Judging[] $judgings */
        $judgings = $this->em->createQueryBuilder()
            ->select('j, jr', 's', 'team', 'p')
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
                static::setOrIncrement($results, $j->getResult());
            }
        }
        // Sort the judgings by runtime.
        usort($judgings, static fn(Judging $a, Judging $b) => $a->getMaxRuntime() <=> $b->getMaxRuntime());

        // Go through the judgings we found, and get the submissions.
        /** @var Submission[] $submissions */
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
        usort($submissions, static fn(Submission $a, Submission $b) => $a->getSubmitTime() <=> $b->getSubmitTime());
        usort($problems, static fn(Problem $a, Problem $b) => $a->getName() <=> $b->getName());
        usort($judgings, static fn(Judging $a, Judging $b) => $a->getJudgingid() <=> $b->getJudgingid());

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

    /**
     * @return array{contest: Contest, contest_problem: ContestProblem, problem: Problem,
     *               timelimit: float, submissions: Submission[], judgings: Judging[],
     *               filters: array<string, string>, view: string,
     *               misc: array{num_teams_attempted: int, num_teams_correct: int,
     *                           correct_percentage: int, teams_correct_percentage: int},
     *               results: array{no-output: int, compiler-error: int, wrong-answer: int, correct: int,
     *                              run-error: int, timelimit: int, output-limit: int}}
     */
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
        usort($judgings, static fn($a, $b) => $a->getMaxRuntime() <=> $b->getMaxRuntime());

        // Go through the judgings we found, and get the submissions.
        $submissions = [];
        foreach ($judgings as $j) {
            $submissions[] = $j->getSubmission();
        }
        usort($submissions, static fn($a, $b) => $a->getSubmitTime() <=> $b->getSubmitTime());

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

        $contestProblem = $this->em->getRepository(ContestProblem::class)->findOneBy([
            'contest' => $contest,
            'problem' => $problem,
        ]);

        return [
            'contest' => $contest,
            'contest_problem' => $contestProblem,
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
     * @return array{'numBuckets': int, 'maxBucketSizeCorrect': int, 'maxBucketSizeCorrect': int, 'maxBucketSizeIncorrect': int,
     *               'problems': array<array{'correct': array<array{'start': DateTime, 'end': DateTime, 'count': int}>,
     *                                       'incorrect': array<array{'start': DateTime, 'end': DateTime, 'count': int}>}>}
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
     * @return array{
     *     contest: Contest,
     *     problems: ContestProblem[],
     *     filters: array<string, string>,
     *     view: string,
     *     languages: array<string, array{
     *          name: string,
     *          teams: array<array{
     *              team: Team,
     *              solved: int,
     *              total: int,
     *          }>,
     *          team_count: int,
     *          solved: int,
     *          not_solved: int,
     *          total: int,
     *          problems_solved: array<int, ContestProblem>,
     *          problems_solved_count: int,
     *          problems_attempted: array<int, ContestProblem>,
     *          problems_attempted_count: int,
     *     }>
     * }
     */
    public function getLanguagesStats(Contest $contest, string $view): array
    {
        /** @var Language[] $languages */
        $languages = $this->em->getRepository(Language::class)
            ->createQueryBuilder('l')
            ->andWhere('l.allowSubmit = 1')
            ->orderBy('l.name')
            ->getQuery()
            ->getResult();

        $languageStats = [];

        foreach ($languages as $language) {
            $languageStats[$language->getLangid()] = [
                'name' => $language->getName(),
                'teams' => [],
                'team_count' => 0,
                'solved' => 0,
                'not_solved' => 0,
                'total' => 0,
                'problems_solved' => [],
                'problems_solved_count' => 0,
                'problems_attempted' => [],
                'problems_attempted_count' => 0,
            ];
        }

        $teams = $this->getTeams($contest, $view);
        foreach ($teams as $team) {
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
                if ($s->getSubmittime() > $contest->getFreezetime()) {
                    continue;
                }

                $language = $s->getLanguage();

                if (!isset($languageStats[$language->getLangid()]['teams'][$team->getTeamid()])) {
                    $languageStats[$language->getLangid()]['teams'][$team->getTeamid()] = [
                        'team' => $team,
                        'solved' => 0,
                        'total' => 0,
                    ];
                }
                $languageStats[$language->getLangid()]['teams'][$team->getTeamid()]['total']++;
                $languageStats[$language->getLangid()]['total']++;
                if ($s->getResult() === 'correct') {
                    $languageStats[$language->getLangid()]['solved']++;
                    $languageStats[$language->getLangid()]['teams'][$team->getTeamid()]['solved']++;
                    $languageStats[$language->getLangid()]['problems_solved'][$s->getProblem()->getProbId()] = $s->getContestProblem();
                } else {
                    $languageStats[$language->getLangid()]['not_solved']++;
                }
                $languageStats[$language->getLangid()]['problems_attempted'][$s->getProblem()->getProbId()] = $s->getContestProblem();
            }
        }

        foreach ($languageStats as &$languageStat) {
            usort($languageStat['teams'], static function (array $a, array $b): int {
                if ($a['solved'] === $b['solved']) {
                    return $b['total'] <=> $a['total'];
                }

                return $b['solved'] <=> $a['solved'];
            });
            $languageStat['team_count'] = count($languageStat['teams']);
            $languageStat['problems_solved_count'] = count($languageStat['problems_solved']);
            $languageStat['problems_attempted_count'] = count($languageStat['problems_attempted']);
        }
        unset($languageStat);

        return [
            'contest' => $contest,
            'problems' => $this->getContestProblems($contest),
            'filters' => StatisticsService::FILTERS,
            'view' => $view,
            'languages' => $languageStats,
        ];
    }

    /**
     * Apply the filter to the given query builder.
     */
    protected function applyFilter(QueryBuilder $queryBuilder, string $filter): QueryBuilder
    {
        return match ($filter) {
            'visiblecat' => $queryBuilder->andWhere('tc.visible = true'),
            'hiddencat' => $queryBuilder->andWhere('tc.visible = false'),
            default => $queryBuilder,
        };
    }

    /**
     * @param array<string, int> $array
     */
    protected static function setOrIncrement(array &$array, int|string $index): void
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
     * @return int[]
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
