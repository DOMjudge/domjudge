<?php declare(strict_types=1);

namespace App\DataTransferObject;

use OpenApi\Attributes as OA;

readonly class AddTeam
{
    /**
     * @param string[] $groupIds
     */
    public function __construct(
        #[OA\Property(nullable: true)]
        public ?string          $id = null,
        #[OA\Property(nullable: true)]
        public ?string          $icpcId = null,
        #[OA\Property(nullable: true)]
        public ?string          $label = null,
        #[OA\Property(type: 'array', items: new OA\Items(type: 'string'))]
        public array            $groupIds = [],
        #[OA\Property(nullable: true)]
        public ?string          $name = null,
        #[OA\Property(nullable: true)]
        public ?string          $displayName = null,
        #[OA\Property(nullable: true)]
        public ?string          $publicDescription = null,
        #[OA\Property(nullable: true)]
        public ?string          $members = null,
        #[OA\Property(nullable: true)]
        public ?string          $description = null,
        #[OA\Property(nullable: true)]
        public ?AddTeamLocation $location = null,
        #[OA\Property(nullable: true)]
        public ?string          $organizationId = null,
    ) {}
}
