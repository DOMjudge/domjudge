<?php declare(strict_types=1);

namespace App\DataTransferObject\Scoreboard;

use App\Controller\API\AbstractRestController as ARC;
use App\DataTransferObject\ContestState;
use JMS\Serializer\Annotation as Serializer;

class Scoreboard
{
    /**
     * @param Row[] $rows
     */
    public function __construct(
        #[Serializer\Groups([ARC::GROUP_NONSTRICT])]
        public readonly ?string $eventId = null,
        public readonly ?string $time = null,
        public readonly ?string $contestTime = null,
        public readonly ?ContestState $state = null,
        #[Serializer\Type("array<App\DataTransferObject\Scoreboard\Row>")]
        public array $rows = [],
    ) {}
}
