<?php declare(strict_types=1);

namespace App\DataTransferObject;

use JMS\Serializer\Annotation as Serializer;
use OpenApi\Attributes as OA;

class UpdateUser
{
    public ?string $id = null;
    public ?string $username = null;
    public ?string $name = null;
    public ?string $email = null;
    public ?string $ip = null;
    #[OA\Property(format: 'password')]
    public ?string $password = null;
    public ?bool $enabled = null;
    public ?string $teamId = null;
    /**
     * @var array<string>
     */
    #[Serializer\Type('array<string>')]
    public array $roles = [];
}
