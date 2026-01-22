<?php declare(strict_types=1);

namespace App\DataTransferObject;

use App\Entity\ContestProblem;
use JMS\Serializer\Annotation as Serializer;

readonly class ContestProblemWrapper
{
    public function __construct(
        #[Serializer\Inline]
        protected ContestProblem $contestProblem,
        #[Serializer\SerializedName('test_data_count')]
        protected int            $testDataCount
    ) {}

    public function getContestProblem(): ContestProblem
    {
        return $this->contestProblem;
    }
}
