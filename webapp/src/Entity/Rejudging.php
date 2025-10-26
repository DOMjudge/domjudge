<?php declare(strict_types=1);
namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

/**
 * Rejudge group.
 */
#[ORM\Entity]
#[ORM\Table(options: [
    'collation' => 'utf8mb4_unicode_ci',
    'charset' => 'utf8mb4',
    'comment' => 'Rejudge group',
])]
#[ORM\Index(columns: ['userid_start'], name: 'userid_start')]
#[ORM\Index(columns: ['userid_finish'], name: 'userid_finish')]
class Rejudging
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['comment' => 'Rejudging ID', 'unsigned' => true])]
    private int $rejudgingid;


    #[ORM\Column(
        type: 'decimal',
        precision: 32,
        scale: 9,
        options: ['comment' => 'Time rejudging started', 'unsigned' => true]
    )]
    private string|float $starttime;

    #[ORM\Column(
        type: 'decimal',
        precision: 32,
        scale: 9,
        nullable: true,
        options: ['comment' => 'Time rejudging ended, null = still busy', 'unsigned' => true]
    )]
    private string|float|null $endtime = null;

    #[ORM\Column(options: ['comment' => 'Reason to start this rejudge'])]
    private string $reason;

    #[ORM\Column(options: ['comment' => 'Rejudging is marked as invalid if canceled', 'default' => 1])]
    private bool $valid = true;

    /**
     * Who started the rejudging.
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'userid_start', referencedColumnName: 'userid', onDelete: 'SET NULL')]
    private ?User $start_user = null;

    /**
     * Who finished the rejudging.
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'userid_finish', referencedColumnName: 'userid', onDelete: 'SET NULL')]
    private ?User $finish_user = null;

    /**
     * @var Collection<int, Judging>
     *
     * One rejudging has many judgings.
     */
    #[ORM\OneToMany(mappedBy: 'rejudging', targetEntity: Judging::class)]
    private Collection $judgings;

    /**
     * @var Collection<int, Submission>
     *
     * One rejudging has many submissions.
     */
    #[ORM\OneToMany(mappedBy: 'rejudging', targetEntity: Submission::class)]
    private Collection $submissions;

    #[ORM\Column(options: [
        'comment' => 'If set, judgings are accepted automatically.',
        'default' => 0,
    ])]
    private bool $autoApply = true;

    #[ORM\Column(
        name: '`repeat`',
        nullable: true,
        options: ['comment' => 'Number of times this rejudging will be repeated.', 'unsigned' => true]
    )]
    private ?int $repeat = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'repeat_rejudgingid', referencedColumnName: 'rejudgingid', onDelete: 'SET NULL')]
    #[Serializer\Exclude]
    private ?Rejudging $repeatedRejudging = null;

    public function __construct()
    {
        $this->judgings    = new ArrayCollection();
        $this->submissions = new ArrayCollection();
    }

    public function getRejudgingid(): int
    {
        return $this->rejudgingid;
    }

    public function setStarttime(string|float $starttime): Rejudging
    {
        $this->starttime = $starttime;
        return $this;
    }

    public function getStarttime(): string|float
    {
        return $this->starttime;
    }

    public function setEndtime(string|float $endtime): Rejudging
    {
        $this->endtime = $endtime;
        return $this;
    }

    public function getEndtime(): string|float|null
    {
        return $this->endtime;
    }

    public function setReason(string $reason): Rejudging
    {
        $this->reason = $reason;
        return $this;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function setValid(bool $valid): Rejudging
    {
        $this->valid = $valid;
        return $this;
    }

    public function getValid(): bool
    {
        return $this->valid;
    }

    public function setStartUser(?User $startUser = null): Rejudging
    {
        $this->start_user = $startUser;
        return $this;
    }

    public function getStartUser(): ?User
    {
        return $this->start_user;
    }

    public function setFinishUser(?User $finishUser = null): Rejudging
    {
        $this->finish_user = $finishUser;
        return $this;
    }

    public function getFinishUser(): ?User
    {
        return $this->finish_user;
    }

    public function addJudging(Judging $judging): Rejudging
    {
        $this->judgings[] = $judging;
        return $this;
    }

    /**
     * @return Collection<int, Judging>
     */
    public function getJudgings(): Collection
    {
        return $this->judgings;
    }

    public function addSubmission(Submission $submission): Rejudging
    {
        $this->submissions[] = $submission;
        return $this;
    }

    /**
     * @return Collection<int, Submission>
     */
    public function getSubmissions(): Collection
    {
        return $this->submissions;
    }

    public function setAutoApply(bool $autoApply): Rejudging
    {
        $this->autoApply = $autoApply;
        return $this;
    }

    public function getAutoApply(): bool
    {
        return $this->autoApply;
    }

    public function setRepeat(int $repeat): Rejudging
    {
        $this->repeat = $repeat;
        return $this;
    }

    public function getRepeat(): ?int
    {
        return $this->repeat;
    }

    public function setRepeatedRejudging(?Rejudging $repeatedRejudging): Rejudging
    {
        $this->repeatedRejudging = $repeatedRejudging;
        return $this;
    }

    public function getRepeatedRejudging(): ?Rejudging
    {
        return $this->repeatedRejudging;
    }
}
