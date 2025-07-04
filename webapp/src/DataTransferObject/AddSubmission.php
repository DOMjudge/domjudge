<?php declare(strict_types=1);

namespace App\DataTransferObject;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class AddSubmission
{
    /**
     * @param AddSubmissionFile[]|null $files
     * @param string[]|null            $code
     */
    public function __construct(
        #[OA\Property(description: 'The problem to submit a solution for', nullable: true)]
        public readonly ?string $problem = null,
        #[OA\Property(description: 'The problem to submit a solution for', nullable: true)]
        public readonly ?string $problemId = null,
        #[OA\Property(description: 'The language to submit a solution in', nullable: true)]
        public readonly ?string $language = null,
        #[OA\Property(description: 'The language to submit a solution in', nullable: true)]
        public readonly ?string $languageId = null,
        #[OA\Property(description: 'The team to submit a solution for. Only used when adding a submission as admin', nullable: true)]
        public readonly ?string $teamId = null,
        #[OA\Property(description: 'The user to submit a solution for. Only used when adding a submission as admin', nullable: true)]
        public readonly ?string $userId = null,
        #[OA\Property(description: 'The time to use for the submission. Only used when adding a submission as admin', format: 'date-time', nullable: true)]
        public readonly ?string $time = null,
        // Code is not here, since it is a file upload handled in the controller itself
        #[OA\Property(description: 'The entry point for the submission. Required for languages requiring an entry point', nullable: true)]
        public readonly ?string $entryPoint = null,
        #[OA\Property(description: 'The ID to use for the submission. Only used when adding a submission as admin and only allowed with PUT', nullable: true)]
        public readonly ?string $id = null,
        #[OA\Property(description: 'The base64 encoded ZIP file to submit', type: 'array', items: new OA\Items(ref: new Model(type: AddSubmissionFile::class)), maxItems: 1, minItems: 1, nullable: true)]
        public readonly ?array $files = null,
        #[OA\Property(description: 'The file(s) to submit', type: 'array', items: new OA\Items(type: 'string', format: 'binary'), nullable: true)]
        public readonly ?array $code = null,
    ) {}

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        if (!$this->problem && !$this->problemId) {
            $context
                ->buildViolation("One of the arguments 'problem', 'problem_id' is required")
                ->atPath('problem')
                ->addViolation();
            $context
                ->buildViolation("One of the arguments 'problem', 'problem_id' is required")
                ->atPath('problem_id')
                ->addViolation();
        }
        if (!$this->language && !$this->languageId) {
            $context
                ->buildViolation("One of the arguments 'language', 'language_id' is required")
                ->atPath('language')
                ->addViolation();
            $context
                ->buildViolation("One of the arguments 'language', 'language_id' is required")
                ->atPath('language_id')
                ->addViolation();
        }
    }
}
