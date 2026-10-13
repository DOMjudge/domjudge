<?php declare(strict_types=1);

namespace App\DataTransferObject;

use OpenApi\Attributes as OA;

#[OA\Schema(required: ['id', 'name'])]
readonly class PutOrganization
{
    public function __construct(
        public string  $id,
        public string  $name,
        #[OA\Property(nullable: true)]
        public ?string $shortname = null,
        #[OA\Property(nullable: true)]
        public ?string $formalName = null,
        #[OA\Property(nullable: true)]
        public ?string $country = null,
        #[OA\Property(nullable: true)]
        public ?string $icpcId = null,
    ) {}
}
