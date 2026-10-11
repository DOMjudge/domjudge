<?php declare(strict_types=1);

namespace App\DataTransferObject;

use OpenApi\Attributes as OA;

#[OA\Schema(required: ['id', 'name'])]
class TeamCategoryPut extends TeamCategoryPost
{
    /**
     * @param string[]|null $types
     */
    public function __construct(
        #[OA\Property(description: 'The ID of the group. Only allowed with PUT requests', nullable: true)]
        public readonly ?string $id,
        string $name,
        bool $hidden = false,
        ?string $icpcId = null,
        ?int $sortorder = null,
        ?string $color = null,
        bool $allowSelfRegistration = false,
        bool $allowPasswordChange = false,
        ?array $types = null,
        ?string $cssClass = null,
    ) {
        parent::__construct(
            $name,
            $hidden,
            $icpcId,
            $sortorder,
            $color,
            $allowSelfRegistration,
            $allowPasswordChange,
            $types,
            $cssClass,
        );
    }
}
