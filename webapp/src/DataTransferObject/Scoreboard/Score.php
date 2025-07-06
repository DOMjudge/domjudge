<?php declare(strict_types=1);

namespace App\DataTransferObject\Scoreboard;

use App\Controller\API\AbstractRestController as ARC;
use JMS\Serializer\Annotation as Serializer;

class Score
{
    public function __construct(
        public readonly int $numSolved,
        #[Serializer\Exclude(if: 'object.totalTime === null')]
        public readonly int|string|null $totalTime = null,
        #[Serializer\Exclude(if: 'object.time === null')]
        public int|string|null $time = null,
        #[Serializer\Exclude(if: 'object.totalRuntime === null')]
        #[Serializer\Groups([ARC::GROUP_NONSTRICT])]
        public readonly ?int $totalRuntime = null,
    ) {}
}
