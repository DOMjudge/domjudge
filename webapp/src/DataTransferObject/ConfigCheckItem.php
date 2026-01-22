<?php declare(strict_types=1);

namespace App\DataTransferObject;

use JMS\Serializer\Annotation as Serializer;

readonly class ConfigCheckItem
{
    public function __construct(
        public string  $caption,
        public string  $result,
        public ?string $desc,
        #[Serializer\Exclude]
        public bool    $escape = true,
    ) {}
}
