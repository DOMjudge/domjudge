<?php declare(strict_types=1);

namespace App\Utils\Scoreboard;

class Filter
{
    /**
     * @var int[]
     */
    public $affiliations = [];

    /**
     * @var string[]
     */
    public $countries = [];

    /**
     * @var int[]
     */
    public $categories = [];

    /**
     * @var int[]
     */
    public $teams = [];

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
     * Get a string to display on what has been filtered
     * @return string
     */
    public function getFilteredOn(): string
    {
        $filteredOn = [];
        if ($this->affiliations) $filteredOn[] = 'affiliations';
        if ($this->countries)    $filteredOn[] = 'countries';
        if ($this->categories)   $filteredOn[] = 'categories';
        if ($this->teams)        $filteredOn[] = 'teams';

        return implode(', ', $filteredOn);
    }
}
