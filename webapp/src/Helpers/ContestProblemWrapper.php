<?php declare(strict_types=1);

namespace App\Helpers;

use App\Entity\ContestProblem;
use JMS\Serializer\Annotation as Serializer;

class ContestProblemWrapper
{
    public function __construct(
        #[Serializer\Inline]
        protected readonly ContestProblem $contestProblem,
        #[Serializer\SerializedName('test_data_count')]
        protected readonly int $testDataCount
    ) {}

    public function getContestProblem(): ContestProblem
    {
        return $this->contestProblem;
    }
}
