<?php declare(strict_types=1);

namespace App\DataTransferObject;

use OpenApi\Attributes as OA;

#[OA\Schema(required: ['id', 'name'])]
readonly class PutTeam
{
    /**
     * @param string[] $groupIds
     */
    public function __construct(
        public string           $id,
        public string           $name,
        #[OA\Property(nullable: true)]
        public ?string          $icpcId = null,
        #[OA\Property(nullable: true)]
        public ?string          $label = null,
        #[OA\Property(type: 'array', items: new OA\Items(type: 'string'))]
        public array            $groupIds = [],
        #[OA\Property(nullable: true)]
        public ?string          $displayName = null,
        #[OA\Property(nullable: true)]
        public ?string          $publicDescription = null,
        #[OA\Property(nullable: true)]
        public ?string          $members = null,
        #[OA\Property(nullable: true)]
        public ?AddTeamLocation $location = null,
        #[OA\Property(nullable: true)]
        public ?string          $organizationId = null,
    ) {}
}
