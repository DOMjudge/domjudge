<?php declare(strict_types=1);

namespace App\DataTransferObject\Shadowing;

readonly class JudgementTypeEvent implements EventData
{
    public function __construct(
        public string $id,
        public string $name,
        public bool   $penalty,
        public bool   $solved,
    ) {}
}
