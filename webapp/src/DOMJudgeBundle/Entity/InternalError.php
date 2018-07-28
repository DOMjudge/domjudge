<?php
namespace DOMJudgeBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Log of judgehost internal errors
 * @ORM\Entity()
 * @ORM\Table(name="internal_error", options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4"})
 */
class InternalError
{
    /**
     * @var int
     * @ORM\Id
     * @ORM\Column(type="integer", name="errorid", options={"comment"="Unique ID"}, nullable=false)
     */
    private $errorid;

    /**
     * @var int
     * @ORM\Column(type="integer", name="judgingid", options={"comment"="Judging ID"}, nullable=true)
     */
    private $judgingid;

    /**
     * @var int
     * @ORM\Column(type="integer", name="cid", options={"comment"="Contest ID"}, nullable=true)
     */
    private $cid;

    /**
     * @var string
     * @ORM\Column(type="text", length=255, name="description", options={"comment"="Description of the error"}, nullable=false)
     */
    private $description;

    /**
     * @var string
     * @ORM\Column(type="text", length=4294967295, name="judgehostlog", options={"comment"="Last N lines of the judgehost log"}, nullable=false)
     */
    private $judgehostlog;

    /**
     * @var double
     * @ORM\Column(type="decimal", precision=32, scale=9, name="time", options={"comment"="When the event occurred", "unsigned"=true}, nullable=false)
     */
    private $time;

    /**
     * @var string
     * @ORM\Column(type="text", length=4294967295, name="disabled", options={"comment"="Disabled stuff, JSON-encoded"}, nullable=false)
     */
    private $disabled;

    /**
     * @var string
     * @ORM\Column(type="text", length=128, name="status", options={"comment"="Status of internal error"}, nullable=false)
     */
    private $status;

    /**
     * @ORM\ManyToOne(targetEntity="Contest", inversedBy="internal_errors")
     * @ORM\JoinColumn(name="cid", referencedColumnName="cid")
     */
    private $contest;

    /**
     * @ORM\ManyToOne(targetEntity="Judging")
     * @ORM\JoinColumn(name="judgingid", referencedColumnName="judgingid")
     */
    private $judging;


    /**
     * Set errorid
     *
     * @param integer $errorid
     *
     * @return InternalError
     */
    public function setErrorid($errorid)
    {
        $this->errorid = $errorid;

        return $this;
    }

    /**
     * Get errorid
     *
     * @return integer
     */
    public function getErrorid()
    {
        return $this->errorid;
    }

    /**
     * Set judgingid
     *
     * @param integer $judgingid
     *
     * @return InternalError
     */
    public function setJudgingid($judgingid)
    {
        $this->judgingid = $judgingid;

        return $this;
    }

    /**
     * Get judgingid
     *
     * @return integer
     */
    public function getJudgingid()
    {
        return $this->judgingid;
    }

    /**
     * Set cid
     *
     * @param integer $cid
     *
     * @return InternalError
     */
    public function setCid($cid)
    {
        $this->cid = $cid;

        return $this;
    }

    /**
     * Get cid
     *
     * @return integer
     */
    public function getCid()
    {
        return $this->cid;
    }

    /**
     * Set description
     *
     * @param string $description
     *
     * @return InternalError
     */
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Get description
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Set judgehostlog
     *
     * @param string $judgehostlog
     *
     * @return InternalError
     */
    public function setJudgehostlog($judgehostlog)
    {
        $this->judgehostlog = $judgehostlog;

        return $this;
    }

    /**
     * Get judgehostlog
     *
     * @return string
     */
    public function getJudgehostlog()
    {
        return $this->judgehostlog;
    }

    /**
     * Set time
     *
     * @param string $time
     *
     * @return InternalError
     */
    public function setTime($time)
    {
        $this->time = $time;

        return $this;
    }

    /**
     * Get time
     *
     * @return string
     */
    public function getTime()
    {
        return $this->time;
    }

    /**
     * Set disabled
     *
     * @param string $disabled
     *
     * @return InternalError
     */
    public function setDisabled($disabled)
    {
        $this->disabled = $disabled;

        return $this;
    }

    /**
     * Get disabled
     *
     * @return string
     */
    public function getDisabled()
    {
        return $this->disabled;
    }

    /**
     * Set status
     *
     * @param string $status
     *
     * @return InternalError
     */
    public function setStatus($status)
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Get status
     *
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Set contest
     *
     * @param \DOMJudgeBundle\Entity\Contest $contest
     *
     * @return InternalError
     */
    public function setContest(\DOMJudgeBundle\Entity\Contest $contest = null)
    {
        $this->contest = $contest;

        return $this;
    }

    /**
     * Get contest
     *
     * @return \DOMJudgeBundle\Entity\Contest
     */
    public function getContest()
    {
        return $this->contest;
    }

    /**
     * Set judging
     *
     * @param \DOMJudgeBundle\Entity\Judging $judging
     *
     * @return InternalError
     */
    public function setJudging(\DOMJudgeBundle\Entity\Judging $judging = null)
    {
        $this->judging = $judging;

        return $this;
    }

    /**
     * Get judging
     *
     * @return \DOMJudgeBundle\Entity\Judging
     */
    public function getJudging()
    {
        return $this->judging;
    }
}
