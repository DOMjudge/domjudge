<?php declare(strict_types=1);
namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

/**
 * Rejudge group.
 *
 * @ORM\Entity()
 * @ORM\Table(
 *     name="rejudging",
 *     options={"collation"="utf8mb4_unicode_ci", "charset"="utf8mb4", "comment"="Rejudge group"},
 *     indexes={
 *         @ORM\Index(name="userid_start", columns={"userid_start"}),
 *         @ORM\Index(name="userid_finish", columns={"userid_finish"})
 *     })
 */
class Rejudging
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="rejudgingid", length=4,
     *     options={"comment"="Rejudging ID","unsigned"=true},
     *     nullable=false)
     */
    private int $rejudgingid;


    /**
     * @var double|string
     * @ORM\Column(type="decimal", precision=32, scale=9, name="starttime",
     *     options={"comment"="Time rejudging started", "unsigned"=true},
     *     nullable=false)
     */
    private $starttime;

    /**
     * @var double|string|null
     * @ORM\Column(type="decimal", precision=32, scale=9, name="endtime",
     *     options={"comment"="Time rejudging ended, null = still busy",
     *              "unsigned"=true},
     *     nullable=true)
     */
    private $endtime;

    /**
     * @ORM\Column(type="string", name="reason", length=255,
     *     options={"comment"="Reason to start this rejudge"}, nullable=false)
     */
    private string $reason;

    /**
     * @ORM\Column(type="boolean", name="valid",
     *     options={"comment"="Rejudging is marked as invalid if canceled",
     *              "default"="1"},
     *     nullable=false)
     */
    private bool $valid = true;

    /**
     * Who started the rejudging.
     * @ORM\ManyToOne(targetEntity="User")
     * @ORM\JoinColumn(name="userid_start", referencedColumnName="userid", onDelete="SET NULL")
     */
    private ?User $start_user;

    /**
     * Who finished the rejudging.
     * @ORM\ManyToOne(targetEntity="User")
     * @ORM\JoinColumn(name="userid_finish", referencedColumnName="userid", onDelete="SET NULL")
     */
    private ?User $finish_user;

    /**
     * One rejudging has many judgings.
     * @ORM\OneToMany(targetEntity="Judging", mappedBy="rejudging")
     */
    private Collection $judgings;

    /**
     * One rejudging has many submissions.
     * @ORM\OneToMany(targetEntity="App\Entity\Submission", mappedBy="rejudging")
     */
    private Collection $submissions;

    /**
     * @ORM\Column(type="boolean", name="auto_apply",
     *     options={"comment"="If set, judgings are accepted automatically.",
     *              "default"="0"},
     *     nullable=false)
     */
    private bool $autoApply = true;

    /**
     * @ORM\Column(type="integer", name="`repeat`",
     *     options={"comment"="Number of times this rejudging will be repeated.",
     *              "unsigned"=true},
     *     nullable=true)
     */
    private ?int $repeat;

    /**
     * @ORM\ManyToOne(targetEntity="Rejudging")
     * @ORM\JoinColumn(name="repeat_rejudgingid", referencedColumnName="rejudgingid", onDelete="SET NULL")
     * @Serializer\Exclude()
     */
    private ?Rejudging $repeatedRejudging;

    public function __construct()
    {
        $this->judgings    = new ArrayCollection();
        $this->submissions = new ArrayCollection();
    }

    public function getRejudgingid(): int
    {
        return $this->rejudgingid;
    }

    /** @param string|float $starttime */
    public function setStarttime($starttime): Rejudging
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
    public function setEndtime($endtime): Rejudging
    {
        $this->endtime = $endtime;
        return $this;
    }

    /** @return string|float */
    public function getEndtime()
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

    public function removeJudging(Judging $judging)
    {
        $this->judgings->removeElement($judging);
    }

    public function getJudgings(): Collection
    {
        return $this->judgings;
    }

    public function addSubmission(Submission $submission): Rejudging
    {
        $this->submissions[] = $submission;
        return $this;
    }

    public function removeSubmission(Submission $submission)
    {
        $this->submissions->removeElement($submission);
    }

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
