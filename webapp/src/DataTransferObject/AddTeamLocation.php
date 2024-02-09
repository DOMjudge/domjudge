<?php declare(strict_types=1);

namespace App\DataTransferObject;

use OpenApi\Attributes as OA;

class AddTeamLocation
{
    public function __construct(
        #[OA\Property(nullable: true)]
        public readonly ?string $description = null,
    ) {}
}
