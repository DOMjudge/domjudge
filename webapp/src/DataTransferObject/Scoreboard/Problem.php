<?php declare(strict_types=1);

namespace App\DataTransferObject\Scoreboard;

use App\Controller\API\AbstractRestController as ARC;
use JMS\Serializer\Annotation as Serializer;

class Problem
{
    public function __construct(
        #[Serializer\Groups([ARC::GROUP_NONSTRICT])]
        public readonly ?string $label,
        public readonly string $problemId,
        public readonly int $numJudged,
        public readonly int $numPending,
        public readonly bool $solved,
        #[Serializer\Exclude(if: 'object.time === null')]
        public ?int $time = null,
        #[Serializer\Groups([ARC::GROUP_NONSTRICT])]
        #[Serializer\Exclude(if: 'object.firstToSolve === null')]
        public ?bool $firstToSolve = null,
        #[Serializer\Groups([ARC::GROUP_NONSTRICT])]
        #[Serializer\Exclude(if: 'object.runtime === null')]
        public ?int $runtime = null,
        #[Serializer\Groups([ARC::GROUP_NONSTRICT])]
        #[Serializer\Exclude(if: 'object.fastestSubmission === null')]
        public ?bool $fastestSubmission = null,
    ) {}
}
