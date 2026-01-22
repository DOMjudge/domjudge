<?php declare(strict_types=1);
namespace App\Entity;

use App\Doctrine\DBAL\Types\InternalErrorStatusType;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

/**
 * Log of judgehost internal errors.
 */
#[ORM\Entity]
#[ORM\Table(
options: [
    'collation' => 'utf8mb4_unicode_ci',
    'charset' => 'utf8mb4',
    'comment' => 'Log of judgehost internal errors',
])]
#[ORM\Index(name: 'judgingid', columns: ['judgingid'])]
#[ORM\Index(name: 'cid', columns: ['cid'])]
class InternalError
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(options: ['comment' => 'Internal error ID', 'unsigned' => true])]
    private int $errorid;

    #[ORM\Column(options: ['comment' => 'Description of the error'])]
    private string $description;

    #[ORM\Column(
        type: 'text',
        length: AbstractMySQLPlatform::LENGTH_LIMIT_TEXT,
        options: ['comment' => 'Last N lines of the judgehost log']
    )]
    private string $judgehostlog;

    #[ORM\Column(
        type: 'decimal',
        precision: 32,
        scale: 9,
        options: ['comment' => 'Timestamp of the internal error', 'unsigned' => true]
    )]
    private string|float $time;

    /**
     * @var array{kind: string, hostname?: string, execid?: string, probid: string, langid: string}
     */
    #[ORM\Column(
        type: 'json',
        length: AbstractMySQLPlatform::LENGTH_LIMIT_TEXT,
        options: ['comment' => 'Disabled stuff, JSON-encoded']
    )]
    private array $disabled;

    #[ORM\Column(
        type: 'internal_error_status',
        options: ['comment' => 'Status of internal error', 'default' => 'open']
    )]
    private string $status = InternalErrorStatusType::STATUS_OPEN;

    #[ORM\ManyToOne(inversedBy: 'internal_errors')]
    #[ORM\JoinColumn(name: 'cid', referencedColumnName: 'cid', onDelete: 'SET NULL')]
    private ?Contest $contest = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'judgingid', referencedColumnName: 'judgingid', onDelete: 'SET NULL')]
    private ?Judging $judging = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'runid', referencedColumnName: 'runid', onDelete: 'SET NULL')]
    private ?JudgingRun $judgingRun = null;

    /**
     * @var Collection<int, Judging>
     */
    #[ORM\OneToMany(targetEntity: Judging::class, mappedBy: 'internalError')]
    #[Serializer\Exclude]
    private Collection $affectedJudgings;

    public function __construct()
    {
        $this->affectedJudgings = new ArrayCollection();
    }

    public function setErrorid(int $errorid): InternalError
    {
        $this->errorid = $errorid;
        return $this;
    }

    public function getErrorid(): int
    {
        return $this->errorid;
    }

    public function setDescription(string $description): InternalError
    {
        $this->description = $description;
        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setJudgehostlog(string $judgehostlog): InternalError
    {
        $this->judgehostlog = $judgehostlog;
        return $this;
    }

    public function getJudgehostlog(): string
    {
        return $this->judgehostlog;
    }

    public function setTime(string|float $time): InternalError
    {
        $this->time = $time;
        return $this;
    }

    public function getTime(): string|float
    {
        return $this->time;
    }

    /**
     * @param array{kind: string, hostname?: string, execid?: string, probid?: string, langid?: string} $disabled
     */
    public function setDisabled(array $disabled): InternalError
    {
        $this->disabled = $disabled;
        return $this;
    }

    /**
     * @return array{kind: string, hostname?: string, execid?: string, probid?: string, langid?: string}
     */
    public function getDisabled(): array
    {
        return $this->disabled;
    }

    public function setStatus(string $status): InternalError
    {
        $this->status = $status;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setContest(?Contest $contest = null): InternalError
    {
        $this->contest = $contest;
        return $this;
    }

    public function getContest(): ?Contest
    {
        return $this->contest;
    }

    public function setJudging(?Judging $judging = null): InternalError
    {
        $this->judging = $judging;
        return $this;
    }

    public function getJudging(): ?Judging
    {
        return $this->judging;
    }

    /**
     * @return Collection<int, Judging>
     */
    public function getAffectedJudgings(): Collection
    {
        return $this->affectedJudgings;
    }

    public function getJudgingRun(): ?JudgingRun
    {
        return $this->judgingRun;
    }

    public function setJudgingRun(?JudgingRun $judgingRun = null): InternalError
    {
        $this->judgingRun = $judgingRun;
        return $this;
    }
}
