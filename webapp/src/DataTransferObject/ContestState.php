<?php declare(strict_types=1);

namespace App\DataTransferObject;

class ContestState
{
    public function __construct(
        public readonly ?string $started = null,
        public readonly ?string $ended = null,
        public readonly ?string $frozen = null,
        public readonly ?string $thawed = null,
        public readonly ?string $finalized = null,
        public readonly ?string $endOfUpdates = null,
    ) {}
}
