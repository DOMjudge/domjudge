<?php declare(strict_types=1);

namespace App\Utils\Scoreboard;

class Summary
{
    /**
     * @var int[]
     */
    protected $numberOfPoints = [];

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
     * @param int $sortorder
     * @return int
     */
    public function getNumberOfPoints(int $sortorder): int
    {
        return $this->numberOfPoints[$sortorder] ?? 0;
    }

    /**
     * @param int $sortorder
     * @param int $numberOfPoints
     */
    public function addNumberOfPoints(int $sortorder, int $numberOfPoints)
    {
        if (!isset($this->numberOfPoints[$sortorder])) {
            $this->numberOfPoints[$sortorder] = 0;
        }
        $this->numberOfPoints[$sortorder] += $numberOfPoints;
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
}
