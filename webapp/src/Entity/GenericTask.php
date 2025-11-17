<?php declare(strict_types=1);
namespace App\Entity;

use App\Utils\Utils;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

/**
 * Result of a generic task.
 */
#[ORM\Entity]
#[ORM\Table(options: [
    'collation' => 'utf8mb4_unicode_ci',
    'charset' => 'utf8mb4',
    'comment' => 'Result of a generic task',
])]
class GenericTask extends BaseApiEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['comment' => 'Task ID', 'unsigned' => true])]
    #[Serializer\SerializedName('id')]
    #[Serializer\Type('string')]
    protected int $taskid;

    #[ORM\Column(
        nullable: true,
        options: ['comment' => 'JudgeTask ID', 'unsigned' => true, 'default' => null]
    )]
    #[Serializer\Exclude]
    private ?int $judgetaskid = null;

    #[ORM\Column(
        nullable: true,
        options: ['comment' => 'Running time for this task']
    )]
    #[Serializer\Exclude]
    private string|float|null $runtime = null;

    #[ORM\Column(
        type: 'decimal',
        precision: 32,
        scale: 9,
        nullable: true,
        options: ['comment' => 'Time task ended', 'unsigned' => true]
    )]
    #[Serializer\Exclude]
    private string|float|null $endtime = null;

    #[ORM\Column(
        type: 'decimal',
        precision: 32,
        scale: 9,
        nullable: true,
        options: ['comment' => 'Time task started', 'unsigned' => true]
    )]
    #[Serializer\Exclude]
    private string|float|null $startTime = null;

    /**
     * @var Collection<int, GenericTaskOutput>
     *
     * We use a OneToMany instead of a OneToOne here, because otherwise this
     * relation will always be loaded. See the commit message of commit
     * 9e421f96691ec67ed62767fe465a6d8751edd884 for a more elaborate explanation
     */
    #[ORM\OneToMany(mappedBy: 'run', targetEntity: GenericTaskOutput::class, cascade: ['persist'], orphanRemoval: true)]
    #[Serializer\Exclude]
    private Collection $output;

    #[ORM\ManyToOne(inversedBy: 'judging_runs')]
    #[ORM\JoinColumn(name: 'judgetaskid', referencedColumnName: 'judgetaskid', onDelete: 'CASCADE')]
    #[Serializer\Exclude]
    private ?JudgeTask $judgetask = null;

    public function __construct()
    {
        $this->output = new ArrayCollection();
    }

    public function getTaskid(): int
    {
        return $this->taskid;
    }

    public function setJudgeTaskId(int $judgetaskid): GenericTask
    {
        $this->judgetaskid = $judgetaskid;
        return $this;
    }

    public function getJudgeTaskId(): ?int
    {
        return $this->judgetaskid;
    }

    public function getJudgeTask(): ?JudgeTask
    {
        return $this->judgetask;
    }

    public function setJudgeTask(JudgeTask $judgeTask): GenericTask
    {
        $this->judgetask = $judgeTask;
        return $this;
    }

    public function setRuntime(string|float $runtime): GenericTask
    {
        $this->runtime = $runtime;
        return $this;
    }

    #[Serializer\VirtualProperty]
    #[Serializer\SerializedName('run_time')]
    #[Serializer\Type('float')]
    public function getRuntime(): string|float|null
    {
        return Utils::roundedFloat($this->runtime);
    }

    public function setEndtime(string|float $endtime): GenericTask
    {
        $this->endtime = $endtime;
        return $this;
    }

    public function setStarttime(string|float $startTime): GenericTask
    {
        $this->startTime = $startTime;
        return $this;
    }

    public function getStarttime(): string|float|null
    {
        return $this->startTime;
    }

    public function getEndtime(): string|float|null
    {
        return $this->endtime;
    }

    #[Serializer\VirtualProperty]
    #[Serializer\SerializedName('time')]
    #[Serializer\Type('string')]
    public function getAbsoluteEndTime(): string
    {
        return Utils::absTime($this->getEndtime());
    }

    public function setOutput(GenericTaskOutput $output): GenericTask
    {
        $this->output->clear();
        $this->output->add($output);
        $output->setGenericTask($this);

        return $this;
    }

    public function getOutput(): ?GenericTaskOutput
    {
        return $this->output->first() ?: null;
    }
}
