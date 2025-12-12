<?php declare(strict_types=1);

namespace App\DataTransferObject\Shadowing;

class TeamEvent implements EventData
{
    /**
     * @param string[]|null $groupIds
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $formalName,
        public readonly ?string $icpcId,
        public readonly ?string $country,
        public readonly ?string $organizationId,
        public readonly ?array $groupIds,
        public readonly ?string $displayName,
        public readonly ?bool $hidden,
    ) {}
}
