<?php declare(strict_types=1);

namespace App\DataTransferObject\Shadowing;

class LanguageEvent implements EventData
{
    public function __construct(
        public readonly string $id,
    ) {}
}
