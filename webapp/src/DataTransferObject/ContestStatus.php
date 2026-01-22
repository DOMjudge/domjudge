<?php declare(strict_types=1);

namespace App\DataTransferObject;

readonly class ContestStatus
{
    public function __construct(
        public int $numSubmissions,
        public int $numQueued,
        public int $numJudging,
    ) {}
}
