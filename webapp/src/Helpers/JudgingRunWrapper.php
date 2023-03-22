<?php declare(strict_types=1);

namespace App\Helpers;

use App\Entity\JudgingRun;
use JMS\Serializer\Annotation as Serializer;

class JudgingRunWrapper
{
    public function __construct(
        /** @Serializer\Inline() */
        protected readonly JudgingRun $judgingRun,
        /** @Serializer\SerializedName("judgement_type_id") */
        protected readonly ?string $judgementTypeId = null
    ) {
    }
}
