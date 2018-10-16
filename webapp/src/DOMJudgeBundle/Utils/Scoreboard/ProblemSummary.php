<?php declare(strict_types=1);

namespace DOMJudgeBundle\Utils\Scoreboard;

class ProblemSummary
{
    /**
     * @var int
     */
    protected $numberOfSubmissions = 0;

    /**
     * @var int
     */
    protected $numberOfPendingSubmissions = 0;

    /**
     * @var int
     */
    protected $numberOfCorrectSubmissions = 0;

    /**
     * @var float[]
     */
    protected $bestTimes = [];

    /**
     * @return int
     */
    public function getNumberOfSubmissions(): int
    {
        return $this->numberOfSubmissions;
    }

    /**
     * @param int $numberOfSubmissions
     */
    public function addNumberOfSubmissions(int $numberOfSubmissions)
    {
        $this->numberOfSubmissions += $numberOfSubmissions;
    }

    /**
     * @return int
     */
    public function getNumberOfPendingSubmissions(): int
    {
        return $this->numberOfPendingSubmissions;
    }

    /**
     * @param int $numberOfPendingSubmissions
     */
    public function addNumberOfPendingSubmissions(int $numberOfPendingSubmissions)
    {
        $this->numberOfPendingSubmissions += $numberOfPendingSubmissions;
    }

    /**
     * @return int
     */
    public function getNumberOfCorrectSubmissions(): int
    {
        return $this->numberOfCorrectSubmissions;
    }

    /**
     * @param int $numberOfCorrectSubmissions
     */
    public function addNumberOfCorrectSubmissions(int $numberOfCorrectSubmissions)
    {
        $this->numberOfCorrectSubmissions += $numberOfCorrectSubmissions;
    }

    /**
     * @return float[]
     */
    public function getBestTimes(): array
    {
        return $this->bestTimes;
    }

    /**
     * @param int $sortorder
     * @param float $bestTime
     */
    public function updateBestTime(int $sortorder, $bestTime)
    {
        if (!isset($this->bestTimes[$sortorder]) || $bestTime < $this->bestTimes[$sortorder]) {
            $this->bestTimes[$sortorder] = $bestTime;
        }
    }
}
