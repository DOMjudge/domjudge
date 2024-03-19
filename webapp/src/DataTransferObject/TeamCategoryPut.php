<?php declare(strict_types=1);

namespace App\DataTransferObject;

use OpenApi\Attributes as OA;

#[OA\Schema(required: ['id', 'name'])]
class TeamCategoryPut extends TeamCategoryPost
{
    public function __construct(
        #[OA\Property(description: 'The ID of the group. Only allowed with PUT requests', nullable: true)]
        public readonly ?string $id,
        string $name,
        bool $hidden = false,
        ?string $icpcId = null,
        int $sortorder = 0,
        ?string $color = null,
        bool $allowSelfRegistration = false
    ) {
        parent::__construct($name, $hidden, $icpcId, $sortorder, $color, $allowSelfRegistration);
    }
}
