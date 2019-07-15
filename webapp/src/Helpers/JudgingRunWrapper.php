<?php declare(strict_types=1);

namespace App\Helpers;

use App\Entity\JudgingRun;
use JMS\Serializer\Annotation as Serializer;

class JudgingRunWrapper
{
    /**
     * @var JudgingRun
     * @Serializer\Inline()
     */
    protected $judgingRun;

    /**
     * @var string
     * @Serializer\SerializedName("judgement_type_id")
     */
    protected $judgementTypeId;

    /**
     * JudgingWrapper constructor.
     * @param JudgingRun $judgingRun
     * @param string|null $judgementTypeId
     */
    public function __construct(JudgingRun $judgingRun, string $judgementTypeId = null)
    {
        $this->judgingRun      = $judgingRun;
        $this->judgementTypeId = $judgementTypeId;
    }
}
