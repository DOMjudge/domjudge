<?php declare(strict_types=1);

namespace App\DataTransferObject;

class ContestState
{
    public function __construct(
        public readonly ?string $started,
        public readonly ?string $ended,
        public readonly ?string $frozen,
        public readonly ?string $thawed,
        public readonly ?string $finalized,
        public readonly ?string $endOfUpdates,
    ) {}
}
