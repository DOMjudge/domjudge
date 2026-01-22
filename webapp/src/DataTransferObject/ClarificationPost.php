<?php declare(strict_types=1);

namespace App\DataTransferObject;

use OpenApi\Attributes as OA;

#[OA\Schema(required: ['text'])]
readonly class ClarificationPost
{
    public function __construct(
        #[OA\Property(description: 'The body of the clarification to send')]
        public string  $text,
        #[OA\Property(description: 'The problem the clarification is for', nullable: true)]
        public ?string $problemId,
        #[OA\Property(description: 'The ID of the clarification this clarification is a reply to', nullable: true)]
        public ?string $replyToId,
        #[OA\Property(description: 'The team the clarification came from. Only used when adding a clarification as admin', nullable: true)]
        public ?string $fromTeamId,
        #[OA\Property(description: 'The team the clarification must be sent to. Only used when adding a clarification as admin', nullable: true)]
        public ?string $toTeamId,
        #[OA\Property(description: 'The time to use for the clarification. Only used when adding a clarification as admin', format: 'date-time', nullable: true)]
        public ?string $time,
        #[OA\Property(description: 'The ID to use for the clarification. Only used when adding a clarification as admin and only allowed with PUT', nullable: true)]
        public ?string $id,
    ) {}
}
