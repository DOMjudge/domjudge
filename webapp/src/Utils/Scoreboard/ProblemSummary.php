<?php declare(strict_types=1);

namespace App\Utils\Scoreboard;

class ProblemSummary
{
    /** @var int[] */
    public array $numSubmissions = [];

    /** @var int[] */
    public array $numSubmissionsPending = [];

    /** @var int[] */
    public array $numSubmissionsCorrect = [];

    /** @var float[] */
    public array $bestTimes = [];

    public function addSubmissionCounts(
        int $sortorder,
        int $numSubmissions,
        int $numSubmissionsPending,
        int $numSubmissionsCorrect
    ) {
        if (!isset($this->numSubmissions[$sortorder])) {
            $this->numSubmissions[$sortorder] = 0;
        }
        if (!isset($this->numSubmissionsPending[$sortorder])) {
            $this->numSubmissionsPending[$sortorder] = 0;
        }
        if (!isset($this->numSubmissionsCorrect[$sortorder])) {
            $this->numSubmissionsCorrect[$sortorder] = 0;
        }
        $this->numSubmissions[$sortorder]        += $numSubmissions;
        $this->numSubmissionsPending[$sortorder] += $numSubmissionsPending;
        $this->numSubmissionsCorrect[$sortorder] += $numSubmissionsCorrect;
    }

    /**
     * Get the best time in minutes for the given sortorder.
     */
    public function getBestTimeInMinutes(int $sortorder): ?int
    {
        if (isset($this->bestTimes[$sortorder])) {
            return ((int)($this->bestTimes[$sortorder] / 60));
        }
        return null;
    }

    /**
     * @param string|float $bestTime
     */
    public function updateBestTime(int $sortorder, $bestTime)
    {
        $this->bestTimes[$sortorder] = $bestTime;
    }
}
