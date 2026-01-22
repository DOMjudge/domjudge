<?php declare(strict_types=1);

namespace App\DataTransferObject\Shadowing;

readonly class StateEvent implements EventData
{
    public function __construct(
        public ?string $endOfUpdates,
    ) {}
}
