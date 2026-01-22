<?php declare(strict_types=1);

namespace App\DataTransferObject;

use App\Entity\ContestProblem;
use JMS\Serializer\Annotation as Serializer;

/**
 * This class is used to output the ordinal of a contest problem.
 */
readonly class OrdinalContestProblemWrapper
{
    public function __construct(
        #[Serializer\SerializedName('ordinal')]
        protected int                                  $ordinal,
        #[Serializer\Inline]
        protected ContestProblemWrapper|ContestProblem $item
    ) {}

    public function getContestProblemWrapper(): ContestProblemWrapper|ContestProblem
    {
        return $this->item;
    }
}
