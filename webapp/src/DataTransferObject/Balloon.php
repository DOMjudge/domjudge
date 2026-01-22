<?php declare(strict_types=1);

namespace App\DataTransferObject;

use App\Entity\ContestProblem;
use JMS\Serializer\Annotation as Serializer;

readonly class Balloon
{
    /**
     * @param array<string,ContestProblem> $total
     */
    public function __construct(
        public int            $balloonid,
        public string         $time,
        public string         $problem,
        public ContestProblem $contestproblem,
        public string         $team,
        public string         $teamid,
        public ?string        $location,
        public ?string        $affiliation,
        public ?string        $affiliationid,
        public ?string        $category,
        public ?string        $categoryid,
        #[Serializer\Type('array<string, App\Entity\ContestProblem>')]
        public array          $total,
        public bool           $done,
    ) {}
}
