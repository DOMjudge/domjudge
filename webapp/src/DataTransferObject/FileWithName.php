<?php declare(strict_types=1);

namespace App\DataTransferObject;

class FileWithName extends BaseFile
{
    public function __construct(
        string $href,
        string $mime,
        public string $filename,
    ) {
        parent::__construct($href, $mime);
    }
}
