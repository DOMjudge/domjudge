<?php declare(strict_types=1);

namespace App\DataTransferObject;

use JMS\Serializer\Annotation as Serializer;

readonly class ApiInfoProvider
{
    public function __construct(
        public string  $name,
        public string  $version,
        #[Serializer\Exclude(if: '!object.buildDate')]
        public ?string $buildDate = null,
    ) {}
}
