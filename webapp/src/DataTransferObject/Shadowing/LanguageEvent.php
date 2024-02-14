<?php declare(strict_types=1);

namespace App\DataTransferObject\Shadowing;

class LanguageEvent implements EventData
{
    /**
     * @param string[] $extensions
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?bool $entryPointRequired,
        public readonly ?string $entryPointName,
        public readonly array $extensions,
    ) {}
}
