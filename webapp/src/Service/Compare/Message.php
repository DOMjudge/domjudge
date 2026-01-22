<?php declare(strict_types=1);

namespace App\Service\Compare;

readonly class Message
{
    public function __construct(
        public MessageType $type,
        public string      $message,
        public ?string     $source = null,
        public ?string     $target = null,
    ) {}
}
