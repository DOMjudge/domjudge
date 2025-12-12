<?php declare(strict_types=1);

namespace App\DataTransferObject\Shadowing;

class ContestEvent implements EventData
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $duration,
        public readonly ?string $scoreboardType,
        public readonly ?int $penaltyTime,
        public readonly ?string $formalName,
        public readonly ?string $startTime,
        public readonly ?string $countdownPauseTime,
        public readonly ?string $scoreboardFreezeDuration,
        public readonly ?string $scoreboardThawTime,
    ) {}
}
