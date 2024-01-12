<?php declare(strict_types=1);

namespace App\DataTransferObject;

use JMS\Serializer\Annotation as Serializer;

class Command
{
    public function __construct(
        #[Serializer\Exclude(if: 'object.version == null')]
        public ?string $version = null,
        #[Serializer\Exclude(if: 'object.versionCommand == null')]
        public ?string $versionCommand = null,
    ) {}
}
