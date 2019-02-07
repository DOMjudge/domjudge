<?php declare(strict_types=1);

namespace DOMJudgeBundle\Utils\Scoreboard;

class ScoreboardMatrixItem
{
    /**
     * @var bool
     */
    protected $isCorrect;

    /**
     * @var int
     */
    protected $numberOfSubmissions;

    /**
     * @var int
     */
    protected $numberOfPendingSubmissions;

    /**
     * @var float|string
     */
    protected $time;

    /**
     * @var int
     */
    protected $penaltyTime;

    /**
     * @var int
     */
    protected $sortOrder;

    /**
     * ScoreboardMatrixItem constructor.
     * @param bool $isCorrect
     * @param int $numberOfSubmissions
     * @param int $numberOfPendingSubmissions
     * @param float|string $time
     * @param int $penaltyTime
     */
    public function __construct(bool $isCorrect, int $numberOfSubmissions, int $numberOfPendingSubmissions, $time, int $penaltyTime, int $sortOrder)
    {
        $this->isCorrect                  = $isCorrect;
        $this->numberOfSubmissions        = $numberOfSubmissions;
        $this->numberOfPendingSubmissions = $numberOfPendingSubmissions;
        $this->time                       = $time;
        $this->penaltyTime                = $penaltyTime;
        $this->sortOrder                  = $sortOrder;
    }

    /**
     * @return bool
     */
    public function isCorrect(): bool
    {
        return $this->isCorrect;
    }

    /**
     * @return int
     */
    public function getNumberOfSubmissions(): int
    {
        return $this->numberOfSubmissions;
    }

    /**
     * @return int
     */
    public function getNumberOfPendingSubmissions(): int
    {
        return $this->numberOfPendingSubmissions;
    }

    /**
     * @return string|float
     */
    public function getTime()
    {
        return $this->time;
    }

    /**
     * @return int
     */
    public function getPenaltyTime(): int
    {
        return $this->penaltyTime;
    }
    
    /**
     * @return int
     */
    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }
}
