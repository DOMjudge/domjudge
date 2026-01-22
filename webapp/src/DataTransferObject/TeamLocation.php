<?php declare(strict_types=1);

namespace App\DataTransferObject;

readonly class TeamLocation
{
    public function __construct(
        public string $description
    ) {}
}
