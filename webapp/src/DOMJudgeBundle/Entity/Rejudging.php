<?php
namespace DOMJudgeBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Rejudge group
 * @ORM\Entity()
 * @ORM\Table(name="rejudging", options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4"})
 */
class Rejudging
{
    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="rejudgingid", options={"comment"="Unique ID"}, nullable=false)
     */
    private $rejudgingid;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", name="userid_start", options={"comment"="User ID of user who started the rejudge"}, nullable=true)
     */
    private $userid_start;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", name="userid_finish", options={"comment"="User ID of user who accepted or canceled the rejudge"}, nullable=true)
     */
    private $userid_finish;


    /**
     * @var double
     * @ORM\Column(type="decimal", precision=32, scale=9, name="starttime", options={"comment"="Time rejudging started", "unsigned"=true}, nullable=false)
     */
    private $starttime;

    /**
     * @var double
     * @ORM\Column(type="decimal", precision=32, scale=9, name="endtime", options={"comment"="Time rejudging ended, null = stil busy", "unsigned"=true}, nullable=true)
     */
    private $endtime;

    /**
     * @var string
     * @ORM\Column(type="string", name="reason", length=255, options={"comment"="Reason to start this rejudge"}, nullable=false)
     */
    private $reason;

    /**
     * @var boolean
     * @ORM\Column(type="boolean", name="valid", options={"comment"="Rejudging is marked as invalid if canceled"}, nullable=false)
     */
    private $valid = true;

    /**
     * Who started the rejudging
     * @ORM\ManyToOne(targetEntity="User")
     * @ORM\JoinColumn(name="userid_start", referencedColumnName="userid")
     */
    private $start_user;

    /**
     * Who finished the rejudging
     * @ORM\ManyToOne(targetEntity="User")
     * @ORM\JoinColumn(name="userid_finish", referencedColumnName="userid")
     */
    private $finish_user;

    /**
     * One judging has many rejudgings
     * @ORM\OneToMany(targetEntity="Judging", mappedBy="rejudging")
     */
    private $judgings;
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->judgings = new \Doctrine\Common\Collections\ArrayCollection();
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
     * @param \DOMJudgeBundle\Entity\User $startUser
     *
     * @return Rejudging
     */
    public function setStartUser(\DOMJudgeBundle\Entity\User $startUser = null)
    {
        $this->start_user = $startUser;

        return $this;
    }

    /**
     * Get startUser
     *
     * @return \DOMJudgeBundle\Entity\User
     */
    public function getStartUser()
    {
        return $this->start_user;
    }

    /**
     * Set finishUser
     *
     * @param \DOMJudgeBundle\Entity\User $finishUser
     *
     * @return Rejudging
     */
    public function setFinishUser(\DOMJudgeBundle\Entity\User $finishUser = null)
    {
        $this->finish_user = $finishUser;

        return $this;
    }

    /**
     * Get finishUser
     *
     * @return \DOMJudgeBundle\Entity\User
     */
    public function getFinishUser()
    {
        return $this->finish_user;
    }

    /**
     * Add judging
     *
     * @param \DOMJudgeBundle\Entity\Judging $judging
     *
     * @return Rejudging
     */
    public function addJudging(\DOMJudgeBundle\Entity\Judging $judging)
    {
        $this->judgings[] = $judging;

        return $this;
    }

    /**
     * Remove judging
     *
     * @param \DOMJudgeBundle\Entity\Judging $judging
     */
    public function removeJudging(\DOMJudgeBundle\Entity\Judging $judging)
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
}
