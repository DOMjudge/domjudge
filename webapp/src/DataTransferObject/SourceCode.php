<?php declare(strict_types=1);

namespace App\DataTransferObject;

class SourceCode
{
    public function __construct(
        public readonly string $id,
        public readonly string $submissionId,
        public readonly string $filename,
        public readonly string $source,
    ) {}
}
