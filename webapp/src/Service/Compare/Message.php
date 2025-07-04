<?php declare(strict_types=1);

namespace App\Service\Compare;

class Message
{
    public function __construct(
        public readonly MessageType $type,
        public readonly string $message,
        public readonly ?string $source = null,
        public readonly ?string $target = null,
    ) {}
}
