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

    /** @var int[] */
    public array $bestRuntimes = [];

    public function addSubmissionCounts(
        int $sortorder,
        int $numSubmissions,
        int $numSubmissionsPending,
        int $numSubmissionsCorrect
    ): void {
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

    public function updateBestTime(int $sortorder, string|float $bestTime): void
    {
        $this->bestTimes[$sortorder] = $bestTime;
    }

    /**
     * Get the best runtime in milliseconds for the given sortorder.
     */
    public function getBestRuntime(int $sortorder): int
    {
        return $this->bestRuntimes[$sortorder] ?? PHP_INT_MAX;
    }

    /**
     * update fastest runtime if given time is a new record for this problem/sortorder
     */
    public function updateBestRuntime(int $sortorder, int $runtime): void
    {
        if ($runtime < $this->getBestRuntime($sortorder)) {
            $this->bestRuntimes[$sortorder] = $runtime;
        }
    }
}
