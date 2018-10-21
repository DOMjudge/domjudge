<?php declare(strict_types=1);

namespace DOMJudgeBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Time intervals removed from the contest for scoring
 * @ORM\Entity()
 * @ORM\Table(name="removed_interval", options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4"})
 */
class RemovedInterval
{
    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="intervalid", options={"comment"="Unique ID"}, nullable=false)
     */
    private $intervalid;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", name="cid", options={"comment"="Contest ID"}, nullable=false)
     */
    private $cid;

    /**
     * @var double
     * @ORM\Column(type="decimal", precision=32, scale=9, name="starttime", options={"comment"="Initial time of removed interval", "unsigned"=true}, nullable=false)
     */
    private $starttime;

    /**
     * @var double
     * @ORM\Column(type="decimal", precision=32, scale=9, name="endtime", options={"comment"="Final time of removed interval", "unsigned"=true}, nullable=false)
     */
    private $endtime;

    /**
     * @var string
     * @ORM\Column(type="string", length=64, name="starttime_string", options={"comment"="Authoritative (absolute only) string representation of starttime"}, nullable=false)
     */
    private $starttime_string;

    /**
     * @var string
     * @ORM\Column(type="string", length=64, name="endtime_string", options={"comment"="Authoritative (absolute only) string representation of endtime"}, nullable=false)
     */
    private $endtime_string;

    /**
     * @ORM\ManyToOne(targetEntity="Contest", inversedBy="removed_intervals")
     * @ORM\JoinColumn(name="cid", referencedColumnName="cid")
     */
    private $contest;

    /**
     * Get intervalid
     *
     * @return integer
     */
    public function getIntervalid()
    {
        return $this->intervalid;
    }

    /**
     * Set cid
     *
     * @param integer $cid
     *
     * @return RemovedInterval
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
     * Set starttime
     *
     * @param string $starttime
     *
     * @return RemovedInterval
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
     * @return RemovedInterval
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
     * Set starttimeString
     *
     * @param string $starttimeString
     *
     * @return RemovedInterval
     */
    public function setStarttimeString($starttimeString)
    {
        $this->starttime_string = $starttimeString;

        return $this;
    }

    /**
     * Get starttimeString
     *
     * @return string
     */
    public function getStarttimeString()
    {
        return $this->starttime_string;
    }

    /**
     * Set endtimeString
     *
     * @param string $endtimeString
     *
     * @return RemovedInterval
     */
    public function setEndtimeString($endtimeString)
    {
        $this->endtime_string = $endtimeString;

        return $this;
    }

    /**
     * Get endtimeString
     *
     * @return string
     */
    public function getEndtimeString()
    {
        return $this->endtime_string;
    }

    /**
     * Set contest
     *
     * @param Contest $contest
     *
     * @return RemovedInterval
     */
    public function setContest(Contest $contest = null)
    {
        $this->contest = $contest;

        return $this;
    }

    /**
     * Get contest
     *
     * @return Contest
     */
    public function getContest()
    {
        return $this->contest;
    }
}
