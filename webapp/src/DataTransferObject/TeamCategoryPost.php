<?php declare(strict_types=1);

namespace App\DataTransferObject;

use OpenApi\Attributes as OA;

#[OA\Schema(required: ['name'])]
class TeamCategoryPost
{
    public function __construct(
        #[OA\Property(description: 'The ID of the group. Only allowed with PUT requests', nullable: true)]
        public readonly ?string $id,
        #[OA\Property(description: 'How to name this group on the scoreboard')]
        public readonly string $name,
        #[OA\Property(description: 'Show this group on the scoreboard?')]
        public readonly bool $hidden = false,
        #[OA\Property(description: 'The ID in the ICPC CMS for this group', nullable: true)]
        public readonly ?string $icpcId = null,
        #[OA\Property(description: 'Bundle groups with the same sortorder, create different scoreboards per sortorder', minimum: 0, nullable: true)]
        public readonly int $sortorder = 0,
        #[OA\Property(description: 'Color to use for teams in this group on the scoreboard', nullable: true)]
        public readonly ?string $color = null,
        #[OA\Property(description: 'Whether to allow self registration for this group')]
        public readonly bool $allowSelfRegistration = false,
    ) {}
}
