<?php declare(strict_types=1);

namespace App\DataTransferObject;

class ImageFile extends FileWithName
{
    public function __construct(
        string $href,
        string $mime,
        string $filename,
        public readonly int $width,
        public readonly int $height,
    ) {
        parent::__construct($href, $mime, $filename);
    }
}
