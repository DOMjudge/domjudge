<?php declare(strict_types=1);

namespace App\DataTransferObject;

use App\Entity\ContestProblem;
use JMS\Serializer\Annotation as Serializer;

class Balloon
{
    /**
     * @param array<string,ContestProblem> $total
     */
    public function __construct(
        public readonly int $balloonid,
        public readonly string $time,
        public readonly string $problem,
        public readonly ContestProblem $contestproblem,
        public readonly string $team,
        public readonly int $teamid,
        public readonly ?string $location,
        public readonly ?string $affiliation,
        public readonly ?int $affiliationid,
        public readonly ?string $category,
        public readonly ?int $categoryid,
        #[Serializer\Type('array<string, App\Entity\ContestProblem>')]
        public readonly array $total,
        public readonly string $awards,
        public readonly bool $done,
    ) {}
}
