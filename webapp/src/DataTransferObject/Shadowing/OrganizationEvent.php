<?php declare(strict_types=1);

namespace App\DataTransferObject\Shadowing;

readonly class OrganizationEvent implements EventData
{
    public function __construct(
        public string  $id,
        public string  $name,
        public ?string $icpcId,
        public ?string $formalName,
        public ?string $country,
    ) {}
}
