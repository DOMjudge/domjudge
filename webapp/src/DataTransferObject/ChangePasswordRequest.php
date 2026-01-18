<?php declare(strict_types=1);

namespace App\DataTransferObject;

use JMS\Serializer\Annotation as Serializer;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(required: ['current_password', 'new_password'])]
class ChangePasswordRequest
{
    public function __construct(
        #[OA\Property(format: 'password', description: 'The current password of the user')]
        #[Assert\NotBlank]
        public readonly string $currentPassword,
        #[OA\Property(format: 'password', description: 'The new password for the user')]
        #[Assert\NotBlank]
        public readonly string $newPassword,
    ) {}
}
