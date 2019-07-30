<?php declare(strict_types=1);
namespace App\Entity;

use App\Doctrine\DBAL\Types\InternalErrorStatusType;
use Doctrine\ORM\Mapping as ORM;

/**
 * Log of judgehost internal errors
 * @ORM\Entity()
 * @ORM\Table(
 *     name="internal_error",
 *     options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4", "comment"="Log of judgehost internal errors"},
 *     indexes={
 *         @ORM\Index(name="judgingid", columns={"judgingid"}),
 *         @ORM\Index(name="cid", columns={"cid"})
 *     })
 */
class InternalError
{
    /**
     * @var int
     * @ORM\Id
     * @ORM\Column(type="integer", name="errorid", length=4,
     *     options={"comment"="Internal error ID","unsigned"=true},
     *     nullable=false)
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $errorid;

    /**
     * @var int
     * @ORM\Column(type="integer", name="judgingid",
     *     options={"comment"="Judging ID","unsigned"=true,"default"="NULL"},
     *     nullable=true)
     */
    private $judgingid;

    /**
     * @var int
     * @ORM\Column(type="integer", name="cid",
     *     options={"comment"="Contest ID","unsigned"=true,"default"="NULL"},
     *     nullable=true)
     */
    private $cid;

    /**
     * @var string
     * @ORM\Column(type="string", length=255, name="description",
     *     options={"comment"="Description of the error"},
     *     nullable=false)
     */
    private $description;

    /**
     * @var string
     * @ORM\Column(type="text", length=65535, name="judgehostlog",
     *     options={"comment"="Last N lines of the judgehost log"},
     *     nullable=false)
     */
    private $judgehostlog;

    /**
     * @var double
     * @ORM\Column(type="decimal", precision=32, scale=9, name="time",
     *     options={"comment"="Timestamp of the internal error", "unsigned"=true},
     *     nullable=false)
     */
    private $time;

    /**
     * @var array
     * @ORM\Column(type="json", length=65535, name="disabled",
     *     options={"comment"="Disabled stuff, JSON-encoded"},
     *     nullable=false)
     */
    private $disabled;

    /**
     * @var string
     * @ORM\Column(type="internal_error_status", name="status",
     *     options={"comment"="Status of internal error","default"="'open'"},
     *     nullable=false)
     */
    private $status = InternalErrorStatusType::STATUS_OPEN;

    /**
     * @ORM\ManyToOne(targetEntity="Contest", inversedBy="internal_errors")
     * @ORM\JoinColumn(name="cid", referencedColumnName="cid", onDelete="SET NULL")
     */
    private $contest;

    /**
     * @ORM\ManyToOne(targetEntity="Judging")
     * @ORM\JoinColumn(name="judgingid", referencedColumnName="judgingid", onDelete="SET NULL")
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
     * @param array $disabled
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
     * @return array
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
     * @param \App\Entity\Contest $contest
     *
     * @return InternalError
     */
    public function setContest(\App\Entity\Contest $contest = null)
    {
        $this->contest = $contest;

        return $this;
    }

    /**
     * Get contest
     *
     * @return \App\Entity\Contest
     */
    public function getContest()
    {
        return $this->contest;
    }

    /**
     * Set judging
     *
     * @param \App\Entity\Judging $judging
     *
     * @return InternalError
     */
    public function setJudging(\App\Entity\Judging $judging = null)
    {
        $this->judging = $judging;

        return $this;
    }

    /**
     * Get judging
     *
     * @return \App\Entity\Judging
     */
    public function getJudging()
    {
        return $this->judging;
    }
}
