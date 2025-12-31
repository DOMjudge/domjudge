<?php declare(strict_types=1);

namespace App\Entity;

use App\Utils\Utils;
use JMS\Serializer\Annotation as Serializer;

/**
 * Common base class for run entities (JudgingRun and ExternalRun).
 */
abstract class AbstractRun extends BaseApiEntity
{
    /**
     * Get the run result/verdict.
     */
    abstract public function getResult(): ?string;

    /**
     * Get the runtime for this testcase.
     */
    abstract public function getRuntime(): string|float|null;

    /**
     * Get the end time of this run.
     */
    abstract public function getEndtime(): string|float|null;

    /**
     * Get the testcase for this run.
     */
    abstract public function getTestcase(): Testcase;

    /**
     * Get the contest for this run.
     */
    abstract public function getContest(): Contest;

    /**
     * Get the parent judgement ID as string (for API).
     */
    abstract public function getJudgementId(): string|int;

    #[Serializer\VirtualProperty]
    #[Serializer\SerializedName('ordinal')]
    #[Serializer\Type('int')]
    public function getTestcaseRank(): int
    {
        return $this->getTestcase()->getRank();
    }

    #[Serializer\VirtualProperty]
    #[Serializer\SerializedName('time')]
    #[Serializer\Type('string')]
    public function getAbsoluteEndTime(): string
    {
        return Utils::absTime($this->getEndtime());
    }

    #[Serializer\VirtualProperty]
    #[Serializer\SerializedName('contest_time')]
    #[Serializer\Type('string')]
    public function getRelativeEndTime(): string
    {
        return Utils::relTime($this->getEndtime() - $this->getContest()->getStarttime());
    }
}
