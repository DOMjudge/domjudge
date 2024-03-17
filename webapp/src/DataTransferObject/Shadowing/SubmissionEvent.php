<?php declare(strict_types=1);

namespace App\DataTransferObject\Shadowing;

class SubmissionEvent implements EventData
{
    /**
     * @param SubmissionFile[] $files
     */
    public function __construct(
        public readonly string $id,
        public readonly string $languageId,
        public readonly string $problemId,
        public readonly string $teamId,
        public readonly string $time,
        public readonly ?string $entryPoint,
        // For the analyst instance we lose access to the files
        // during the freeze.
        public readonly ?array $files,
    ) {}
}
