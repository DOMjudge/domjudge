<?php declare(strict_types=1);

namespace App\DataTransferObject\Scoreboard;

use JMS\Serializer\Annotation as Serializer;

class Row
{
    /**
     * @param Problem[] $problems
     */
    public function __construct(
        public readonly int $rank,
        public readonly string $teamId,
        public readonly Score $score,
        #[Serializer\Type("array<App\DataTransferObject\Scoreboard\Problem>")]
        public readonly array $problems,
    ) {}
}
