<?php declare(strict_types=1);

namespace App\DataTransferObject;

use JMS\Serializer\Annotation as Serializer;
use OpenApi\Attributes as OA;

readonly class PatchUser
{
    /**
     * @param array<string>|null $roles
     */
    public function __construct(
        #[OA\Property(nullable: true)]
        public ?string $id = null,
        #[OA\Property(nullable: true)]
        public ?string $username = null,
        #[OA\Property(nullable: true)]
        public ?string $name = null,
        #[OA\Property(nullable: true)]
        public ?string $ip = null,
        #[OA\Property(format: 'password', nullable: true)]
        public ?string $password = null,
        #[OA\Property(nullable: true)]
        public ?bool $enabled = null,
        #[OA\Property(nullable: true)]
        public ?string $teamId = null,
        #[Serializer\Type('array<string>')]
        #[OA\Property(nullable: true)]
        public ?array $roles = null,
    ) {}
}
