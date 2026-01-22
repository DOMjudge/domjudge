<?php declare(strict_types=1);

namespace App\DataTransferObject\Shadowing;

use App\Entity\ScoreboardType;

readonly class ContestData
{
    public function __construct(
        public string          $id,
        public string          $name,
        public string          $duration,
        public ?string         $scoreboardFreezeDuration,
        public int             $penaltyTime,
        public ?string         $startTime,
        public ?ScoreboardType $scoreboardType,
        // TODO: check for end time and scoreboard thaw time
    ) {}
}
