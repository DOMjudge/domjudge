<?php declare(strict_types=1);

namespace App\DataTransferObject;

use OpenApi\Attributes as OA;

#[OA\Schema(required: ['id'])]
class PatchContest
{
    public function __construct(
        public readonly string $id,
        #[OA\Property(description: 'The new start time of the contest', nullable: true)]
        public readonly ?string $startTime = null,
        #[OA\Property(description: 'The new unfreeze (thaw) time of the contest', nullable: true)]
        public readonly ?string $scoreboardThawTime = null,
        #[OA\Property(description: 'Force overwriting the start_time even when in next 30s or the scoreboard_thaw_time when already set or too much in the past')]
        public readonly bool $force = false,
    ) {}
}
