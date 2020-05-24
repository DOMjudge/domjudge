<?php declare(strict_types=1);
namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

/**
 * Rejudge group
 * @ORM\Entity()
 * @ORM\Table(
 *     name="rejudging",
 *     options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4", "comment"="Rejudge group"},
 *     indexes={
 *         @ORM\Index(name="userid_start", columns={"userid_start"}),
 *         @ORM\Index(name="userid_finish", columns={"userid_finish"})
 *     })
 */
class Rejudging
{
    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="rejudgingid", length=4,
     *     options={"comment"="Rejudging ID","unsigned"=true},
     *     nullable=false)
     */
    private $rejudgingid;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", name="userid_start", length=4,
     *     options={"comment"="User ID of user who started the rejudge",
     *              "unsigned"=true,"default"="NULL"},
     *     nullable=true)
     */
    private $userid_start;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", name="userid_finish",
     *     options={"comment"="User ID of user who accepted or canceled the rejudge",
     *              "unsigned"=true,"default"="NULL"},
     *     nullable=true)
     */
    private $userid_finish;


    /**
     * @var double
     * @ORM\Column(type="decimal", precision=32, scale=9, name="starttime",
     *     options={"comment"="Time rejudging started", "unsigned"=true},
     *     nullable=false)
     */
    private $starttime;

    /**
     * @var double
     * @ORM\Column(type="decimal", precision=32, scale=9, name="endtime",
     *     options={"comment"="Time rejudging ended, null = still busy",
     *              "unsigned"=true,"default"="NULL"},
     *     nullable=true)
     */
    private $endtime;

    /**
     * @var string
     * @ORM\Column(type="string", name="reason", length=255,
     *     options={"comment"="Reason to start this rejudge"}, nullable=false)
     */
    private $reason;

    /**
     * @var boolean
     * @ORM\Column(type="boolean", name="valid",
     *     options={"comment"="Rejudging is marked as invalid if canceled",
     *              "default"="1"},
     *     nullable=false)
     */
    private $valid = true;

    /**
     * Who started the rejudging
     * @ORM\ManyToOne(targetEntity="User")
     * @ORM\JoinColumn(name="userid_start", referencedColumnName="userid", onDelete="SET NULL")
     */
    private $start_user;

    /**
     * Who finished the rejudging
     * @ORM\ManyToOne(targetEntity="User")
     * @ORM\JoinColumn(name="userid_finish", referencedColumnName="userid", onDelete="SET NULL")
     */
    private $finish_user;

    /**
     * One rejudging has many judgings
     * @ORM\OneToMany(targetEntity="Judging", mappedBy="rejudging")
     */
    private $judgings;

    /**
     * One rejudging has many submissions
     * @ORM\OneToMany(targetEntity="App\Entity\Submission", mappedBy="rejudging")
     */
    private $submissions;

    /**
     * @var boolean
     * @ORM\Column(type="boolean", name="auto_apply",
     *     options={"comment"="If set, judgings are accepted automatically.",
     *              "default"="0"},
     *     nullable=false)
     */
    private $autoApply = true;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", name="`repeat`",
     *     options={"comment"="Number of times this rejudging will be repeated.",
     *              "unsigned"=true,"default"="NULL"},
     *     nullable=true)
     */
    private $repeat;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", name="repeat_rejudgingid",
     *     options={"comment"="In case repeat is set, this will mark the first rejudgingid.",
     *              "unsigned"=true,"default"="NULL"},
     *     nullable=true)
     */
    private $repeat_rejudgingid;

    /**
     * @ORM\ManyToOne(targetEntity="Rejudging")
     * @ORM\JoinColumn(name="repeat_rejudgingid", referencedColumnName="rejudgingid", onDelete="SET NULL")
     * @Serializer\Exclude()
     */
    private $repeatedRejudging;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->judgings = new \Doctrine\Common\Collections\ArrayCollection();
        $this->submissions = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * Get rejudgingid
     *
     * @return integer
     */
    public function getRejudgingid()
    {
        return $this->rejudgingid;
    }

    /**
     * Set useridStart
     *
     * @param integer $useridStart
     *
     * @return Rejudging
     */
    public function setUseridStart($useridStart)
    {
        $this->userid_start = $useridStart;

        return $this;
    }

    /**
     * Get useridStart
     *
     * @return integer
     */
    public function getUseridStart()
    {
        return $this->userid_start;
    }

    /**
     * Set useridFinish
     *
     * @param integer $useridFinish
     *
     * @return Rejudging
     */
    public function setUseridFinish($useridFinish)
    {
        $this->userid_finish = $useridFinish;

        return $this;
    }

    /**
     * Get useridFinish
     *
     * @return integer
     */
    public function getUseridFinish()
    {
        return $this->userid_finish;
    }

    /**
     * Set starttime
     *
     * @param string $starttime
     *
     * @return Rejudging
     */
    public function setStarttime($starttime)
    {
        $this->starttime = $starttime;

        return $this;
    }

    /**
     * Get starttime
     *
     * @return string
     */
    public function getStarttime()
    {
        return $this->starttime;
    }

    /**
     * Set endtime
     *
     * @param string $endtime
     *
     * @return Rejudging
     */
    public function setEndtime($endtime)
    {
        $this->endtime = $endtime;

        return $this;
    }

    /**
     * Get endtime
     *
     * @return string
     */
    public function getEndtime()
    {
        return $this->endtime;
    }

    /**
     * Set reason
     *
     * @param string $reason
     *
     * @return Rejudging
     */
    public function setReason($reason)
    {
        $this->reason = $reason;

        return $this;
    }

    /**
     * Get reason
     *
     * @return string
     */
    public function getReason()
    {
        return $this->reason;
    }

    /**
     * Set valid
     *
     * @param boolean $valid
     *
     * @return Rejudging
     */
    public function setValid($valid)
    {
        $this->valid = $valid;

        return $this;
    }

    /**
     * Get valid
     *
     * @return boolean
     */
    public function getValid()
    {
        return $this->valid;
    }

    /**
     * Set startUser
     *
     * @param \App\Entity\User $startUser
     *
     * @return Rejudging
     */
    public function setStartUser(\App\Entity\User $startUser = null)
    {
        $this->start_user = $startUser;

        return $this;
    }

    /**
     * Get startUser
     *
     * @return \App\Entity\User
     */
    public function getStartUser()
    {
        return $this->start_user;
    }

    /**
     * Set finishUser
     *
     * @param \App\Entity\User $finishUser
     *
     * @return Rejudging
     */
    public function setFinishUser(\App\Entity\User $finishUser = null)
    {
        $this->finish_user = $finishUser;

        return $this;
    }

    /**
     * Get finishUser
     *
     * @return \App\Entity\User
     */
    public function getFinishUser()
    {
        return $this->finish_user;
    }

    /**
     * Add judging
     *
     * @param \App\Entity\Judging $judging
     *
     * @return Rejudging
     */
    public function addJudging(\App\Entity\Judging $judging)
    {
        $this->judgings[] = $judging;

        return $this;
    }

    /**
     * Remove judging
     *
     * @param \App\Entity\Judging $judging
     */
    public function removeJudging(\App\Entity\Judging $judging)
    {
        $this->judgings->removeElement($judging);
    }

    /**
     * Get judgings
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getJudgings()
    {
        return $this->judgings;
    }

    /**
     * Add submission
     *
     * @param Submission $submission
     *
     * @return Rejudging
     */
    public function addSubmission(Submission $submission)
    {
        $this->submissions[] = $submission;

        return $this;
    }

    /**
     * Remove submission
     *
     * @param Submission $submission
     */
    public function removeSubmission(Submission $submission)
    {
        $this->submissions->removeElement($submission);
    }

    /**
     * Get submissions
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getSubmissions()
    {
        return $this->submissions;
    }

    /**
     * Set auto_apply
     *
     * @param boolean $autoApply
     *
     * @return Rejudging
     */
    public function setAutoApply(bool $autoApply)
    {
        $this->autoApply = $autoApply;

        return $this;
    }

    /**
     * Get auto_apply
     *
     * @return boolean
     */
    public function getAutoApply()
    {
        return $this->autoApply;
    }

    /**
     * Set repeat
     *
     * @param int $repeat
     *
     * @return Rejudging
     */
    public function setRepeat(int $repeat)
    {
        $this->repeat = $repeat;

        return $this;
    }

    /**
     * Get repeat
     *
     * @return int
     */
    public function getRepeat()
    {
        return $this->repeat;
    }

    /**
     * Set repeat_rejudgingid
     *
     * @param int $repeat_rejudgingid
     *
     * @return Rejudging
     */
    public function setRepeatRejudgingId(int $repeatRejudgingId)
    {
        $this->repeat_rejudgingid = $repeatRejudgingId;

        return $this;
    }

    /**
     * Get repeat
     *
     * @return int
     */
    public function getRepeatRejudgingId()
    {
        return $this->repeat_rejudgingid;
    }
}
