<?php declare(strict_types=1);

namespace App\DataTransferObject;

class ContestStatus
{
    public function __construct(
        public readonly int $numSubmissions,
        public readonly int $numQueued,
        public readonly int $numJudging,
    ) {}
}
