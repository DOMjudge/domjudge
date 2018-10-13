<?php declare(strict_types=1);

namespace DOMJudgeBundle\Helpers;

use DOMJudgeBundle\Entity\Judging;
use JMS\Serializer\Annotation as Serializer;

class JudgingWrapper
{
    /**
     * @var Judging
     * @Serializer\Inline()
     */
    protected $judging;

    /**
     * @var float
     * @Serializer\SerializedName("max_run_time")
     */
    protected $maxRunTime;

    /**
     * @var string
     * @Serializer\SerializedName("judgement_type_id")
     */
    protected $judgementTypeId;

    /**
     * JudgingWrapper constructor.
     * @param Judging $judging
     * @param float|null $maxRunTime
     * @param string|null $judgementTypeId
     */
    public function __construct(Judging $judging, float $maxRunTime = null, string $judgementTypeId = null)
    {
        $this->judging         = $judging;
        $this->maxRunTime      = $maxRunTime;
        $this->judgementTypeId = $judgementTypeId;
    }
}
