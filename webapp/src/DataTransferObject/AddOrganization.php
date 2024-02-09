<?php declare(strict_types=1);

namespace App\DataTransferObject;

use OpenApi\Attributes as OA;

class AddOrganization
{
    public function __construct(
        #[OA\Property(nullable: true)]
        public readonly ?string $id = null,
        #[OA\Property(nullable: true)]
        public readonly ?string $shortname = null,
        #[OA\Property(nullable: true)]
        public readonly ?string $name = null,
        #[OA\Property(nullable: true)]
        public readonly ?string $formalName = null,
        #[OA\Property(nullable: true)]
        public readonly ?string $country = null,
        #[OA\Property(nullable: true)]
        public readonly ?string $icpcId = null,
    ) {}
}
