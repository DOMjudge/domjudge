<?php declare(strict_types=1);

namespace App\DataTransferObject\Shadowing;

readonly class JudgementEvent implements EventData
{
    public function __construct(
        public string            $startTime,
        public string            $id,
        public string            $submissionId,
        public ?string           $endTime,
        public ?string           $judgementTypeId,
        public string|float|null $score,
    ) {}
}
