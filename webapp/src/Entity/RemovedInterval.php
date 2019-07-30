<?php declare(strict_types=1);

namespace App\Entity;

use App\Utils\Utils;
use App\Validator\Constraints\TimeString;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Time intervals removed from the contest for scoring
 * @ORM\Entity()
 * @ORM\Table(
 *     name="removed_interval",
 *     options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4", "comment"="Time intervals removed from the contest for scoring"},
 *     indexes={@ORM\Index(name="cid", columns={"cid"})})
 */
class RemovedInterval
{
    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="intervalid", length=4,
     *     options={"comment"="Removed interval ID","unsigned"=true}, nullable=false)
     */
    private $intervalid;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", name="cid", length=4,
     *     options={"comment"="Contest ID","unsigned"=true}, nullable=false)
     */
    private $cid;

    /**
     * @var double
     * @ORM\Column(type="decimal", precision=32, scale=9, name="starttime",
     *     options={"comment"="Initial time of removed interval", "unsigned"=true},
     *     nullable=false)
     */
    private $starttime;

    /**
     * @var double
     * @ORM\Column(type="decimal", precision=32, scale=9, name="endtime",
     *     options={"comment"="Final time of removed interval", "unsigned"=true},
     *     nullable=false)
     */
    private $endtime;

    /**
     * @var string
     * @ORM\Column(type="string", length=64, name="starttime_string",
     *     options={"comment"="Authoritative (absolute only) string representation of starttime"},
     *     nullable=false)
     * @TimeString(allowRelative=false)
     */
    private $starttimeString;

    /**
     * @var string
     * @ORM\Column(type="string", length=64, name="endtime_string",
     *     options={"comment"="Authoritative (absolute only) string representation of endtime"},
     *     nullable=false)
     * @TimeString(allowRelative=false)
     */
    private $endtimeString;

    /**
     * @ORM\ManyToOne(targetEntity="Contest", inversedBy="removedIntervals")
     * @ORM\JoinColumn(name="cid", referencedColumnName="cid", onDelete="CASCADE")
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
     * @throws \Exception
     */
    public function setStarttimeString($starttimeString)
    {
        $this->starttimeString = $starttimeString;
        $date                  = new \DateTime($starttimeString);
        $this->starttime       = $date->format('U.v');

        return $this;
    }

    /**
     * Get starttimeString
     *
     * @return string
     */
    public function getStarttimeString()
    {
        return $this->starttimeString;
    }

    /**
     * Set endtimeString
     *
     * @param string $endtimeString
     *
     * @return RemovedInterval
     * @throws \Exception
     */
    public function setEndtimeString($endtimeString)
    {
        $this->endtimeString = $endtimeString;
        $date                = new \DateTime($endtimeString);
        $this->endtime       = $date->format('U.v');

        return $this;
    }

    /**
     * Get endtimeString
     *
     * @return string
     */
    public function getEndtimeString()
    {
        return $this->endtimeString;
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

    /**
     * @param ExecutionContextInterface $context
     * @Assert\Callback()
     */
    public function validate(ExecutionContextInterface $context)
    {
        // Update all contest timing, taking into account all removed intervals
        $this->getContest()->setStarttimeString($this->getContest()->getStarttimeString());

        if ($this->getEndtime() <= $this->getStarttime()) {
            $context
                ->buildViolation('Interval ends before (or when) it starts')
                ->atPath('starttimeString')
                ->addViolation();

            $context
                ->buildViolation('Interval ends before (or when) it starts')
                ->atPath('endtimeString')
                ->addViolation();
        }

        if (Utils::difftime((float)$this->getStarttime(), (float)$this->getContest()->getStarttime(true)) < 0) {
            $context
                ->buildViolation('Interval starttime outside of contest')
                ->atPath('starttimeString')
                ->addViolation();
        }
        if (Utils::difftime((float)$this->getEndtime(), (float)$this->getContest()->getEndtime()) > 0) {
            $context
                ->buildViolation('Interval endtime outside of contest')
                ->atPath('endtimeString')
                ->addViolation();
        }

        /** @var RemovedInterval $removedInterval */
        foreach ($this->getContest()->getRemovedIntervals() as $removedInterval) {
            if ($removedInterval->getIntervalid() === $this->getIntervalid()) {
                continue;
            }

            if ((Utils::difftime((float)$this->getStarttime(), (float)$removedInterval->getStarttime()) >= 0 &&
                    Utils::difftime((float)$this->getStarttime(), (float)$removedInterval->getEndtime()) < 0) ||
                (Utils::difftime((float)$this->getEndtime(), (float)$removedInterval->getStarttime()) > 0 &&
                    Utils::difftime((float)$this->getEndtime(), (float)$removedInterval->getEndtime()) <= 0)) {
                $context
                    ->buildViolation(sprintf('Interval overlaps with interval %d', $removedInterval->getIntervalid()))
                    ->atPath('starttimeString')
                    ->addViolation();

                $context
                    ->buildViolation(sprintf('Interval overlaps with interval %d', $removedInterval->getIntervalid()))
                    ->atPath('endtimeString')
                    ->addViolation();
            }
        }
    }
}
