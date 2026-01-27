<?php declare(strict_types=1);

namespace App\Utils\Scoreboard;

use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\RankCache;
use App\Entity\ScoreboardType;
use App\Entity\ScoreCache;
use App\Entity\Team;
use App\Entity\TeamCategory;
use App\Utils\FreezeData;
use App\Utils\Utils;

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
     * @param Team[]           $teamsInDescendingOrder
     * @param TeamCategory[]   $categories
     * @param ContestProblem[] $problems
     * @param ScoreCache[]     $scoreCache
     * @param RankCache[]      $rankCache
     */
    public function __construct(
        protected readonly Contest    $contest,
        protected readonly array      $teamsInDescendingOrder,
        protected readonly array      $categories,
        protected readonly array      $problems,
        protected readonly array      $scoreCache,
        protected readonly array      $rankCache,
        protected readonly FreezeData $freezeData,
        bool                          $jury,
        protected readonly bool       $scoreIsInSeconds
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
    public function getTeamsInDescendingOrder(): array
    {
        return $this->teamsInDescendingOrder;
    }

    /**
     * @return TeamCategory[]
     */
    public function getCategories(): array
    {
        return $this->categories;
    }

    /**
     * @param int[]|null $limitToTeamIds
     *
     * @return list<TeamCategory>
     */
    public function getColorCategories(?array $limitToTeamIds = null): array
    {
        $categories = [];
        foreach ($this->scores as $score) {
            // Skip if we have limitteams and the team is not listed.
            if (!empty($limitToTeamIds) &&
                !in_array($score->team->getTeamid(), $limitToTeamIds)) {
                continue;
            }
            if ($category = $score->team->getBackgroundColorCategory()) {
                $categories[$category->getCategoryid()] = $category;
            }
        }
        return array_values($categories);
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

        $teamToRankCache = [];
        foreach ($this->rankCache as $rc) {
            $teamToRankCache[$rc->getTeam()->getTeamid()] = $rc;
        }

        // Initialize scores
        $this->scores = [];
        foreach ($this->teamsInDescendingOrder as $team) {
            $rankCacheForTeam = $teamToRankCache[$team->getTeamid()] ?? null;
            $this->scores[$team->getTeamid()] = new TeamScore($team, $rankCacheForTeam, $this->restricted);
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
            // Skip this cell if the team or problem is not known by us.
            if (!array_key_exists($teamId, $this->teamsInDescendingOrder) ||
                !array_key_exists($probId, $this->problems)) {
                continue;
            }
            $isCorrect = $scoreCell->getIsCorrect($this->restricted);

            $penalty = Utils::calcPenaltyTime(
                $isCorrect,
                $scoreCell->getSubmissions($this->restricted),
                $this->contest->getPenaltyTime(), $this->scoreIsInSeconds
            );

            $contestProblem = $scoreCell->getContest()->getContestProblem($scoreCell->getProblem());
            if ($scoreCell->getProblem()->isScoringProblem()) {
                $points = $scoreCell->getScore($this->restricted);
            } else {
                $points = strval(
                    $isCorrect ?
                        $contestProblem->getPoints() : 0
                );
            }

            $this->matrix[$teamId][$probId] = new ScoreboardMatrixItem(
                isCorrect: $isCorrect,
                isFirst: $isCorrect && $scoreCell->getIsFirstToSolve(),
                numSubmissions: $scoreCell->getSubmissions($this->restricted),
                numSubmissionsPending: $scoreCell->getPending($this->restricted),
                time: $scoreCell->getSolveTime($this->restricted),
                penaltyTime: $penalty,
                runtime: $scoreCell->getRuntime($this->restricted),
                numSubmissionsInFreeze: $scoreCell->getPending(false),
                points: $points,
            );
        }

        // Loop over all teams to calculate ranks and totals.
        $prevSortOrder  = -1;
        $rank           = 0;
        $previousTeamId = null;
        foreach ($this->scores as $teamScore) {
            $teamId = $teamScore->team->getTeamid();
            $teamSortOrder = $teamScore->team->getSortorder();
            // rank, team name, total correct, total time
            if ($teamSortOrder != $prevSortOrder) {
                $prevSortOrder  = $teamSortOrder;
                $rank           = 0; // reset team position on switch to different category
                $previousTeamId = null;
            }
            $rank++;

            // Use previous team rank when scores are equal.
            if (isset($previousTeamId) &&
                $this->scores[$previousTeamId]->getSortKey($this->restricted) === $teamScore->getSortKey($this->restricted)) {
                $teamScore->rank = $this->scores[$previousTeamId]->rank;
            } else {
                $teamScore->rank = $rank;
            }
            $previousTeamId = $teamId;

            // Keep summary statistics for the bottom row of our table.
            // The numberOfPoints summary is useful only if they're all 1-point problems.
            $sortOrder = $teamScore->team->getSortorder();
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

    public function isScoring(): bool
    {
        return $this->contest->getScoreboardType() === ScoreboardType::SCORE;
    }

    /**
     * Return whether this scoreboard has at least one non default category color.
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

            if ($score->team->getBackgroundColorCategory()?->getColor()) {
                $colors[$score->team->getBackgroundColorCategory()->getColor()] = 1;
            }
        }

        return count($colors) > 0;
    }

    /**
     * Determine whether this team is the best in the given category
     *
     * @param int[] $limitToTeamIds
     */
    public function isBestInCategory(Team $team, TeamCategory $category, ?array $limitToTeamIds = null): bool
    {
        if ($this->bestInCategoryData === null) {
            $this->bestInCategoryData = [];
            foreach ($this->scores as $score) {
                // Skip if we have limitteams and the team is not listed.
                if (!empty($limitToTeamIds) &&
                    !in_array($score->team->getTeamid(), $limitToTeamIds)) {
                    continue;
                }

                $categoryId = $score->team->getScoringCategory()->getCategoryid();
                if (!isset($this->bestInCategoryData[$categoryId])) {
                    $this->bestInCategoryData[$categoryId] = $score->team->getTeamid();
                }

                foreach ($score->team->getTopBadgeCategories() as $badgeCategory) {
                    $categoryId = $badgeCategory->getCategoryid();
                    if (!isset($this->bestInCategoryData[$categoryId])) {
                        $this->bestInCategoryData[$categoryId] = $score->team->getTeamid();
                    }
                }
            }
        }

        $categoryId = $category->getCategoryid();
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
        $sortorder = $team->getSortorder();
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

    /**
     * Get the maximum score achieved by any team for a given problem.
     * Returns 0 if no team has a positive score.
     */
    public function getMaxScoreForProblem(ContestProblem $problem): float
    {
        $maxScore = 0.0;
        $problemId = $problem->getProbid();

        foreach ($this->scores as $teamScore) {
            $teamId = $teamScore->team->getTeamid();
            if (isset($this->matrix[$teamId][$problemId])) {
                $maxScore = max($maxScore, $this->matrix[$teamId][$problemId]->getScore());
            }
        }

        return $maxScore;
    }

    /**
     * Get the CSS class for a scoreboard cell.
     */
    public function getCellCssClass(
        Team $team,
        ContestProblem $problem,
        bool $showPending = true,
        bool $jury = false
    ): string {
        $matrixItem = $this->matrix[$team->getTeamid()][$problem->getProbid()] ?? null;
        if ($matrixItem === null) {
            return 'score_neutral';
        }

        $cssClass = 'score_neutral';

        if ($this->isScoring()) {
            // For scoring problems: green if score > 0, red if score = 0 and attempted
            if ($matrixItem->hasPositiveScore()) {
                $cssClass = 'score_correct';
            } elseif ($showPending && $matrixItem->numSubmissionsPending > 0) {
                $cssClass = 'score_pending';
            } elseif ($matrixItem->numSubmissions > 0) {
                $cssClass = 'score_incorrect';
            }
        } else {
            // For pass-fail problems: use original logic
            if ($matrixItem->isCorrect) {
                $cssClass = 'score_correct';
                if (!$this->getRuntimeAsScoreTiebreaker() && $matrixItem->isFirst) {
                    $cssClass .= ' score_first';
                } elseif ($this->getRuntimeAsScoreTiebreaker() && $this->isFastestSubmission($team, $problem)) {
                    $cssClass .= ' score_first';
                }
            } elseif ($showPending && $matrixItem->numSubmissionsPending > 0) {
                $cssClass = 'score_pending';
            } elseif ($matrixItem->numSubmissions > 0) {
                $cssClass = 'score_incorrect';
            }
        }

        // Add pending indicator for jury view during freeze
        if ($jury && $showPending && $matrixItem->numSubmissionsInFreeze > 0) {
            if (!str_contains($cssClass, 'score_pending')) {
                $cssClass .= ' score_pending';
            }
        }

        return $cssClass;
    }

    /**
     * Get the inline background style for a scoreboard cell (for scoring gradient).
     * Returns empty string if no gradient is needed.
     */
    public function getCellStyle(Team $team, ContestProblem $problem): string
    {
        if (!$this->isScoring()) {
            return '';
        }

        $matrixItem = $this->matrix[$team->getTeamid()][$problem->getProbid()] ?? null;
        if ($matrixItem === null) {
            return '';
        }

        $maxScore = $this->getMaxScoreForProblem($problem);
        return $matrixItem->getGradientColor($maxScore);
    }

    /**
     * Check if a cell should display score/time (has positive score for scoring, is correct for pass-fail).
     */
    public function cellHasScore(Team $team, ContestProblem $problem): bool
    {
        $matrixItem = $this->matrix[$team->getTeamid()][$problem->getProbid()] ?? null;
        if ($matrixItem === null) {
            return false;
        }

        if ($this->isScoring()) {
            return $matrixItem->hasPositiveScore();
        }

        return $matrixItem->isCorrect;
    }
}
