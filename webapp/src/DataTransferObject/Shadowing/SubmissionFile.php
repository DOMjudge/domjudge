<?php declare(strict_types=1);

namespace App\DataTransferObject\Shadowing;

readonly class SubmissionFile
{
    public function __construct(
        public string  $href,
        public ?string $mime,
        public ?string $filename,
    ) {}
}
