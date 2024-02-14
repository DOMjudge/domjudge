<?php declare(strict_types=1);

namespace App\DataTransferObject\Shadowing;

class GroupEvent implements EventData
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $icpcId,
        public readonly ?string $type,
        public readonly ?string $location,
        public readonly ?bool $hidden,
        public readonly ?int $sortorder,
        public readonly ?string $color,
    ) {}
}
