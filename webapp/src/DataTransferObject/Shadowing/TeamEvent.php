<?php declare(strict_types=1);

namespace App\DataTransferObject\Shadowing;

readonly class TeamEvent implements EventData
{
    /**
     * @param string[]|null $groupIds
     */
    public function __construct(
        public string  $id,
        public string  $name,
        public ?string $formalName,
        public ?string $icpcId,
        public ?string $country,
        public ?string $organizationId,
        public ?array  $groupIds,
        public ?string $displayName,
    ) {}
}
