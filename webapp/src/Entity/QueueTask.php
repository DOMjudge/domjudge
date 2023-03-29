<?php declare(strict_types=1);
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

/**
 * An item in the queue.
 */
#[ORM\Table(
    name: 'queuetask',
    options: [
        'collation' => 'utf8mb4_unicode_ci',
        'charset' => 'utf8mb4',
        'comment' => 'Work items.',
    ]
)]
#[ORM\Index(columns: ['queuetaskid'], name: 'queuetaskid')]
#[ORM\Index(columns: ['jobid'], name: 'jobid')]
#[ORM\Index(columns: ['priority'], name: 'priority')]
#[ORM\Index(columns: ['teampriority'], name: 'teampriority')]
#[ORM\Index(columns: ['teamid'], name: 'teamid')]
#[ORM\Index(columns: ['starttime'], name: 'starttime')]
#[ORM\Entity]
class QueueTask
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['comment' => 'Queuetask ID', 'unsigned' => true])]
    private int $queuetaskid;

    #[ORM\Column(
        nullable: true,
        options: ['comment' => 'All queuetasks with the same jobid belong together.', 'unsigned' => true]
    )]
    #[Serializer\Type('string')]
    private ?int $jobid = null;

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

    public function setJobId($jobid): QueueTask
    {
        $this->jobid = $jobid;
        return $this;
    }

    public function getJobId(): ?int
    {
        return $this->jobid;
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

    public function setStartTime(?float $startTime = null): QueueTask
    {
        $this->startTime = $startTime;
        return $this;
    }

    public function getStartTime(): string|float|null
    {
        return $this->startTime;
    }
}
