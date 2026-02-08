<?php declare(strict_types=1);

namespace App\DataTransferObject;

use OpenApi\Attributes as OA;

#[OA\Schema(required: ['id', 'username', 'name', 'roles'])]
class UpdateUser extends AddUser
{
    public function __construct(
        public readonly string $id,
        string $username,
        string $name,
        ?string $ip,
        ?string $password,
        ?bool $enabled,
        ?string $teamId,
        array $roles
    ) {
        parent::__construct($username, $name, $ip, $password, $enabled, $teamId, $roles);
    }
}
