<?php declare(strict_types=1);

namespace App\DataTransferObject;

use App\Entity\TeamCategory;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

readonly class TeamCategoryPatch
{
    /**
     * @param string[]|null $types
     */
    public function __construct(
        #[OA\Property(nullable: true)]
        public ?string $id = null,
        #[OA\Property(description: 'How to name this group on the scoreboard', nullable: true)]
        public ?string $name = null,
        #[OA\Property(description: 'Show this group on the scoreboard?', nullable: true)]
        public ?bool $hidden = null,
        #[OA\Property(description: 'The ID in the ICPC CMS for this group', nullable: true)]
        public ?string $icpcId = null,
        #[OA\Property(description: 'Bundle groups with the same sortorder, create different scoreboards per sortorder', minimum: 0, nullable: true)]
        public ?int $sortorder = null,
        #[OA\Property(description: 'Color to use for teams in this group on the scoreboard', nullable: true)]
        public ?string $color = null,
        #[OA\Property(description: 'Whether to allow self registration for this group', nullable: true)]
        public ?bool $allowSelfRegistration = null,
        #[OA\Property(description: 'Whether teams in this group are allowed to change their own password', nullable: true)]
        public ?bool $allowPasswordChange = null,
        #[OA\Property(description: 'How this group is used', items: new OA\Items(type: 'string'), nullable: true)]
        #[Assert\Choice(callback: [TeamCategory::class, 'getAllTypeNames'], multiple: true)]
        public ?array $types = null,
        #[OA\Property(description: 'CSS class to apply to scoreboard rows, for groups of type css-class', nullable: true)]
        public ?string $cssClass = null,
    ) {}
}
