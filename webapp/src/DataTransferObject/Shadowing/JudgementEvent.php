<?php declare(strict_types=1);

namespace App\DataTransferObject\Shadowing;

class JudgementEvent implements EventData
{
    public function __construct(
        public readonly string $startTime,
        public readonly string $id,
        public readonly string $submissionId,
        public readonly ?string $endTime,
        public readonly ?string $judgementTypeId,
        public readonly string|float|null $score,
    ) {}
}
