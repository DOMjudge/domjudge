<?php declare(strict_types=1);

namespace App\DataTransferObject\Scoreboard;

use JMS\Serializer\Annotation as Serializer;

readonly class Row
{
    /**
     * @param Problem[] $problems
     */
    public function __construct(
        public int    $rank,
        public string $teamId,
        public Score  $score,
        #[Serializer\Type("array<App\DataTransferObject\Scoreboard\Problem>")]
        public array  $problems,
    ) {}
}
