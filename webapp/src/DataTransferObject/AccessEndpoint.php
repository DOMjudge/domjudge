<?php declare(strict_types=1);

namespace App\DataTransferObject;

use JMS\Serializer\Annotation as Serializer;

class AccessEndpoint
{
    /**
     * @param string[] $properties
     */
    public function __construct(
        public readonly string $type,
        #[Serializer\Type('array<string>')]
        public readonly array $properties,
    ) {}
}
