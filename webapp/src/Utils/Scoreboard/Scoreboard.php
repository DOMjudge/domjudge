<?php declare(strict_types=1);

namespace App\Utils\Scoreboard;

use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\ScoreCache;
use App\Entity\Team;
use App\Entity\TeamCategory;
use App\Utils\FreezeData;
use App\Utils\Utils;
use Collator;
use Exception;

class Scoreboard
{
    protected readonly bool $restricted;

    /** @var ScoreboardMatrixItem[][] */
    protected array $matrix = [];
    protected Summary $summary;

    /** @var TeamScore[] */
    protected array $scores = [];
    /** @var int[]|null */
    protected ?array $bestInCategoryData = null;

    /**
     * @param Team[]           $teams
     * @param TeamCategory[]   $categories
     * @param ContestProblem[] $problems
     * @param ScoreCache[]     $scoreCache
     */
    public function __construct(
        protected readonly Contest $contest,
        protected readonly array $teams,
        protected readonly array $categories,
        protected readonly array $problems,
        protected readonly array $scoreCache,
        protected readonly FreezeData $freezeData,
        bool $jury,
        protected readonly int $penaltyTime,
        protected readonly bool $scoreIsInSeconds
    ) {
        $this->restricted = $jury || $freezeData->showFinal($jury);

        $this->initializeScoreboard();
        $this->calculateScoreboard();
    }

    /**
     * @return bool Whether this Scoreboard has restricted access (either a jury member can see, or after unfreeze).
     */
    public function hasRestrictedAccess(): bool
    {
        return $this->restricted;
    }

    /**
     * @return Team[]
     */
    public function getTeams(): array
    {
        return $this->teams;
    }

    /**
     * @return TeamCategory[]
     */
    public function getCategories(): array
    {
        return $this->categories;
    }

    /**
     * @return ContestProblem[]
     */
    public function getProblems(): array
    {
        return $this->problems;
    }

    /**
     * @return ScoreboardMatrixItem[][]
     */
    public function getMatrix(): array
    {
        return $this->matrix;
    }

    public function getSummary(): Summary
    {
        return $this->summary;
    }

    /**
     * @return TeamScore[]
     */
    public function getScores(): array
    {
        return $this->scores;
    }

    public function getFreezeData(): FreezeData
    {
        return $this->freezeData;
    }

    /**
     * Get the progress of this scoreboard
     */
    public function getProgress(): int
    {
        return $this->getFreezeData()->getProgress();
    }

    /**
     * Initialize the scoreboard data
     */
    protected function initializeScoreboard(): void
    {
        // Initialize summary
        $this->summary = new Summary($this->problems);

        // Initialize scores
        $this->scores = [];
        foreach ($this->teams as $team) {
            $this->scores[$team->getTeamid()] = new TeamScore($team);
        }
    }

    /**
     * Calculate the scoreboard data, filling the summary, matrix and scores properties.
     */
    protected function calculateScoreboard(): void
    {
        // Calculate matrix and update scores.
        $this->matrix = [];
        foreach ($this->scoreCache as $scoreCell) {
            $teamId = $scoreCell->getTeam()->getTeamid();
            $probId = $scoreCell->getProblem()->getProbid();
            // Skip this row if the team or problem is not known by us.
            if (!array_key_exists($teamId, $this->teams) ||
                !array_key_exists($probId, $this->problems)) {
                continue;
            }

            $penalty = Utils::calcPenaltyTime(
                $scoreCell->getIsCorrect($this->restricted),
                $scoreCell->getSubmissions($this->restricted),
                $this->penaltyTime, $this->scoreIsInSeconds
            );

            $this->matrix[$teamId][$probId] = new ScoreboardMatrixItem(
                isCorrect: $scoreCell->getIsCorrect($this->restricted),
                isFirst: $scoreCell->getIsCorrect($this->restricted) && $scoreCell->getIsFirstToSolve(),
                numSubmissions: $scoreCell->getSubmissions($this->restricted),
                numSubmissionsPending: $scoreCell->getPending($this->restricted),
                time: $scoreCell->getSolveTime($this->restricted),
                penaltyTime: $penalty,
                runtime: $scoreCell->getRuntime($this->restricted),
                numSubmissionsInFreeze: $scoreCell->getPending(false),
            );

            if ($scoreCell->getIsCorrect($this->restricted)) {
                $solveTime      = Utils::scoretime($scoreCell->getSolveTime($this->restricted),
                                                   $this->scoreIsInSeconds);
                $contestProblem = $this->problems[$scoreCell->getProblem()->getProbid()];
                $this->scores[$teamId]->numPoints += $contestProblem->getPoints();
                $this->scores[$teamId]->solveTimes[] = $solveTime;
                $this->scores[$teamId]->totalTime += $solveTime + $penalty;
                $this->scores[$teamId]->totalRuntime += $scoreCell->getRuntime($this->restricted);
            }
        }

        // Now sort the scores using the scoreboard sort function.
        uasort($this->scores, $this->scoreboardCompare(...));

        // Loop over all teams to calculate ranks and totals.
        $prevSortOrder  = -1;
        $rank           = 0;
        $previousTeamId = null;
        foreach ($this->scores as $teamScore) {
            $teamId = $teamScore->team->getTeamid();
            $teamSortOrder = $teamScore->team->getCategory()->getSortorder();
            // rank, team name, total correct, total time
            if ($teamSortOrder != $prevSortOrder) {
                $prevSortOrder  = $teamSortOrder;
                $rank           = 0; // reset team position on switch to different category
                $previousTeamId = null;
            }
            $rank++;

            // Use previous team rank when scores are equal.
            if (isset($previousTeamId) &&
                $this->scoreCompare($this->scores[$previousTeamId], $teamScore) == 0) {
                $teamScore->rank = $this->scores[$previousTeamId]->rank;
            } else {
                $teamScore->rank = $rank;
            }
            $previousTeamId = $teamId;

            // Keep summary statistics for the bottom row of our table.
            // The numberOfPoints summary is useful only if they're all 1-point problems.
            $sortOrder = $teamScore->team->getCategory()->getSortorder();
            $this->summary->addNumberOfPoints($sortOrder, $teamScore->numPoints);
            $teamAffiliation = $teamScore->team->getAffiliation();
            if ($teamAffiliation) {
                $this->summary->incrementAffiliationValue($teamAffiliation->getAffilid());
                if ($teamAffiliation->getCountry()) {
                    $this->summary->incrementCountryValue($teamAffiliation->getCountry());
                }
            }

            // Loop over the problems
            foreach ($this->problems as $contestProblem) {
                $problemId = $contestProblem->getProbid();
                // Provide default scores when nothing submitted for this team + problem yet
                if (!isset($this->matrix[$teamId][$problemId])) {
                    $this->matrix[$teamId][$problemId] = new ScoreboardMatrixItem(
                        isCorrect: false,
                        isFirst: false,
                        numSubmissions: 0,
                        numSubmissionsPending: 0,
                        time: 0,
                        penaltyTime: 0,
                        runtime: 0);
                }

                $problemMatrixItem = $this->matrix[$teamId][$problemId];
                $problemSummary    = $this->summary->getProblem($problemId);

                $problemSummary->addSubmissionCounts(
                    $sortOrder,
                    $problemMatrixItem->numSubmissions ?? 0,
                    $problemMatrixItem->numSubmissionsPending ?? 0,
                    $problemMatrixItem->isCorrect ? 1 : 0
                );
                if ($problemMatrixItem->isFirst) {
                    $problemSummary->updateBestTime($sortOrder, $problemMatrixItem->time);
                }
                // aggregate minimum runtime of correct submissions for each problem
                if ($problemMatrixItem->isCorrect) {
                    $problemSummary->updateBestRuntime($sortOrder, $problemMatrixItem->runtime);
                }
            }
        }
    }

    /**
     * Scoreboard sorting function. It uses the following
     * criteria:
     * - First, use the sortorder override from the team_category table
     *   (e.g. score regular contestants always over spectators);
     * - Then, use the scoreCompare function to determine the actual ordering
     *   based on number of problems solved and the time it took;
     * - If still equal, order on team name alphabetically.
     */
    protected function scoreboardCompare(TeamScore $a, TeamScore $b): int
    {
        // First order by our predefined sortorder based on category.
        $a_sortorder = $a->team->getCategory()->getSortorder();
        $b_sortorder = $b->team->getCategory()->getSortorder();
        if ($a_sortorder != $b_sortorder) {
            return $a_sortorder <=> $b_sortorder;
        }

        // Then compare scores.
        $scoreCompare = $this->scoreCompare($a, $b);
        if ($scoreCompare != 0) {
            return $scoreCompare;
        }

        // Else, order by teamname alphabetically.
        if ($a->team->getEffectiveName() != $b->team->getEffectiveName()) {
            $collator = new Collator('en');
            return $collator->compare($a->team->getEffectiveName(), $b->team->getEffectiveName());
        }
        // Undecided, should never happen in practice.
        return 0;
    }

    /**
     * Main score comparison function, called from the 'scoreboardCompare' wrapper
     * above. Scores based on the following criteria:
     * - highest points from correct solutions;
     * - least amount of total time spent on these solutions; (or lowest total runtime)
     * - the tie-breaker function below.
     */
    protected function scoreCompare(TeamScore $a, TeamScore $b): int
    {
        // More correctness points than someone else means higher rank.
        if ($a->numPoints != $b->numPoints) {
            return $b->numPoints <=> $a->numPoints;
        }
        // Else, less time spent means higher rank.
        if ($this->getRuntimeAsScoreTiebreaker()) { // runtime ordering
            if ($a->totalRuntime != $b->totalRuntime) {
                return $a->totalRuntime <=> $b->totalRuntime;
            }
        } else { // solvetime ordering
            if ($a->totalTime != $b->totalTime) {
                return $a->totalTime <=> $b->totalTime;
            }
        }
        // Else tie-breaker rule.
        return static::scoreTiebreaker($a, $b);
    }

    /**
     * Tie-breaker comparison function, called from the 'scoreCompare' function
     * above. Scores based on the following criterion:
     * - fastest submission time for latest correct problem
     */
    public static function scoreTiebreaker(TeamScore $a, TeamScore $b): int
    {
        $atimes = $a->solveTimes;
        $btimes = $b->solveTimes;
        rsort($atimes);
        rsort($btimes);

        if (isset($atimes[0]) && isset($btimes[0])) {
            return $atimes[0] <=> $btimes[0];
        }
        if (!isset($atimes[0]) && !isset($btimes[0])) {
            return 0;
        }
        if (!isset($atimes[0])) {
            return -1;
        }
        if (!isset($btimes[0])) {
            return 1;
        }

        throw new Exception('Unhandled tie breaker case.');
    }

    /**
     * Return whether to show points for this scoreboard.
     */
    public function showPoints(): bool
    {
        foreach ($this->problems as $problem) {
            if ($problem->getPoints() != 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return the used team categories for this scoreboard.
     *
     * @param int[] $limitToTeamIds
     * @return TeamCategory[]
     */
    public function getUsedCategories(?array $limitToTeamIds = null): array
    {
        $usedCategories = [];
        foreach ($this->scores as $score) {
            // Skip if we have limitteams and the team is not listed.
            if (!empty($limitToTeamIds) &&
                !in_array($score->team->getTeamid(), $limitToTeamIds)) {
                continue;
            }

            $category = $score->team->getCategory();
            if ($category) {
                $usedCategories[$category->getCategoryid()] = $category;
            }
        }

        return $usedCategories;
    }

    /**
     * Return whether this scoreboard has multiple category colors.
     *
     * @param int[] $limitToTeamIds
     */
    public function hasCategoryColors(?array $limitToTeamIds = null): bool
    {
        $colors = [];
        foreach ($this->scores as $score) {
            // Skip if we have limitteams and the team is not listed.
            if (!empty($limitToTeamIds) &&
                !in_array($score->team->getTeamid(), $limitToTeamIds)) {
                continue;
            }

            if ($score->team->getCategory() &&
                $score->team->getCategory()->getColor()) {
                $colors[$score->team->getCategory()->getColor()] = 1;
            } else {
                $colors['transparent'] = 1;
            }
        }

        return count($colors) > 1;
    }

    /**
     * Determine whether this team is the best in the given category
     *
     * @param int[] $limitToTeamIds
     */
    public function isBestInCategory(Team $team, ?array $limitToTeamIds = null): bool
    {
        if ($this->bestInCategoryData === null) {
            $this->bestInCategoryData = [];
            foreach ($this->scores as $score) {
                // Skip if we have limitteams and the team is not listed.
                if (!empty($limitToTeamIds) &&
                    !in_array($score->team->getTeamid(), $limitToTeamIds)) {
                    continue;
                }

                $categoryId = $score->team->getCategory()->getCategoryid();
                if (!isset($this->bestInCategoryData[$categoryId])) {
                    $this->bestInCategoryData[$categoryId] = $score->team->getTeamid();
                }
            }
        }

        $categoryId = $team->getCategory()->getCategoryid();
        // Only check the scores when the team has points.
        if ($this->scores[$team->getTeamid()]->numPoints > 0) {
            // If the rank of this team is equal to the best team for this
            // category, this team is best in that category.
            return $this->scores[$this->bestInCategoryData[$categoryId]]->rank ===
                $this->scores[$team->getTeamid()]->rank;
        }

        return false;
    }

    /**
     * Determine whether this team was the first team to solve this problem.
     */
    public function solvedFirst(Team $team, ContestProblem $problem): bool
    {
        return $this->matrix[$team->getTeamid()][$problem->getProbid()]->isFirst;
    }


    /**
     * Determine whether this team has the fastest correct implementation for this problem
     */
    public function isFastestSubmission(Team $team, ContestProblem $problem): bool
    {
        $item = $this->matrix[$team->getTeamid()][$problem->getProbid()];
        if (!$item->isCorrect) {
            return false;
        }
        $sortorder = $team->getCategory()->getSortorder();
        $bestTime = $this->summary->getProblem($problem->getProbid())->getBestRuntime($sortorder);
        return $item->runtime == $bestTime;
    }

    /**
     * Determine whether to order by runtime instead of solvetime
     * @return bool
     */
    public function getRuntimeAsScoreTiebreaker(): bool
    {
        return $this->contest->getRuntimeAsScoreTiebreaker();
    }
}
