<?php declare(strict_types=1);

namespace App\Entity;

use App\Utils\Utils;
use Doctrine\Common\Collections\Collection;
use JMS\Serializer\Annotation as Serializer;
use OpenApi\Attributes as OA;

/**
 * Common base class for judgement entities (Judging and ExternalJudgement).
 */
abstract class AbstractJudgement extends BaseApiEntity
{
    /**
     * Get the judgement result/verdict.
     */
    abstract public function getResult(): ?string;

    /**
     * Get the start time of this judgement.
     */
    abstract public function getStarttime(): string|float|null;

    /**
     * Get the end time of this judgement.
     */
    abstract public function getEndtime(): string|float|null;

    /**
     * Get whether this judgement is valid.
     */
    abstract public function getValid(): bool;

    /**
     * Get whether this judgement has been verified.
     */
    abstract public function getVerified(): bool;

    /**
     * Get the jury member who verified this judgement.
     */
    abstract public function getJuryMember(): ?string;

    /**
     * Get the verification comment.
     */
    abstract public function getVerifyComment(): ?string;

    /**
     * Get the submission for this judgement.
     */
    abstract public function getSubmission(): Submission;

    /**
     * Get the contest for this judgement.
     */
    abstract public function getContest(): ?Contest;

    /**
     * Get the runs for this judgement.
     *
     * @return Collection<int, AbstractRun>
     */
    abstract public function getRuns(): Collection;

    #[OA\Property(nullable: true)]
    #[Serializer\VirtualProperty]
    #[Serializer\SerializedName('start_time')]
    #[Serializer\Type('string')]
    public function getAbsoluteStartTime(): ?string
    {
        return Utils::absTime($this->getStarttime());
    }

    #[Serializer\VirtualProperty]
    #[Serializer\SerializedName('start_contest_time')]
    #[Serializer\Type('string')]
    public function getRelativeStartTime(): string
    {
        return Utils::relTime($this->getStarttime() - $this->getContest()->getStarttime());
    }

    #[OA\Property(nullable: true)]
    #[Serializer\VirtualProperty]
    #[Serializer\SerializedName('end_time')]
    #[Serializer\Type('string')]
    public function getAbsoluteEndTime(): ?string
    {
        return $this->getEndtime() ? Utils::absTime($this->getEndtime()) : null;
    }

    #[OA\Property(nullable: true)]
    #[Serializer\VirtualProperty]
    #[Serializer\SerializedName('end_contest_time')]
    #[Serializer\Type('string')]
    public function getRelativeEndTime(): ?string
    {
        return $this->getEndtime() ? Utils::relTime($this->getEndtime() - $this->getContest()->getStarttime()) : null;
    }

    #[Serializer\VirtualProperty]
    #[Serializer\SerializedName('submission_id')]
    public function getApiSubmissionId(): string
    {
        return $this->getSubmission()->getExternalid();
    }

    public function getMaxRuntime(): ?float
    {
        $runs = $this->getRuns();
        if ($runs->isEmpty()) {
            return null;
        }
        $max = 0;
        foreach ($runs as $run) {
            $max = max($run->getRuntime() ?? 0, $max);
        }
        return $max;
    }

    public function getSumRuntime(): float
    {
        $sum = 0;
        foreach ($this->getRuns() as $run) {
            $sum += $run->getRuntime();
        }
        return $sum;
    }
}
