<?php declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Log of all events during a contest.
 */
#[ORM\Entity]
#[ORM\Table(options: [
    'collation' => 'utf8mb4_unicode_ci',
    'charset' => 'utf8mb4',
    'comment' => 'Log of all events during a contest',
])]
#[ORM\Index(columns: ['cid', 'eventtime'], name: 'eventtime')]
#[ORM\Index(columns: ['cid'], name: 'cid')]
#[ORM\Index(columns: ['cid', 'endpointtype', 'endpointid'], name: 'endpoint')]
class Event
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(options: ['comment' => 'Event ID', 'unsigned' => true])]
    private int $eventid;

    #[ORM\Column(
        type: 'decimal',
        precision: 32,
        scale: 9,
        options: ['comment' => 'When the event occurred', 'unsigned' => true]
    )]
    private string|float $eventtime;

    #[ORM\Column(length: 32, options: ['comment' => 'API endpoint associated to this entry'])]
    private string $endpointtype;

    #[ORM\Column(length: 64, options: ['comment' => 'API endpoint (external) ID'])]
    private string $endpointid;

    #[ORM\Column(length: 32, options: ['comment' => 'Description of action performed'])]
    private string $action;

    /**
     * @var resource
     */
    #[ORM\Column(
        type: 'binaryjson',
        options: ['comment' => 'JSON encoded content of the change, as provided in the event feed']
    )]
    private $content;

    #[ORM\ManyToOne(inversedBy: 'problems')]
    #[ORM\JoinColumn(name: 'cid', referencedColumnName: 'cid', onDelete: 'CASCADE')]
    private ?Contest $contest = null;

    public function setEventid(int $eventid): Event
    {
        $this->eventid = $eventid;
        return $this;
    }

    public function getEventid(): int
    {
        return $this->eventid;
    }

    public function setEventtime(string|float $eventtime): Event
    {
        $this->eventtime = $eventtime;
        return $this;
    }

    public function getEventtime(): string|float
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

    public function setContent(mixed $content): Event
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
