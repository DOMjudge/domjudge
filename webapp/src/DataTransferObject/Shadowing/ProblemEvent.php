<?php declare(strict_types=1);

namespace App\DataTransferObject\Shadowing;

readonly class ProblemEvent implements EventData
{
    public function __construct(
        public string  $id,
        public string  $name,
        public ?float  $timeLimit,
        public ?string $label,
        public ?string $rgb,
    ) {}
}
