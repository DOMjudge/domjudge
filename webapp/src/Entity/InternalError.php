<?php declare(strict_types=1);
namespace App\Entity;

use App\Doctrine\DBAL\Types\InternalErrorStatusType;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

/**
 * Log of judgehost internal errors.
 */
#[ORM\Table(
    name: 'internal_error',
    options: [
        'collation' => 'utf8mb4_unicode_ci',
        'charset' => 'utf8mb4',
        'comment' => 'Log of judgehost internal errors',
    ]
)]
#[ORM\Index(columns: ['judgingid'], name: 'judgingid')]
#[ORM\Index(columns: ['cid'], name: 'cid')]
#[ORM\Entity]
class InternalError
{
    #[ORM\Id]
    #[ORM\Column(
        name: 'errorid',
        type: 'integer',
        length: 4,
        nullable: false,
        options: ['comment' => 'Internal error ID', 'unsigned' => true]
    )]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private int $errorid;

    #[ORM\Column(
        name: 'description',
        type: 'string',
        length: 255,
        nullable: false,
        options: ['comment' => 'Description of the error']
    )]
    private string $description;

    #[ORM\Column(
        name: 'judgehostlog',
        type: 'text',
        length: 65535,
        nullable: false,
        options: ['comment' => 'Last N lines of the judgehost log']
    )]
    private string $judgehostlog;

    #[ORM\Column(
        name: 'time',
        type: 'decimal',
        precision: 32,
        scale: 9,
        nullable: false,
        options: ['comment' => 'Timestamp of the internal error', 'unsigned' => true]
    )]
    private string|float $time;

    #[ORM\Column(
        name: 'disabled',
        type: 'json',
        length: 65535,
        nullable: false,
        options: ['comment' => 'Disabled stuff, JSON-encoded']
    )]
    private array $disabled;

    #[ORM\Column(
        name: 'status',
        type: 'internal_error_status',
        nullable: false,
        options: ['comment' => 'Status of internal error', 'default' => 'open']
    )]
    private string $status = InternalErrorStatusType::STATUS_OPEN;

    #[ORM\ManyToOne(targetEntity: Contest::class, inversedBy: 'internal_errors')]
    #[ORM\JoinColumn(name: 'cid', referencedColumnName: 'cid', onDelete: 'SET NULL')]
    private ?Contest $contest = null;

    #[ORM\ManyToOne(targetEntity: Judging::class)]
    #[ORM\JoinColumn(name: 'judgingid', referencedColumnName: 'judgingid', onDelete: 'SET NULL')]
    private ?Judging $judging = null;

    #[ORM\OneToMany(mappedBy: 'internalError', targetEntity: Judging::class)]
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

    public function setDisabled(array $disabled): InternalError
    {
        $this->disabled = $disabled;
        return $this;
    }

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

    public function getAffectedJudgings(): Collection
    {
        return $this->affectedJudgings;
    }
}
