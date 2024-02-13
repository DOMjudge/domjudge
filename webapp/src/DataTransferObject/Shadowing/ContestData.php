<?php declare(strict_types=1);

namespace App\DataTransferObject\Shadowing;

class ContestData
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $externalId,
        public readonly string $name,
        public readonly ?string $formalName,
        public readonly ?string $shortname,
        public readonly string $duration,
        public readonly ?string $scoreboardFreezeDuration,
        public readonly ?string $scoreboardThawTime,
        public readonly ?string $scoreboardType,
        public readonly int $penaltyTime,
        public readonly ?string $startTime,
        public readonly ?string $endTime,
        public readonly ?bool $allowSubmit,
        public readonly ?string $warningMessage,
    ) {}
}
