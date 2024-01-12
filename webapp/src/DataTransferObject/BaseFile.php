<?php declare(strict_types=1);

namespace App\DataTransferObject;

class BaseFile
{
    public function __construct(
        public string $href,
        public string $mime,
    ) {}
}
