<?php declare(strict_types=1);

namespace App\Utils\Scoreboard;

use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\ScoreCache;
use App\Entity\Team;
use App\Entity\TeamCategory;
use App\Utils\FreezeData;
use App\Utils\Utils;

/**
 * Class Scoreboard
 *
 * This class represents the whole scoreboard
 *
 * @package App\Utils\Scoreboard
 */
class Scoreboard
{
    /**
     * @var Contest
     */
    protected $contest;

    /**
     * @var Team[]
     */
    protected $teams;

    /**
     * @var TeamCategory[]
     */
    protected $categories;

    /**
     * @var ContestProblem[]
     */
    protected $problems;

    /**
     * @var ScoreCache[]
     */
    protected $scoreCache;

    /**
     * @var FreezeData
     */
    protected $freezeData;

    /**
     * @var bool
     */
    protected $restricted;

    /**
     * @var int
     */
    protected $penaltyTime;

    /**
     * @var bool
     */
    protected $scoreIsInSeconds;

    /**
     * @var ScoreboardMatrixItem[][]
     */
    protected $matrix = [];

    /**
     * @var Summary
     */
    protected $summary;

    /**
     * @var TeamScore[]
     */
    protected $scores = [];

    /**
     * @var array
     */
    protected $bestInCategoryData = null;

    /**
     * Scoreboard constructor.
     * @param Contest          $contest
     * @param Team[]           $teams
     * @param TeamCategory[]   $categories
     * @param ContestProblem[] $problems
     * @param ScoreCache[]     $scoreCache
     * @param FreezeData       $freezeData
     * @param bool             $jury
     * @param int              $penaltyTime
     * @param bool             $scoreIsInSeconds
     */
    public function __construct(
        Contest $contest,
        array $teams,
        array $categories,
        array $problems,
        array $scoreCache,
        FreezeData $freezeData,
        bool $jury,
        int $penaltyTime,
        bool $scoreIsInSeconds
    ) {
        $this->contest          = $contest;
        $this->teams            = $teams;
        $this->categories       = $categories;
        $this->problems         = $problems;
        $this->scoreCache       = $scoreCache;
        $this->freezeData       = $freezeData;
        $this->restricted       = $jury || $freezeData->showFinal($jury);
        $this->penaltyTime      = $penaltyTime;
        $this->scoreIsInSeconds = $scoreIsInSeconds;

        $this->initializeScoreboard();
        $this->calculateScoreboard();
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

    /**
     * @return Summary
     */
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

    /**
     * @return FreezeData
     */
    public function getFreezeData(): FreezeData
    {
        return $this->freezeData;
    }

    /**
     * Get the progress of this scoreboard
     * @return int
     */
    public function getProgress()
    {
        $now = Utils::now();
        if (!$this->freezeData->started()) {
            return -1;
        }
        $left = Utils::difftime((float)$this->contest->getEndtime(), $now);
        if ($left <= 0) {
            return 100;
        }

        $passed   = Utils::difftime((float)$this->contest->getStarttime(), $now);
        $duration = Utils::difftime((float)$this->contest->getStarttime(),
                                    (float)$this->contest->getEndtime());
        return (int)($passed * 100. / $duration);
    }

    /**
     * Initialize the scoreboard data
     */
    protected function initializeScoreboard()
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
     * Calculate the scoreboard data, filling the summary, matrix and scores properties
     */
    protected function calculateScoreboard()
    {
        // Calculate matrix and update scores
        $this->matrix = [];
        foreach ($this->scoreCache as $scoreRow) {
            // Skip this row if the team or problem is not known by us
            if (!array_key_exists($scoreRow->getTeam()->getTeamid(), $this->teams) ||
                !array_key_exists($scoreRow->getProblem()->getProbid(), $this->problems)) {
                continue;
            }

            $penalty = Utils::calcPenaltyTime(
                $scoreRow->getIsCorrect($this->restricted),
                $scoreRow->getSubmissions($this->restricted),
                $this->penaltyTime, $this->scoreIsInSeconds
            );

            $this->matrix[$scoreRow->getTeam()->getTeamid()][$scoreRow->getProblem()->getProbid()] = new ScoreboardMatrixItem(
                $scoreRow->getIsCorrect($this->restricted),
                $scoreRow->getIsCorrect($this->restricted) ? $scoreRow->getIsFirstToSolve() : false,
                $scoreRow->getSubmissions($this->restricted),
                $scoreRow->getPending($this->restricted),
                $scoreRow->getSolveTime($this->restricted),
                $penalty
            );

            if ($scoreRow->getIsCorrect($this->restricted)) {
                $solveTime      = Utils::scoretime($scoreRow->getSolveTime($this->restricted), $this->scoreIsInSeconds);
                $contestProblem = $this->problems[$scoreRow->getProblem()->getProbid()];
                $this->scores[$scoreRow->getTeam()->getTeamid()]->addNumberOfPoints($contestProblem->getPoints());
                $this->scores[$scoreRow->getTeam()->getTeamid()]->addSolveTime($solveTime);
                $this->scores[$scoreRow->getTeam()->getTeamid()]->addTotalTime($solveTime + $penalty);
            }
        }

        // Now sort the scores using the scoreboard sort function
        uasort($this->scores, [static::class, 'scoreboardCompare']);

        // Loop over all teams to calculate ranks and totals
        $prevSortOrder  = -1;
        $rank           = 0;
        $previousTeamId = null;
        foreach ($this->scores as $teamScore) {
            $teamId = $teamScore->getTeam()->getTeamid();
            // rank, team name, total correct, total time
            if ($teamScore->getTeam()->getCategory()->getSortorder() != $prevSortOrder) {
                $prevSortOrder  = $teamScore->getTeam()->getCategory()->getSortorder();
                $rank           = 0; // reset team position on switch to different category
                $previousTeamId = null;
            }
            $rank++;

            // Use previous team rank when scores are equal
            if (isset($previousTeamId) && $this->scoreCompare($this->scores[$previousTeamId], $teamScore) == 0) {
                $teamScore->setRank($rank);
                $teamScore->setRank($this->scores[$previousTeamId]->getRank());
            } else {
                $teamScore->setRank($rank);
            }
            $previousTeamId = $teamId;

            // Keep summary statistics for the bottom row of our table
            // The numberOfPoints summary is useful only if they're all 1-point problems.
            $sortOrder = $teamScore->getTeam()->getCategory()->getSortorder();
            $this->summary->addNumberOfPoints($sortOrder, $teamScore->getNumberOfPoints());
            if ($teamScore->getTeam()->getAffiliation()) {
                $this->summary->incrementAffiliationValue($teamScore->getTeam()->getAffiliation()->getAffilid());
                if ($teamScore->getTeam()->getAffiliation()->getCountry()) {
                    $this->summary->incrementCountryValue($teamScore->getTeam()->getAffiliation()->getCountry());
                }
            }

            // Loop over the problems
            foreach ($this->problems as $contestProblem) {
                $problemId = $contestProblem->getProbid();
                // Provide default scores when nothing submitted for this team + problem yet
                if (!isset($this->matrix[$teamId][$problemId])) {
                    $this->matrix[$teamId][$problemId] = new ScoreboardMatrixItem(false, false, 0, 0, 0, 0);
                }

                $problemMatrixItem = $this->matrix[$teamId][$problemId];
                $problemSummary    = $this->summary->getProblem($problemId);
                $problemSummary->addNumberOfSubmissions($sortOrder, $problemMatrixItem->getNumberOfSubmissions());
                $problemSummary->addNumberOfPendingSubmissions($sortOrder,
                                                               $problemMatrixItem->getNumberOfPendingSubmissions());
                $problemSummary->addNumberOfCorrectSubmissions($sortOrder, $problemMatrixItem->isCorrect() ? 1 : 0);
                if ($problemMatrixItem->isFirst()) {
                    $problemSummary->updateBestTime($sortOrder, $problemMatrixItem->getTime());
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
     * @param TeamScore $a
     * @param TeamScore $b
     * @return int
     */
    protected static function scoreboardCompare(TeamScore $a, TeamScore $b)
    {
        // First order by our predefined sortorder based on category
        if ($a->getTeam()->getCategory()->getSortorder() != $b->getTeam()->getCategory()->getSortorder()) {
            return $a->getTeam()->getCategory()->getSortorder() <=> $b->getTeam()->getCategory()->getSortorder();
        }

        // Then compare scores
        $scoreCompare = static::scoreCompare($a, $b);
        if ($scoreCompare != 0) {
            return $scoreCompare;
        }

        // Else, order by teamname alphabetically
        if ($a->getTeam()->getName() != $b->getTeam()->getName()) {
            $collator = new \Collator('en');
            return $collator->compare($a->getTeam()->getName(), $b->getTeam()->getName());
        }
        // Undecided, should never happen in practice
        return 0;
    }

    /**
     * Main score comparison function, called from the 'scoreboardCompare' wrapper
     * above. Scores based on the following criteria:
     * - highest points from correct solutions;
     * - least amount of total time spent on these solutions;
     * - the tie-breaker function below
     * @param TeamScore $a
     * @param TeamScore $b
     * @return int
     */
    protected static function scoreCompare(TeamScore $a, TeamScore $b): int
    {
        // More correctness points than someone else means higher rank
        if ($a->getNumberOfPoints() != $b->getNumberOfPoints()) {
            return $b->getNumberOfPoints() <=> $a->getNumberOfPoints();
        }
        // Else, less time spent means higher rank
        if ($a->getTotalTime() != $b->getTotalTime()) {
            return $a->getTotalTime() <=> $b->getTotalTime();
        }
        // Else tie-breaker rule
        return static::scoreTiebreaker($a, $b);
    }

    /**
     * Tie-breaker comparison function, called from the 'scoreCompare' function
     * above. Scores based on the following criterion:
     * - fastest submission time for latest correct problem
     * @param TeamScore $a
     * @param TeamScore $b
     * @return int
     */
    public static function scoreTiebreaker(TeamScore $a, TeamScore $b): int
    {
        $atimes = $a->getSolveTimes();
        $btimes = $b->getSolveTimes();
        rsort($atimes);
        rsort($btimes);
        if (isset($atimes[0]) && isset($btimes[0])) {
            return $atimes[0] <=> $btimes[0];
        } else {
            if (!isset($atimes[0]) && !isset($btimes[0])) {
                return 0;
            } else {
                if (!isset($atimes[0])) {
                    return -1;
                } else {
                    if (!isset($btimes[0])) {
                        return 1;
                    }
                }
            }
        }
    }

    /**
     * Return whether to show points for this scoreboard
     * @return bool
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
     * Return the used team categories for this scoreboard
     * @param array|null $limitToTeamIds
     * @return TeamCategory[]
     */
    public function getUsedCategories(array $limitToTeamIds = null)
    {
        $usedCategories = [];
        foreach ($this->scores as $score) {
            // skip if we have limitteams and the team is not listed
            if (!empty($limitToTeamIds) &&
                !in_array($score->getTeam()->getTeamid(), $limitToTeamIds)) {
                continue;
            }

            if ($score->getTeam()->getCategory()) {
                $category = $score->getTeam()->getCategory();
                $usedCategories[$category->getCategoryid()] = $category;
            }
        }

        return $usedCategories;
    }

    /**
     * Return whether this scoreboard has category colors
     * @return bool
     */
    public function hasCategoryColors(): bool
    {
        foreach ($this->scores as $score) {
            if ($score->getTeam()->getCategory() &&
                $score->getTeam()->getCategory()->getColor()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether this team is the best in the given category
     * @param Team       $team
     * @param array|null $limitToTeamIds
     * @return bool
     */
    public function isBestInCategory(Team $team, array $limitToTeamIds = null): bool
    {
        if ($this->bestInCategoryData === null) {
            $this->bestInCategoryData = [];
            foreach ($this->scores as $score) {
                // skip if we have limitteams and the team is not listed
                if (!empty($limitToTeamIds) &&
                    !in_array($score->getTeam()->getTeamid(), $limitToTeamIds)) {
                    continue;
                }

                $categoryId = $score->getTeam()->getCategoryid();
                if (!isset($this->bestInCategoryData[$categoryId])) {
                    $this->bestInCategoryData[$categoryId] = $score->getTeam()->getTeamid();
                }
            }
        }

        $categoryId = $team->getCategoryid();
        // Only check the scores when the team has points
        if ($this->scores[$team->getTeamid()]->getNumberOfPoints()) {
            // If the rank of this team is equal to the best team for this
            // category, this team is best in that category
            return $this->scores[$this->bestInCategoryData[$categoryId]]->getRank() ===
                $this->scores[$team->getTeamid()]->getRank();
        }

        return false;
    }

    /**
     * Determine whether this team was the first team to solve this problem
     * @param Team           $team
     * @param ContestProblem $problem
     * @return bool
     */
    public function solvedFirst(Team $team, ContestProblem $problem): bool
    {
        return $this->matrix[$team->getTeamid()][$problem->getProbid()]->isFirst();
    }
}
