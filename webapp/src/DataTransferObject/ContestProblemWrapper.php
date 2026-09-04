<?php declare(strict_types=1);

namespace App\DataTransferObject;

use App\Controller\API\AbstractRestController as ARC;
use App\Entity\ContestProblem;
use App\Utils\CcsApiVersion;
use JMS\Serializer\Annotation as Serializer;

readonly class ContestProblemWrapper
{
    public function __construct(
        #[Serializer\Inline]
        protected ContestProblem $contestProblem,
        #[Serializer\Groups([ARC::GROUP_NONSTRICT, CcsApiVersion::Format_2026_01->value])]
        protected int $memoryLimit,
        #[Serializer\Groups([ARC::GROUP_NONSTRICT, CcsApiVersion::Format_2026_01->value])]
        protected int $outputLimit,
        #[Serializer\Groups([ARC::GROUP_NONSTRICT, CcsApiVersion::Format_2026_01->value])]
        protected int $codeLimit,
        #[Serializer\SerializedName('test_data_count')]
        protected int            $testDataCount
    ) {}

    public function getContestProblem(): ContestProblem
    {
        return $this->contestProblem;
    }
}
