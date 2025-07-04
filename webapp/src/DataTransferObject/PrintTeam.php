<?php declare(strict_types=1);

namespace App\DataTransferObject;

use OpenApi\Attributes as OA;

#[OA\Schema(required: ['name'])]
class PrintTeam
{
    public function __construct(
        #[OA\Property(description: 'The original name of the file')]
        public readonly string $originalName,
        #[OA\Property(description: 'The programming language of the file contents', nullable: true)]
        public readonly ?string $language,
        #[OA\Property(description: 'The (base64-encoded) contents of the source file', format: 'binary')]
        public readonly string $fileContents,
    ) {}
}
