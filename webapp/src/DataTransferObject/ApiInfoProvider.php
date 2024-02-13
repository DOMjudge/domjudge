<?php declare(strict_types=1);

namespace App\DataTransferObject;

use JMS\Serializer\Annotation as Serializer;

class ApiInfoProvider
{
    public function __construct(
        public readonly string $name,
        public readonly string $version,
        #[Serializer\Exclude(if: '!object.buildDate')]
        public readonly ?string $buildDate = null,
    ) {}
}
