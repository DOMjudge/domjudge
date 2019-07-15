<?php declare(strict_types=1);

namespace App\Helpers;

use App\Entity\ContestProblem;
use JMS\Serializer\Annotation as Serializer;

class ContestProblemWrapper
{
    /**
     * @var ContestProblem
     * @Serializer\Inline()
     */
    protected $contestProblem;

    /**
     * @var int
     * @Serializer\SerializedName("test_data_count")
     */
    protected $testDataCount;

    /**
     * ProblemWrapper constructor.
     * @param ContestProblem $contestProblem
     * @param int $testDataCount
     */
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
