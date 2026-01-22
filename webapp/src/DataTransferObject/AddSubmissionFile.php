<?php declare(strict_types=1);

namespace App\DataTransferObject;

use OpenApi\Attributes as OA;

readonly class AddSubmissionFile
{
    public function __construct(
        #[OA\Property(description: 'The base64 encoded submission files')]
        public string  $data,
        #[OA\Property(description: 'The mime type of the file. Should be application/zip', nullable: true)]
        public ?string $mime,
    ) {}
}
