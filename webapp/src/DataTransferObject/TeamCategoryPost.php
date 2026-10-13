<?php declare(strict_types=1);

namespace App\DataTransferObject;

use App\Entity\TeamCategory;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(required: ['name'])]
class TeamCategoryPost
{
    /**
     * @param string[]|null $types
     */
    public function __construct(
        #[OA\Property(description: 'How to name this group on the scoreboard')]
        public readonly string $name,
        #[OA\Property(description: 'Show this group on the scoreboard?')]
        public readonly bool $hidden = false,
        #[OA\Property(description: 'The ID in the ICPC CMS for this group', nullable: true)]
        public readonly ?string $icpcId = null,
        #[OA\Property(description: 'Bundle groups with the same sortorder, create different scoreboards per sortorder', minimum: 0, nullable: true)]
        public readonly ?int $sortorder = null,
        #[OA\Property(description: 'Color to use for teams in this group on the scoreboard', nullable: true)]
        public readonly ?string $color = null,
        #[OA\Property(description: 'Whether to allow self registration for this group')]
        public readonly bool $allowSelfRegistration = false,
        #[OA\Property(description: 'Whether teams in this group are allowed to change their own password')]
        public readonly bool $allowPasswordChange = false,
        #[OA\Property(
            description: 'How this group is used, defaults to scoring and badge-top',
            items: new OA\Items(type: 'string'),
            nullable: true,
        )]
        #[Assert\Choice(callback: [TeamCategory::class, 'getAllTypeNames'], multiple: true)]
        public readonly ?array $types = null,
        #[OA\Property(description: 'CSS class to apply to scoreboard rows, for groups of type css-class', nullable: true)]
        public readonly ?string $cssClass = null,
    ) {}
}
