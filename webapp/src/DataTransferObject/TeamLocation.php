<?php declare(strict_types=1);

namespace App\DataTransferObject;

class TeamLocation
{
    public function __construct(
        public readonly string $description
    ) {}
}
