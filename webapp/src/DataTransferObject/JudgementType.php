<?php declare(strict_types=1);

namespace App\DataTransferObject;

readonly class JudgementType
{
    public function __construct(
        public string $id,
        public string $name,
        public bool   $penalty,
        public bool   $solved,
    ) {}
}
