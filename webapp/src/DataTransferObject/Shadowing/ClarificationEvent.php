<?php declare(strict_types=1);

namespace App\DataTransferObject\Shadowing;

readonly class ClarificationEvent implements EventData
{
    public function __construct(
        public string  $id,
        public string  $text,
        public string  $time,
        public ?string $fromTeamId,
        public ?string $toTeamId,
        public ?string $replyToId,
        public ?string $problemId,
    ) {}
}
