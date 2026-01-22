<?php declare(strict_types=1);

namespace App\DataTransferObject\Shadowing;

readonly class LanguageEvent implements EventData
{
    /**
     * @param list<string>|null $extensions
     */
    public function __construct(
        public string $id,
        public ?array $extensions = null,
    ) {}
}
