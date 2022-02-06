<?php declare(strict_types=1);

namespace App\Helpers;

use App\Entity\JudgingRun;
use JMS\Serializer\Annotation as Serializer;

class JudgingRunWrapper
{
    /** @Serializer\Inline() */
    protected JudgingRun $judgingRun;

    /** @Serializer\SerializedName("judgement_type_id") */
    protected ?string $judgementTypeId;

    public function __construct(JudgingRun $judgingRun, string $judgementTypeId = null)
    {
        $this->judgingRun      = $judgingRun;
        $this->judgementTypeId = $judgementTypeId;
    }
}
