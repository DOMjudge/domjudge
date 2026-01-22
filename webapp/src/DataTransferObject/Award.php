<?php declare(strict_types=1);

namespace App\DataTransferObject;

use JMS\Serializer\Annotation as Serializer;

readonly class Award
{
    /**
     * @param string[] $teamIds
     */
    public function __construct(
        public string  $id,
        public ?string $citation,
        #[Serializer\Type('array<string>')]
        public array   $teamIds,
    ) {}
}
