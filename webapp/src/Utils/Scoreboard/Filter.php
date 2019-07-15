<?php declare(strict_types=1);

namespace App\Utils\Scoreboard;

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
    public function __construct(
        array $affiliations = [],
        array $countries = [],
        array $categories = [],
        array $teams = []
    ) {
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
     * @param int[] $affiliations
     */
    public function setAffiliations(array $affiliations)
    {
        $this->affiliations = $affiliations;
    }

    /**
     * @return string[]
     */
    public function getCountries(): array
    {
        return $this->countries;
    }

    /**
     * @param string[] $countries
     */
    public function setCountries(array $countries)
    {
        $this->countries = $countries;
    }

    /**
     * @return int[]
     */
    public function getCategories(): array
    {
        return $this->categories;
    }

    /**
     * @param int[] $categories
     */
    public function setCategories(array $categories)
    {
        $this->categories = $categories;
    }

    /**
     * @return int[]
     */
    public function getTeams(): array
    {
        return $this->teams;
    }

    /**
     * @param int[] $teams
     */
    public function setTeams(array $teams)
    {
        $this->teams = $teams;
    }

    /**
     * Get a string to display on what has been filtered
     * @return string
     */
    public function getFilteredOn(): string
    {
        $filteredOn = [];
        if ($this->affiliations) {
            $filteredOn[] = 'affiliations';
        }
        if ($this->countries) {
            $filteredOn[] = 'countries';
        }
        if ($this->categories) {
            $filteredOn[] = 'categories';
        }
        if ($this->teams) {
            $filteredOn[] = 'teams';
        }

        if (empty($filteredOn)) {
            return '';
        }

        return implode(', ', $filteredOn);
    }
}
