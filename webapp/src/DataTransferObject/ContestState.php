<?php declare(strict_types=1);

namespace App\DataTransferObject;

readonly class ContestState
{
    public function __construct(
        public ?string $started = null,
        public ?string $ended = null,
        public ?string $frozen = null,
        public ?string $thawed = null,
        public ?string $finalized = null,
        public ?string $endOfUpdates = null,
    ) {}
}
