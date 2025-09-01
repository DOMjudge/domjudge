<?php declare(strict_types=1);

namespace App\DataTransferObject\Shadowing;

class RunEvent implements EventData
{
    public function __construct(
        public readonly string $id,
        public readonly string $judgementId,
        public readonly int $ordinal,
        public readonly ?string $judgementTypeId,
        public readonly ?string $time,
        public readonly ?float $runTime,
        public readonly string|float|null $score,
    ) {}
}
