<?php declare(strict_types=1);

namespace App\DataTransferObject\Shadowing;

readonly class ContestEvent implements EventData
{
    public function __construct(
        public string  $id,
        public string  $name,
        public string  $duration,
        public ?string $scoreboardType,
        public ?int    $penaltyTime,
        public ?string $formalName,
        public ?string $startTime,
        public ?string $countdownPauseTime,
        public ?string $scoreboardFreezeDuration,
        public ?string $scoreboardThawTime,
    ) {}
}
