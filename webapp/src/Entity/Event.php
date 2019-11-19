<?php declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Log of all events during a contest
 *
 * @ORM\Table(
 *     name="event",
 *     options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4", "comment"="Log of all events during a contest"},
 *     indexes={
 *         @ORM\Index(name="eventtime", columns={"cid","eventtime"}),
 *         @ORM\Index(name="cid", columns={"cid"}),
 *         @ORM\Index(name="endpoint", columns={"cid","endpointtype","endpointid"})
 *     })
 * @ORM\Entity
 */
class Event
{
    /**
     * @var integer
     *
     * @ORM\Id
     * @ORM\Column(name="eventid", type="integer", nullable=false, length=4,
     *     options={"comment"="Event ID","unsigned"=true})
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $eventid;

    /**
     * @var double
     *
     * @ORM\Column(name="eventtime", type="decimal", precision=32, scale=9,
     *     nullable=false, options={"comment"="When the event occurred","unsigned"=true})
     */
    private $eventtime;

    /**
     * @var string
     *
     * @ORM\Column(name="endpointtype", type="string", length=32, nullable=false,
     *     options={"comment"="API endpoint associated to this entry"})
     */
    private $endpointtype;

    /**
     * @var string
     *
     * @ORM\Column(name="endpointid", type="string", length=64, nullable=false,
     *     options={"comment"="API endpoint (external) ID"})
     */
    private $endpointid;

    /**
     * @var string
     *
     * @ORM\Column(name="action", type="string", length=32, nullable=false,
     *     options={"comment"="Description of action performed"})
     */
    private $action;

    /**
     * @var resource
     *
     * @ORM\Column(name="content", type="binaryjson",
     *     options={"comment"="JSON encoded content of the change, as provided in the event feed"})
     */
    private $content;

    /**
     * @var integer
     *
     * @ORM\Column(type="integer", name="cid", length=4,
     *     options={"comment"="Contest ID","unsigned"=true}, nullable=false)
     */
    private $cid;

    /**
     * @var Contest
     *
     * @ORM\ManyToOne(targetEntity="Contest", inversedBy="problems")
     * @ORM\JoinColumn(name="cid", referencedColumnName="cid", onDelete="CASCADE")
     */
    private $contest;

    /**
     * Set eventid
     *
     * @param integer $eventid
     *
     * @return Event
     */
    public function setEventid($eventid)
    {
        $this->eventid = $eventid;

        return $this;
    }

    /**
     * Get eventid
     *
     * @return integer
     */
    public function getEventid()
    {
        return $this->eventid;
    }

    /**
     * Set eventtime
     *
     * @param double $eventtime
     *
     * @return Event
     */
    public function setEventtime($eventtime)
    {
        $this->eventtime = $eventtime;

        return $this;
    }

    /**
     * Get eventtime
     *
     * @return double
     */
    public function getEventtime()
    {
        return $this->eventtime;
    }

    /**
     * Set cid
     *
     * @param integer $cid
     *
     * @return Event
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
     * Set endpointtype
     *
     * @param string $endpointtype
     *
     * @return Event
     */
    public function setEndpointtype($endpointtype)
    {
        $this->endpointtype = $endpointtype;

        return $this;
    }

    /**
     * Get endpointtype
     *
     * @return string
     */
    public function getEndpointtype()
    {
        return $this->endpointtype;
    }

    /**
     * Set endpointid
     *
     * @param string $endpointid
     *
     * @return Event
     */
    public function setEndpointid($endpointid)
    {
        $this->endpointid = $endpointid;

        return $this;
    }

    /**
     * Get endpointid
     *
     * @return string
     */
    public function getEndpointid()
    {
        return $this->endpointid;
    }

    /**
     * Set action
     *
     * @param string $action
     *
     * @return Event
     */
    public function setAction($action)
    {
        $this->action = $action;

        return $this;
    }

    /**
     * Get action
     *
     * @return string
     */
    public function getAction()
    {
        return $this->action;
    }

    /**
     * Set content
     *
     * @param mixed $content
     *
     * @return Event
     */
    public function setContent($content)
    {
        $this->content = $content;

        return $this;
    }

    /**
     * Get content
     *
     * @return mixed
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * Set contest
     *
     * @param Contest|null $contest
     * @return Event
     */
    public function setContest($contest)
    {
        $this->contest = $contest;
        return $this;
    }

    /**
     * Get contest
     *
     * @return Contest|null
     */
    public function getContest()
    {
        return $this->contest;
    }
}
