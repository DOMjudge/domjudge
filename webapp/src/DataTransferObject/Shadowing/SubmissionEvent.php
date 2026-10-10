<?php declare(strict_types=1);

namespace App\DataTransferObject\Shadowing;

readonly class SubmissionEvent implements EventData
{
    /**
     * @param SubmissionFile[] $files
     */
    public function __construct(
        public string  $id,
        public string  $languageId,
        public string  $problemId,
        // Optional since 2026-01, which added account_id as an alternative.
        public ?string $teamId,
        public string  $time,
        public ?string $entryPoint,
        // For the analyst instance we lose access to the files
        // during the freeze.
        public ?array  $files,
        public ?string $accountId,
    ) {}
}
