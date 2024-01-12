<?php declare(strict_types=1);

namespace App\DataTransferObject;

class JudgementType
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly bool $penalty,
        public readonly bool $solved,
    ) {}
}
