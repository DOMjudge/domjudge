<?php declare(strict_types=1);

namespace App\DataTransferObject;

use JMS\Serializer\Annotation as Serializer;

class Award
{
    /**
     * @param string[] $teamIds
     */
    public function __construct(
        public readonly string $id,
        public readonly ?string $citation,
        #[Serializer\Type('array<string>')]
        public readonly array $teamIds,
    ) {}
}
