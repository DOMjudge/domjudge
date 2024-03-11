<?php declare(strict_types=1);

namespace App\DataTransferObject\Shadowing;

class JudgementTypeEvent implements EventData
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly bool $penalty,
        public readonly bool $solved,
    ) {}
}
