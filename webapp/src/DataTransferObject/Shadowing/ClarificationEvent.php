<?php declare(strict_types=1);

namespace App\DataTransferObject\Shadowing;

class ClarificationEvent implements EventData
{
    public function __construct(
        public readonly string $id,
        public readonly string $text,
        public readonly string $time,
        public readonly string $contestTime,
        public readonly ?string $fromTeamId,
        public readonly ?string $toTeamId,
        public readonly ?string $replyToId,
        public readonly ?string $problemId,
    ) {}
}
