<?php declare(strict_types=1);

namespace App\DataTransferObject\Shadowing;

readonly class ClarificationEvent implements EventData
{
    /**
     * @param list<string>|null $toTeamIds  Since 2026-01, replacing $toTeamId
     * @param list<string>|null $toGroupIds Since 2026-01
     */
    public function __construct(
        public string  $id,
        public string  $text,
        public string  $time,
        public ?string $fromTeamId,
        public ?string $toTeamId,
        public ?string $replyToId,
        public ?string $problemId,
        public ?array  $toTeamIds = null,
        public ?array  $toGroupIds = null,
    ) {}
}
