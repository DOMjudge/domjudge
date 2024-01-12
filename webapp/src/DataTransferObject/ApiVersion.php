<?php declare(strict_types=1);

namespace App\DataTransferObject;

class ApiVersion
{
    public function __construct(
        public readonly int $apiVersion
    ) {}
}
