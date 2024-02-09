<?php declare(strict_types=1);

namespace App\DataTransferObject;

use OpenApi\Attributes as OA;

class AddTeam
{
    /**
     * @param string[] $groupIds
     */
    public function __construct(
        #[OA\Property(nullable: true)]
        public readonly ?string $id = null,
        #[OA\Property(nullable: true)]
        public readonly ?string $icpcId = null,
        #[OA\Property(nullable: true)]
        public readonly ?string $label = null,
        #[OA\Property(type: 'array', items: new OA\Items(type: 'string'))]
        public readonly array $groupIds = [],
        #[OA\Property(nullable: true)]
        public readonly ?string $name = null,
        #[OA\Property(nullable: true)]
        public readonly ?string $displayName = null,
        #[OA\Property(nullable: true)]
        public readonly ?string $publicDescription = null,
        #[OA\Property(nullable: true)]
        public readonly ?string $members = null,
        #[OA\Property(nullable: true)]
        public readonly ?string $description = null,
        #[OA\Property(nullable: true)]
        public readonly ?AddTeamLocation $location = null,
        #[OA\Property(nullable: true)]
        public readonly ?string $organizationId = null,
    ) {}
}
