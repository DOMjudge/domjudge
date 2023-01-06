<?php declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

/**
 * Judgement in external system.
 *
 * @ORM\Table(
 *     name="external_judgement",
 *     options={"collation"="utf8mb4_unicode_ci", "charset"="utf8mb4", "comment":"Judgement in external system"},
 *     indexes={
 *         @ORM\Index(name="submitid", columns={"submitid"}),
 *         @ORM\Index(name="cid", columns={"cid"}),
 *         @ORM\Index(name="verified", columns={"verified"}),
 *     },
 *     uniqueConstraints={
 *         @ORM\UniqueConstraint(name="externalid", columns={"cid", "externalid"}, options={"lengths": {null, 190}}),
 *     })
 * @ORM\Entity
 */
class ExternalJudgement
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="extjudgementid",
     *     options={"comment"="External judgement ID","unsigned"=true},
     *     nullable=false)
     */
    private int $extjudgementid;

    /**
     * @ORM\Column(type="string", name="externalid", length=255,
     *     options={"comment"="Judgement ID in external system, should be unique inside a single contest",
     *              "collation"="utf8mb4_bin"},
     *     nullable=true)
     */
    protected string $externalid;

    /**
     * @ORM\Column(name="result", type="string", length=32,
     *     options={"comment"="Result string as obtained from external system. null if not finished yet"},
     *     nullable=true)
     */
    private ?string $result = null;

    /**
     * @ORM\Column(type="boolean", name="verified",
     *     options={"comment"="Result / difference verified?",
     *              "default"=0},
     *     nullable=false)
     * @Serializer\Exclude()
     */
    private bool $verified = false;

    /**
     * @ORM\Column(type="string", name="jury_member", length=255,
     *     options={"comment"="Name of user who verified the result / difference",
     *              "default"=NULL},
     *     nullable=true)
     * @Serializer\Exclude()
     */
    private ?string $jury_member;

    /**
     * @ORM\Column(type="string", name="verify_comment", length=255,
     *     options={"comment"="Optional additional information provided by the verifier",
     *              "default"=NULL},
     *     nullable=true)
     * @Serializer\Exclude()
     */
    private ?string $verify_comment;

    /**
     * @var double|string
     *
     * @ORM\Column(type="decimal", precision=32, scale=9, name="starttime",
     *              options={"comment"="Time judging started", "unsigned"=true},
     *              nullable=false)
     */
    private $starttime;

    /**
     * @var double|string|null
     *
     * @ORM\Column(type="decimal", precision=32, scale=9, name="endtime",
     *     options={"comment"="Time judging ended, null = still busy",
     *              "unsigned"=true},
     *     nullable=true)
     */
    private $endtime = null;

    /**
     * @ORM\Column(type="boolean", name="valid",
     *     options={"comment"="Old external judgement is marked as invalid when receiving a new one",
     *              "default"="1"},
     *     nullable=false)
     */
    private bool $valid = true;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Contest")
     * @ORM\JoinColumn(name="cid", referencedColumnName="cid", onDelete="CASCADE")
     */
    private Contest $contest;

    /**
     * @ORM\ManyToOne(targetEntity="Submission", inversedBy="external_judgements")
     * @ORM\JoinColumn(name="submitid", referencedColumnName="submitid", onDelete="CASCADE")
     */
    private Submission $submission;

    /**
     * @ORM\OneToMany(targetEntity="ExternalRun", mappedBy="external_judgement")
     */
    private Collection $external_runs;

    public function __construct()
    {
        $this->external_runs = new ArrayCollection();
    }

    public function getExtjudgementid(): int
    {
        return $this->extjudgementid;
    }

    public function setExternalid(string $externalid): ExternalJudgement
    {
        $this->externalid = $externalid;
        return $this;
    }

    public function getExternalid(): string
    {
        return $this->externalid;
    }

    public function setResult(?string $result): ExternalJudgement
    {
        $this->result = $result;
        return $this;
    }

    public function getResult(): ?string
    {
        return $this->result;
    }

    public function setVerified(bool $verified): ExternalJudgement
    {
        $this->verified = $verified;
        return $this;
    }

    public function getVerified(): bool
    {
        return $this->verified;
    }

    public function setJuryMember(?string $juryMember): ExternalJudgement
    {
        $this->jury_member = $juryMember;
        return $this;
    }

    public function getJuryMember(): ?string
    {
        return $this->jury_member;
    }

    public function setVerifyComment(?string $verifyComment): ExternalJudgement
    {
        $this->verify_comment = $verifyComment;
        return $this;
    }

    public function getVerifyComment(): ?string
    {
        return $this->verify_comment;
    }

    /** @param string|float $starttime */
    public function setStarttime($starttime): ExternalJudgement
    {
        $this->starttime = $starttime;
        return $this;
    }

    /** @return string|float */
    public function getStarttime()
    {
        return $this->starttime;
    }

    /** @param string|float $endtime */
    public function setEndtime($endtime): ExternalJudgement
    {
        $this->endtime = $endtime;
        return $this;
    }

    /** @return string|float */
    public function getEndtime()
    {
        return $this->endtime;
    }

    public function setValid(bool $valid): ExternalJudgement
    {
        $this->valid = $valid;
        return $this;
    }

    public function getValid(): bool
    {
        return $this->valid;
    }

    public function setContest(?Contest $contest = null): ExternalJudgement
    {
        $this->contest = $contest;
        return $this;
    }

    public function getContest(): ?Contest
    {
        return $this->contest;
    }

    public function setSubmission(Submission $submission): ExternalJudgement
    {
        $this->submission = $submission;
        return $this;
    }

    public function getSubmission(): Submission
    {
        return $this->submission;
    }

    public function addExternalRun(ExternalRun $externalRun): ExternalJudgement
    {
        $this->external_runs[] = $externalRun;
        return $this;
    }

    public function removeExternalRun(ExternalRun $externalRun): void
    {
        $this->external_runs->removeElement($externalRun);
    }

    public function getExternalRuns(): Collection
    {
        return $this->external_runs;
    }

    public function getMaxRuntime(): float
    {
        $max = 0;
        foreach ($this->external_runs as $run) {
            $max = max($run->getRuntime(), $max);
        }
        return $max;
    }

    public function getSumRuntime(): float
    {
        $sum = 0;
        foreach ($this->external_runs as $run) {
            $sum += $run->getRuntime();
        }
        return $sum;
    }
}
