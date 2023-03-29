<?php declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use App\Entity\Contest;

/**
 * Judgement in external system.
 */
#[ORM\Table(
    name: 'external_judgement',
    options: [
        'collation' => 'utf8mb4_unicode_ci',
        'charset' => 'utf8mb4',
        'comment' => 'Judgement in external system',
    ]
)]
#[ORM\Index(columns: ['submitid'], name: 'submitid')]
#[ORM\Index(columns: ['cid'], name: 'cid')]
#[ORM\Index(columns: ['verified'], name: 'verified')]
#[ORM\UniqueConstraint(
    name: 'externalid',
    columns: ['cid', 'externalid'],
    options: ['lengths' => [null, 190]]
)]
#[ORM\Entity]
class ExternalJudgement
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(
        name: 'extjudgementid',
        type: 'integer',
        nullable: false,
        options: ['comment' => 'External judgement ID', 'unsigned' => true]
    )]
    private int $extjudgementid;

    #[ORM\Column(
        name: 'externalid',
        type: 'string',
        length: 255,
        nullable: true,
        options: ['comment' => 'Judgement ID in external system, should be unique inside a single contest', 'collation' => 'utf8mb4_bin']
    )]
    protected string $externalid;

    #[ORM\Column(
        name: 'result',
        type: 'string',
        length: 32,
        nullable: true,
        options: ['comment' => 'Result string as obtained from external system. null if not finished yet']
    )]
    private ?string $result = null;

    #[ORM\Column(
        name: 'verified',
        type: 'boolean',
        nullable: false,
        options: ['comment' => 'Result / difference verified?', 'default' => 0]
    )]
    #[Serializer\Exclude]
    private bool $verified = false;

    #[ORM\Column(
        name: 'jury_member',
        type: 'string',
        length: 255,
        nullable: true,
        options: ['comment' => 'Name of user who verified the result / difference', 'default' => null]
    )]
    #[Serializer\Exclude]
    private ?string $jury_member = null;

    #[ORM\Column(
        name: 'verify_comment',
        type: 'string',
        length: 255,
        nullable: true,
        options: ['comment' => 'Optional additional information provided by the verifier', 'default' => null]
    )]
    #[Serializer\Exclude]
    private ?string $verify_comment = null;

    #[ORM\Column(
        name: 'starttime',
        type: 'decimal',
        precision: 32,
        scale: 9,
        nullable: false,
        options: ['comment' => 'Time judging started', 'unsigned' => true]
    )]
    private string|float $starttime;

    #[ORM\Column(
        name: 'endtime',
        type: 'decimal',
        precision: 32,
        scale: 9,
        nullable: true,
        options: ['comment' => 'Time judging ended, null = still busy', 'unsigned' => true]
    )]
    private string|float|null $endtime = null;

    #[ORM\Column(
        name: 'valid',
        type: 'boolean',
        nullable: false,
        options: ['comment' => 'Old external judgement is marked as invalid when receiving a new one', 'default' => 1]
    )]
    private bool $valid = true;

    #[ORM\ManyToOne(targetEntity: Contest::class)]
    #[ORM\JoinColumn(name: 'cid', referencedColumnName: 'cid', onDelete: 'CASCADE')]
    private Contest $contest;

    #[ORM\ManyToOne(targetEntity: Submission::class, inversedBy: 'external_judgements')]
    #[ORM\JoinColumn(name: 'submitid', referencedColumnName: 'submitid', onDelete: 'CASCADE')]
    private Submission $submission;

    #[ORM\OneToMany(mappedBy: 'external_judgement', targetEntity: ExternalRun::class)]
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

    public function setStarttime(string|float $starttime): ExternalJudgement
    {
        $this->starttime = $starttime;
        return $this;
    }

    public function getStarttime(): string|float
    {
        return $this->starttime;
    }

    public function setEndtime(string|float $endtime): ExternalJudgement
    {
        $this->endtime = $endtime;
        return $this;
    }

    public function getEndtime(): string|float|null
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
