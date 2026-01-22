<?php declare(strict_types=1);

namespace App\DataTransferObject;

readonly class ApiVersion
{
    public function __construct(
        public int $apiVersion
    ) {}
}
