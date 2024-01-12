<?php declare(strict_types=1);

namespace App\DataTransferObject;

use JMS\Serializer\Annotation as Serializer;

class Access
{
    /**
     * @param string[]         $capabilities
     * @param AccessEndpoint[] $endpoints
     */
    public function __construct(
        #[Serializer\Type('array<string>')]
        public array $capabilities,
        #[Serializer\Type('array<App\DataTransferObject\AccessEndpoint>')]
        public array $endpoints
    ) {}
}
