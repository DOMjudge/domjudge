<?php declare(strict_types=1);
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

/**
 * An item in the queue.
 *
 * @ORM\Entity()
 * @ORM\Table(
 *     name="queuetask",
 *     indexes={
 *         @ORM\Index(name="queuetaskid", columns={"queuetaskid"}),
 *         @ORM\Index(name="jobid", columns={"jobid"}),
 *         @ORM\Index(name="priority", columns={"priority"}),
 *         @ORM\Index(name="teampriority", columns={"teampriority"}),
 *         @ORM\Index(name="teamid", columns={"teamid"}),
 *         @ORM\Index(name="starttime", columns={"starttime"}),
 *     },
 *     options={"collation"="utf8mb4_unicode_ci", "charset"="utf8mb4", "comment"="Work items."}
 *     )
 */
class QueueTask
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="queuetaskid", length=4,
     *     options={"comment"="Queuetask ID","unsigned"=true},
     *     nullable=false)
     */
    private int $queuetaskid;

    /**
     * @ORM\Column(type="integer", name="jobid", length=4,
     *     options={"comment"="All queuetasks with the same jobid belong together.","unsigned"=true},
     *     nullable=true)
     * @Serializer\Type("string")
     */
    private ?int $jobid;

    /**
     * @ORM\Column(type="integer", name="priority", length=4,
     *     options={"comment"="Priority; negative means higher priority",
     *              "unsigned"=false},
     *     nullable=false)
     */
    private int $priority;

    /**
     * @ORM\Column(type="integer", name="teampriority", length=4,
     *     options={"comment"="Team Priority; somewhat magic, lower implies higher priority.",
     *              "unsigned"=false},
     *     nullable=false)
     */
    private int $teamPriority;

    /**
     * @ORM\ManyToOne(targetEntity="Team")
     * @ORM\JoinColumn(name="teamid", referencedColumnName="teamid", onDelete="CASCADE")
     * @Serializer\Exclude()
     */
    private ?Team $team;

    /**
     * @var double|string|null
     * @ORM\Column(type="decimal", precision=32, scale=9, name="starttime", options={"comment"="Time started work",
     *                             "unsigned"=true}, nullable=true)
     * @Serializer\Exclude()
     */
    private $startTime;

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

    /**
     * @return string|float|null
     */
    public function getStartTime()
    {
        return $this->startTime;
    }
}
