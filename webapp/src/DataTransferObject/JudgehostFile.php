<?php declare(strict_types=1);

namespace App\DataTransferObject;

class JudgehostFile
{
    public function __construct(
        public readonly string $filename,
        public readonly string $content,
        public readonly bool $isExecutable = false,
    ) {}
}
