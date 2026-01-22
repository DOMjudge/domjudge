<?php declare(strict_types=1);

namespace App\DataTransferObject\Shadowing;

readonly class GroupEvent implements EventData
{
    public function __construct(
        public string  $id,
        public string  $name,
        public ?string $icpcId,
        public ?bool   $hidden,
        public ?int    $sortorder,
        public ?string $color,
    ) {}
}
