<?php declare(strict_types=1);

namespace App\Utils\Scoreboard;

class ScoreboardMatrixItem
{
    public bool $isCorrect;
    public bool $isFirst;
    public int $numSubmissions;
    public int $numSubmissionsPending;

    /** @var float|string */
    public $time;
    public int $penaltyTime;

    public function __construct(
        bool $isCorrect,
        bool $isFirst,
        int $numSubmissions,
        int $numSubmissionsPending,
        $time,
        int $penaltyTime
    ) {
        $this->isCorrect             = $isCorrect;
        $this->isFirst               = $isFirst;
        $this->numSubmissions        = $numSubmissions;
        $this->numSubmissionsPending = $numSubmissionsPending;
        $this->time                  = $time;
        $this->penaltyTime           = $penaltyTime;
    }
}
