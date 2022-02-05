<?php declare(strict_types=1);

namespace App\Helpers;

use App\Entity\Judging;
use App\Utils\Utils;
use JMS\Serializer\Annotation as Serializer;

class JudgingWrapper
{
    /** @Serializer\Inline() */
    protected Judging $judging;

    /** @Serializer\Exclude() */
    protected ?float $maxRunTime;

    /** @Serializer\SerializedName("judgement_type_id") */
    protected ?string $judgementTypeId;

    public function __construct(Judging $judging, ?float $maxRunTime = null, ?string $judgementTypeId = null)
    {
        $this->judging         = $judging;
        $this->maxRunTime      = $maxRunTime;
        $this->judgementTypeId = $judgementTypeId;
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("max_run_time")
     * @Serializer\Type("float")
     */
    public function getMaxRunTime(): ?float
    {
        return Utils::roundedFloat($this->maxRunTime);
    }
}
