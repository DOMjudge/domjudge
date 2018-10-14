<?php declare(strict_types=1);

namespace DOMJudgeBundle\Utils\Scoreboard;

class Filter
{
    /**
     * @var int[]
     */
    protected $affiliations = [];

    /**
     * @var string[]
     */
    protected $countries = [];

    /**
     * @var int[]
     */
    protected $categories = [];

    /**
     * @var int[]
     */
    protected $teams = [];

    /**
     * Filter constructor.
     * @param int[] $affiliations
     * @param string[] $countries
     * @param int[] $categories
     * @param int[] $teams
     */
    public function __construct(array $affiliations, array $countries, array $categories, array $teams)
    {
        $this->affiliations = $affiliations;
        $this->countries    = $countries;
        $this->categories   = $categories;
        $this->teams        = $teams;
    }

    /**
     * @return int[]
     */
    public function getAffiliations(): array
    {
        return $this->affiliations;
    }

    /**
     * @return string[]
     */
    public function getCountries(): array
    {
        return $this->countries;
    }

    /**
     * @return int[]
     */
    public function getCategories(): array
    {
        return $this->categories;
    }

    /**
     * @return int[]
     */
    public function getTeams(): array
    {
        return $this->teams;
    }
}
