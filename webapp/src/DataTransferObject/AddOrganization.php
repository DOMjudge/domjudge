<?php declare(strict_types=1);

namespace App\DataTransferObject;

use OpenApi\Attributes as OA;

readonly class AddOrganization
{
    public function __construct(
        #[OA\Property(nullable: true)]
        public ?string $id = null,
        #[OA\Property(nullable: true)]
        public ?string $shortname = null,
        #[OA\Property(nullable: true)]
        public ?string $name = null,
        #[OA\Property(nullable: true)]
        public ?string $formalName = null,
        #[OA\Property(nullable: true)]
        public ?string $country = null,
        #[OA\Property(nullable: true)]
        public ?string $icpcId = null,
    ) {}
}
