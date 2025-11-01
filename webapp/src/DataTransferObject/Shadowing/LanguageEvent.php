<?php declare(strict_types=1);

namespace App\DataTransferObject\Shadowing;

class LanguageEvent implements EventData
{
    /**
     * @param list<string>|null $extensions
     */
    public function __construct(
        public readonly string $id,
        public readonly ?array $extensions = null,
    ) {}
}
