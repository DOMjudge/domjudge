<?php declare(strict_types=1);

namespace App\DataTransferObject\Shadowing;

class JudgementEvent implements EventData
{
    public function __construct(
        public readonly string $startTime,
        public readonly string $startContestTime,
        public readonly string $id,
        public readonly string $submissionId,
        public readonly ?float $maxRunTime,
        public readonly ?string $endTime,
        public readonly ?string $outputCompileAsString,
        public readonly ?string $judgementTypeId,
    ) {}
}
