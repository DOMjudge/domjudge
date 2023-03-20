<?php declare(strict_types=1);

namespace App\Utils\Scoreboard;

class Filter
{
    /**
     * @param int[] $affiliations
     * @param string[] $countries
     * @param int[] $categories
     * @param int[] $teams
     */
    public function __construct(
        public array $affiliations = [],
        public array $countries = [],
        public array $categories = [],
        public array $teams = []
    )
    {
    }

    /**
     * Get a string to display on what has been filtered.
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

        return implode(', ', $filteredOn);
    }
}
