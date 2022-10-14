<?php declare(strict_types=1);

namespace App\Utils\Scoreboard;

class Filter
{
    /** int[] */
    public array $affiliations = [];

    /** @var string[] */
    public array $countries = [];

    /** @var int[] */
    public array $categories = [];

    /** @var int[] */
    public array $teams = [];

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
