<?php declare(strict_types=1);
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

/**
 * An item in the queue.
 */
#[ORM\Entity]
#[ORM\Table(
    name: 'queuetask',
    options: [
        'collation' => 'utf8mb4_unicode_ci',
        'charset' => 'utf8mb4',
        'comment' => 'Work items.',
    ]
)]
#[ORM\Index(name: 'queuetaskid', columns: ['queuetaskid'])]
#[ORM\Index(name: 'judgingid', columns: ['judgingid'])]
#[ORM\Index(name: 'priority', columns: ['priority'])]
#[ORM\Index(name: 'teampriority', columns: ['teampriority'])]
#[ORM\Index(name: 'teamid', columns: ['teamid'])]
#[ORM\Index(name: 'starttime', columns: ['starttime'])]
class QueueTask
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['comment' => 'Queuetask ID', 'unsigned' => true])]
    private int $queuetaskid;

    #[ORM\ManyToOne(inversedBy: 'queueTasks')]
    #[ORM\JoinColumn(name: 'judgingid', referencedColumnName: 'judgingid', onDelete: 'CASCADE')]
    #[Serializer\Exclude]
    private Judging $judging;

    #[ORM\Column(options: [
        'comment' => 'Priority; negative means higher priority',
        'unsigned' => false,
    ])]
    private int $priority;

    #[ORM\Column(
        name: 'teampriority',
        options: [
            'comment' => 'Team Priority; somewhat magic, lower implies higher priority.',
            'unsigned' => false,
        ]
    )]
    private int $teamPriority;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'teamid', referencedColumnName: 'teamid', onDelete: 'CASCADE')]
    #[Serializer\Exclude]
    private ?Team $team = null;

    #[ORM\Column(
        name: 'starttime',
        type: 'decimal',
        precision: 32,
        scale: 9,
        nullable: true,
        options: ['comment' => 'Time started work', 'unsigned' => true]
    )]
    #[Serializer\Exclude]
    private float|string|null $startTime = null;

    public function getQueueTaskid(): int
    {
        return $this->queuetaskid;
    }

    public function setJudging(Judging $judging): QueueTask
    {
        $this->judging = $judging;
        return $this;
    }

    public function getJudging(): Judging
    {
        return $this->judging;
    }

    public function setPriority(int $priority): QueueTask
    {
        $this->priority = $priority;
        return $this;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setTeamPriority(int $teamPriority): QueueTask
    {
        $this->teamPriority = $teamPriority;
        return $this;
    }

    public function getTeamPriority(): int
    {
        return $this->teamPriority;
    }

    public function setTeam(?Team $team = null): QueueTask
    {
        $this->team = $team;
        return $this;
    }

    public function getTeam(): ?Team
    {
        return $this->team;
    }

    public function setStartTime(string|float|null $startTime = null): QueueTask
    {
        $this->startTime = $startTime;
        return $this;
    }

    public function getStartTime(): string|float|null
    {
        return $this->startTime;
    }
}
