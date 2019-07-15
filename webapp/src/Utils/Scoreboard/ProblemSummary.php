<?php declare(strict_types=1);

namespace App\Utils\Scoreboard;

class ProblemSummary
{
    /**
     * @var int[]
     */
    protected $numberOfSubmissions = [];

    /**
     * @var int[]
     */
    protected $numberOfPendingSubmissions = [];

    /**
     * @var int[]
     */
    protected $numberOfCorrectSubmissions = [];

    /**
     * @var float[]
     */
    protected $bestTimes = [];

    /**
     * @param int $sortorder
     * @return int
     */
    public function getNumberOfSubmissions(int $sortorder): int
    {
        return $this->numberOfSubmissions[$sortorder] ?? 0;
    }

    /**
     * @param int $sortorder
     * @param int $numberOfSubmissions
     */
    public function addNumberOfSubmissions(int $sortorder, int $numberOfSubmissions)
    {
        if (!isset($this->numberOfSubmissions[$sortorder])) {
            $this->numberOfSubmissions[$sortorder] = 0;
        }
        $this->numberOfSubmissions[$sortorder] += $numberOfSubmissions;
    }

    /**
     * @param int $sortorder
     * @return int
     */
    public function getNumberOfPendingSubmissions(int $sortorder): int
    {
        return $this->numberOfPendingSubmissions[$sortorder] ?? 0;
    }

    /**
     * @param int $sortorder
     * @param int $numberOfPendingSubmissions
     */
    public function addNumberOfPendingSubmissions(int $sortorder, int $numberOfPendingSubmissions)
    {
        if (!isset($this->numberOfPendingSubmissions[$sortorder])) {
            $this->numberOfPendingSubmissions[$sortorder] = 0;
        }
        $this->numberOfPendingSubmissions[$sortorder] += $numberOfPendingSubmissions;
    }

    /**
     * @param int $sortorder
     * @return int
     */
    public function getNumberOfCorrectSubmissions(int $sortorder): int
    {
        return $this->numberOfCorrectSubmissions[$sortorder] ?? 0;
    }

    /**
     * @param int $sortorder
     * @param int $numberOfCorrectSubmissions
     */
    public function addNumberOfCorrectSubmissions(int $sortorder, int $numberOfCorrectSubmissions)
    {
        if (!isset($this->numberOfCorrectSubmissions[$sortorder])) {
            $this->numberOfCorrectSubmissions[$sortorder] = 0;
        }
        $this->numberOfCorrectSubmissions[$sortorder] += $numberOfCorrectSubmissions;
    }

    /**
     * @return float[]
     */
    public function getBestTimes(): array
    {
        return $this->bestTimes;
    }

    /**
     * Get the best time in minutes for the given sortorder
     * @param int $sortorder
     * @return int|null
     */
    public function getBestTimeInMinutes(int $sortorder)
    {
        if (isset($this->bestTimes[$sortorder])) {
            return ((int)($this->bestTimes[$sortorder] / 60));
        }
        return null;
    }

    /**
     * @param int   $sortorder
     * @param float $bestTime
     */
    public function updateBestTime(int $sortorder, $bestTime)
    {
        $this->bestTimes[$sortorder] = $bestTime;
    }
}
