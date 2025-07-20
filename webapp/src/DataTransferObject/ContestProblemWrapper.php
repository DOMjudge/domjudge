<?php declare(strict_types=1);

namespace App\DataTransferObject;

use App\Controller\API\AbstractRestController as ARC;
use App\Entity\ContestProblem;
use JMS\Serializer\Annotation as Serializer;

class ContestProblemWrapper
{
    public function __construct(
        #[Serializer\Inline]
        protected readonly ContestProblem $contestProblem,
        #[Serializer\Groups([ARC::GROUP_NONSTRICT, '2025-draft'])]
        protected readonly int $memoryLimit,
        #[Serializer\Groups([ARC::GROUP_NONSTRICT, '2025-draft'])]
        protected readonly int $outputLimit,
        #[Serializer\Groups([ARC::GROUP_NONSTRICT, '2025-draft'])]
        protected readonly int $codeLimit,
        #[Serializer\SerializedName('test_data_count')]
        protected readonly int $testDataCount
    ) {}

    public function getContestProblem(): ContestProblem
    {
        return $this->contestProblem;
    }
}
