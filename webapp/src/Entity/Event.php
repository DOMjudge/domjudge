<?php declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Log of all events during a contest.
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
     * @var Contest
     *
     * @ORM\ManyToOne(targetEntity="Contest", inversedBy="problems")
     * @ORM\JoinColumn(name="cid", referencedColumnName="cid", onDelete="CASCADE")
     */
    private $contest;

    public function setEventid(int $eventid): Event
    {
        $this->eventid = $eventid;
        return $this;
    }

    public function getEventid(): int
    {
        return $this->eventid;
    }

    /** @param string|float $eventtime */
    public function setEventtime($eventtime): Event
    {
        $this->eventtime = $eventtime;
        return $this;
    }

    /** @return string|float */
    public function getEventtime()
    {
        return $this->eventtime;
    }

    public function setEndpointtype(string $endpointtype): Event
    {
        $this->endpointtype = $endpointtype;
        return $this;
    }

    public function getEndpointtype(): string
    {
        return $this->endpointtype;
    }

    public function setEndpointid(string $endpointid): Event
    {
        $this->endpointid = $endpointid;
        return $this;
    }

    public function getEndpointid(): string
    {
        return $this->endpointid;
    }

    public function setAction(string $action): Event
    {
        $this->action = $action;
        return $this;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    /**
     * @param mixed $content
     */
    public function setContent($content): Event
    {
        $this->content = $content;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getContent()
    {
        return $this->content;
    }

    public function setContest(?Contest $contest): Event
    {
        $this->contest = $contest;
        return $this;
    }

    public function getContest(): ?Contest
    {
        return $this->contest;
    }
}
