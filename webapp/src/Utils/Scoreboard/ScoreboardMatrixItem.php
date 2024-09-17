<?php declare(strict_types=1);

namespace App\Utils\Scoreboard;

class ScoreboardMatrixItem
{
    public function __construct(
        public bool $isCorrect,
        public bool $isFirst,
        public int $numSubmissions,
        public int $numSubmissionsPending,
        public float|string $time,
        public int $penaltyTime,
        public int $runtime,
        public ?int $numSubmissionsInFreeze = null,
    ) {}
}
