<?php declare(strict_types=1);

namespace App\DataTransferObject\Shadowing;

class SubmissionFile
{
    public function __construct(
        public readonly string $href,
        public readonly ?string $mime,
        public readonly ?string $filename,
    ) {}
}
