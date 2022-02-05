<?php declare(strict_types=1);

namespace App\Helpers;

use App\Entity\ContestProblem;
use JMS\Serializer\Annotation as Serializer;

class ContestProblemWrapper
{
    /** @Serializer\Inline() */
    protected ContestProblem $contestProblem;

    /** @Serializer\SerializedName("test_data_count") */
    protected int $testDataCount;

    public function __construct(ContestProblem $contestProblem, int $testDataCount)
    {
        $this->contestProblem = $contestProblem;
        $this->testDataCount = $testDataCount;
    }

    /**
     * @return ContestProblem
     */
    public function getContestProblem(): ContestProblem
    {
        return $this->contestProblem;
    }
}
