<?php declare(strict_types=1);

namespace App\DataTransferObject;

use JMS\Serializer\Annotation as Serializer;

class ConfigCheckItem
{
    public function __construct(
        public readonly string $caption,
        public readonly string $result,
        public readonly ?string $desc,
        #[Serializer\Exclude]
        public readonly bool $escape = true,
    ) {}
}
