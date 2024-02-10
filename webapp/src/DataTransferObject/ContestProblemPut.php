<?php declare(strict_types=1);

namespace App\DataTransferObject;

use OpenApi\Attributes as OA;

#[OA\Schema(required: ['label'])]
class ContestProblemPut
{
    public function __construct(
        #[OA\Property(description: 'The label of the problem to add to the contest')]
        public readonly string $label,
        #[OA\Property(description: 'Human readable color of the problem to add. Will be overwritten by `rgb` if supplied', nullable: true)]
        public readonly ?string $color,
        #[OA\Property(description: 'Hexadecimal RGB value of the color of the problem to add. Overrules `color` if supplied', nullable: true)]
        public readonly ?string $rgb,
        #[OA\Property(description: 'The number of points for the problem to add. Defaults to 1')]
        public readonly int $points = 1,
        #[OA\Property(description: 'Whether to use lazy evaluation for this problem. Defaults to the global setting', nullable: true)]
        public readonly ?bool $lazyEvalResults = null,
    ) {}
}
