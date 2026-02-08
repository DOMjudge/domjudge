<?php declare(strict_types=1);

namespace App\DataTransferObject;

use JMS\Serializer\Annotation as Serializer;
use OpenApi\Attributes as OA;

#[OA\Schema(required: ['username', 'name', 'roles'])]
class AddUser
{
    /**
     * @param array<string> $roles
     */
    public function __construct(
        public readonly string $username,
        public readonly string $name,
        #[OA\Property(nullable: true)]
        public readonly ?string $ip,
        #[OA\Property(format: 'password', nullable: true)]
        public readonly ?string $password,
        #[OA\Property(nullable: true)]
        public readonly ?bool $enabled,
        #[OA\Property(nullable: true)]
        public readonly ?string $teamId,
        #[Serializer\Type('array<string>')]
        public readonly array $roles,
    ) {}
}
