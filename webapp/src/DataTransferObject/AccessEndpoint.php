<?php declare(strict_types=1);

namespace App\DataTransferObject;

use JMS\Serializer\Annotation as Serializer;

readonly class AccessEndpoint
{
    /**
     * @param string[] $properties
     */
    public function __construct(
        public string $type,
        #[Serializer\Type('array<string>')]
        public array  $properties,
    ) {}
}
