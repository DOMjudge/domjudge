<?php declare(strict_types=1);

namespace DOMJudgeBundle\Utils\Scoreboard;

class Summary
{
    /**
     * @var int
     */
    protected $numberOfPoints = 0;

    /**
     * @var int[]
     */
    protected $affiliations = [];

    /**
     * @var int[]
     */
    protected $countries = [];

    /**
     * @var ProblemSummary[]
     */
    protected $problems = [];

    /**
     * Summary constructor.
     * @param array $problems
     */
    public function __construct(array $problems)
    {
        foreach (array_keys($problems) as $problemId) {
            $this->problems[$problemId] = new ProblemSummary();
        }
    }

    /**
     * @return int
     */
    public function getNumberOfPoints(): int
    {
        return $this->numberOfPoints;
    }

    /**
     * @param int $numberOfPoints
     */
    public function addNumberOfPoints(int $numberOfPoints)
    {
        $this->numberOfPoints += $numberOfPoints;
    }

    /**
     * @return int[]
     */
    public function getAffiliations(): array
    {
        return $this->affiliations;
    }

    /**
     * @param int $affiliationId
     */
    public function incrementAffiliationValue(int $affiliationId)
    {
        if (!isset($this->affiliations[$affiliationId])) {
            $this->affiliations[$affiliationId] = 0;
        }
        $this->affiliations[$affiliationId]++;
    }

    /**
     * @return int[]
     */
    public function getCountries(): array
    {
        return $this->countries;
    }

    /**
     * @param string $country
     */
    public function incrementCountryValue(string $country)
    {
        if (!isset($this->countries[$country])) {
            $this->countries[$country] = 0;
        }
        $this->countries[$country]++;
    }

    /**
     * @return ProblemSummary[]
     */
    public function getProblems(): array
    {
        return $this->problems;
    }

    /**
     * @param $problemId
     * @return ProblemSummary|null
     */
    public function getProblem($problemId)
    {
        return $this->problems[$problemId] ?? null;
    }

    /**
     * @param ProblemSummary[] $problems
     */
    public function setProblems(array $problems)
    {
        $this->problems = $problems;
    }
}
