<?php declare(strict_types=1);

namespace App\DataTransferObject;

class Result
{
    public function __construct(
        public readonly string $teamId,
        public readonly ?int $rank,
        public readonly string $award,
        public readonly int $numSolved,
        public readonly int $totalTime,
        public readonly int $lastTime,
        public readonly string $groupWinner = '',
    ) {}
}
