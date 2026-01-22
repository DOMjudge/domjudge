<?php declare(strict_types=1);

namespace App\DataTransferObject\Scoreboard;

use App\Controller\API\AbstractRestController as ARC;
use JMS\Serializer\Annotation as Serializer;

readonly class Score
{
    public function __construct(
        public int  $numSolved,
        #[Serializer\Exclude(if: 'object.totalTime === null')]
        public ?int $totalTime = null,
        #[Serializer\Exclude(if: 'object.totalRuntime === null')]
        #[Serializer\Groups([ARC::GROUP_NONSTRICT])]
        public ?int $totalRuntime = null,
    ) {}
}
