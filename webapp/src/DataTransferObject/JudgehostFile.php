<?php declare(strict_types=1);

namespace App\DataTransferObject;

readonly class JudgehostFile
{
    public function __construct(
        public string $filename,
        public string $content,
        public bool   $isExecutable = false,
    ) {}
}
