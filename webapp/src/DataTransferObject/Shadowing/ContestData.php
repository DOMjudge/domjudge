<?php declare(strict_types=1);

namespace App\DataTransferObject\Shadowing;

class ContestData
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $duration,
        public readonly ?string $scoreboardFreezeDuration,
        public readonly int $penaltyTime,
        public readonly ?string $startTime,
    ) {}
}
