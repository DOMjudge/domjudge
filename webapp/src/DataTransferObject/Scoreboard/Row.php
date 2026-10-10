<?php declare(strict_types=1);

namespace App\DataTransferObject\Scoreboard;

use App\Controller\API\AbstractRestController as ARC;
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
        #[Serializer\Groups([ARC::GROUP_NONSTRICT])]
        #[Serializer\Exclude(if: 'object.medal === null')]
        public ?string $medal,
        #[Serializer\Type("array<App\DataTransferObject\Scoreboard\Problem>")]
        public array  $problems,
    ) {}
}
