<?php declare(strict_types=1);

namespace App\DataTransferObject;

use OpenApi\Attributes as OA;

#[OA\Schema(required: ['name'])]
readonly class PrintTeam
{
    public function __construct(
        #[OA\Property(description: 'The original name of the file')]
        public string  $originalName,
        #[OA\Property(description: 'The programming language of the file contents', nullable: true)]
        public ?string $language,
        #[OA\Property(description: 'The (base64-encoded) contents of the source file', format: 'binary')]
        public string  $fileContents,
    ) {}
}
