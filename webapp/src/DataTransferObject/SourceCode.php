<?php declare(strict_types=1);

namespace App\DataTransferObject;

readonly class SourceCode
{
    public function __construct(
        public string $id,
        public string $submissionId,
        public string $filename,
        public string $source,
    ) {}
}
