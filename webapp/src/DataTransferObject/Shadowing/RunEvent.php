<?php declare(strict_types=1);

namespace App\DataTransferObject\Shadowing;

readonly class RunEvent implements EventData
{
    public function __construct(
        public string            $id,
        public string            $judgementId,
        public int               $ordinal,
        public ?string           $judgementTypeId,
        public ?string           $time,
        public ?float            $runTime,
        public string|float|null $score,
    ) {}
}
