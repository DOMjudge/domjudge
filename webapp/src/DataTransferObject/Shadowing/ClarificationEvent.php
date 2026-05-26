<?php declare(strict_types=1);

namespace App\DataTransferObject\Shadowing;

readonly class ClarificationEvent implements EventData
{
    /**
     * @param list<string>|null $toTeamIds CCS 2026-01+ recipient list. We only model single
     *                                     recipients, so callers should use the first entry.
     */
    public function __construct(
        public string  $id,
        public string  $text,
        public string  $time,
        public ?string $fromTeamId,
        public ?string $toTeamId,
        public ?string $replyToId,
        public ?string $problemId,
        public ?array  $toTeamIds,
    ) {}
}
