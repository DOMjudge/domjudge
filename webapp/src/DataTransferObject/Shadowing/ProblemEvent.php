<?php declare(strict_types=1);

namespace App\DataTransferObject\Shadowing;

class ProblemEvent implements EventData
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly int $timeLimit,
        public readonly ?string $label,
        public readonly ?string $rgb,
    ) {}
}
