<?php declare(strict_types=1);

namespace App\Utils\Scoreboard;

class ScoreboardMatrixItem
{
    /**
     * @var bool
     */
    protected $isCorrect;

    /**
     * @var bool
     */
    protected $isFirst;

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
     * ScoreboardMatrixItem constructor.
     * @param bool $isCorrect
     * @param bool $isFirst
     * @param int $numberOfSubmissions
     * @param int $numberOfPendingSubmissions
     * @param float|string $time
     * @param int $penaltyTime
     */
    public function __construct(
        bool $isCorrect,
        bool $isFirst,
        int $numberOfSubmissions,
        int $numberOfPendingSubmissions,
        $time,
        int $penaltyTime
    ) {
        $this->isCorrect                  = $isCorrect;
        $this->isFirst                    = $isFirst;
        $this->numberOfSubmissions        = $numberOfSubmissions;
        $this->numberOfPendingSubmissions = $numberOfPendingSubmissions;
        $this->time                       = $time;
        $this->penaltyTime                = $penaltyTime;
    }

    /**
     * @return bool
     */
    public function isCorrect(): bool
    {
        return $this->isCorrect;
    }

    /**
     * @return bool
     */
    public function isFirst(): bool
    {
        return $this->isFirst;
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
}
