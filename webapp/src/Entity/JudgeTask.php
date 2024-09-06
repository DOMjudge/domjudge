<?php declare(strict_types=1);
namespace App\Entity;

use App\Doctrine\DBAL\Types\JudgeTaskType;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

/**
 * Individual judge tasks.
 */
#[ORM\Entity]
#[ORM\Table(
    name: 'judgetask',
    options: [
        'collation' => 'utf8mb4_unicode_ci',
        'charset' => 'utf8mb4',
        'comment' => 'Individual judge tasks.',
    ]
)]
#[ORM\Index(columns: ['judgehostid'], name: 'judgehostid')]
#[ORM\Index(columns: ['priority'], name: 'priority')]
#[ORM\Index(columns: ['jobid'], name: 'jobid')]
#[ORM\Index(columns: ['submitid'], name: 'submitid')]
#[ORM\Index(columns: ['valid'], name: 'valid')]
#[ORM\Index(columns: ['judgehostid', 'jobid'], name: 'judgehostid_jobid')]
#[ORM\Index(columns: ['judgehostid', 'valid', 'priority'], name: 'judgehostid_valid_priority')]
#[ORM\Index(
    columns: ['judgehostid', 'starttime', 'valid', 'type', 'priority', 'judgetaskid'],
    name: 'specific_type')
]
class JudgeTask
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['comment' => 'Judgetask ID', 'unsigned' => true])]
    private int $judgetaskid;

    #[ORM\ManyToOne(inversedBy: 'judgetasks')]
    #[ORM\JoinColumn(name: 'judgehostid', referencedColumnName: 'judgehostid', onDelete: 'SET NULL')]
    #[Serializer\Exclude]
    private ?Judgehost $judgehost = null;

    #[ORM\Column(
        type: 'judge_task_type',
        options: ['comment' => 'Type of the judge task.', 'default' => 'judging_run']
    )]
    private string $type = JudgeTaskType::JUDGING_RUN;

    #[ORM\Column(options: ['comment' => 'Priority; negative means higher priority', 'unsigned' => false])]
    private int $priority;

    final public const PRIORITY_HIGH = -10;
    final public const PRIORITY_DEFAULT = 0;
    final public const PRIORITY_LOW = 10;

    #[ORM\Column(
        nullable: true,
        options: ['comment' => 'All judgetasks with the same jobid belong together.', 'unsigned' => true]
    )]
    #[Serializer\Type('string')]
    private ?int $jobid = null;

    #[ORM\Column(
        nullable: true,
        options: ['comment' => 'Optional UUID for the associated judging, used for caching.']
    )]
    private ?string $uuid = null;


    #[ORM\ManyToOne()]
    #[ORM\JoinColumn(name: 'submitid', nullable: true, referencedColumnName: 'submitid', onDelete: 'CASCADE',
        options: ['comment' => 'Submission ID being judged', 'unsigned' => true])
    ]
    #[Serializer\Exclude]
    private ?Submission $submission = null;

    #[Serializer\VirtualProperty]
    #[Serializer\SerializedName('submitid')]
    #[Serializer\Type('string')]
    public function getSubmitid(): ?int
    {
        return $this->submission?->getSubmitid();
    }


    // Note that we rely on the fact here that files with an ID are immutable,
    // so clients are allowed to cache them on disk.
    #[ORM\Column(nullable: true, options: ['comment' => 'Compile script ID', 'unsigned' => true])]
    #[Serializer\Type('string')]
    private ?int $compile_script_id = null;

    #[ORM\Column(nullable: true, options: ['comment' => 'Run script ID', 'unsigned' => true])]
    #[Serializer\Type('string')]
    private ?int $run_script_id = null;

    #[ORM\Column(nullable: true, options: ['comment' => 'Compare script ID', 'unsigned' => true])]
    #[Serializer\Type('string')]
    private ?int $compare_script_id = null;

    #[ORM\Column(nullable: true, options: ['comment' => 'Testcase ID', 'unsigned' => true])]
    #[Serializer\Type('string')]
    private ?int $testcase_id = null;

    #[ORM\Column(length: 100, nullable: true, options: ['comment' => 'Testcase Hash'])]
    #[Serializer\Type('string')]
    private ?string $testcase_hash = null;

    #[ORM\Column(
        type: 'text',
        nullable: true,
        options: [
            'comment' => 'The compile config as JSON-blob.',
            'collation' => 'utf8mb4_bin',
            'default' => null,
        ]
    )]
    protected ?string $compile_config = null;

    #[ORM\Column(
        type: 'text',
        nullable: true,
        options: [
            'comment' => 'The run config as JSON-blob.',
            'collation' => 'utf8mb4_bin',
            'default' => null,
        ]
    )]
    protected ?string $run_config = null;

    #[ORM\Column(
        type: 'text',
        nullable: true,
        options: [
            'comment' => 'The compare config as JSON-blob.',
            'collation' => 'utf8mb4_bin',
            'default' => null,
        ]
    )]
    protected ?string $compare_config = null;

    #[ORM\Column(options: ['comment' => 'Only handed out if still valid.', 'default' => 1])]
    #[Serializer\Exclude]
    protected bool $valid = true;

    #[ORM\Column(
        type: 'decimal',
        precision: 32,
        scale: 9,
        nullable: true,
        options: ['comment' => 'Time the judgetask was started', 'unsigned' => true]
    )]
    #[Serializer\Exclude]
    private string|float|null $starttime = null;

    /**
     * @var Collection<int, JudgingRun>
     */
    #[ORM\OneToMany(mappedBy: 'judgetask', targetEntity: JudgingRun::class)]
    #[Serializer\Exclude]
    private Collection $judging_runs;

    #[ORM\ManyToOne(inversedBy: 'judgeTasks')]
    #[ORM\JoinColumn(name: 'versionid', referencedColumnName: 'versionid', onDelete: 'SET NULL')]
    #[Serializer\Exclude]
    private ?Version $version = null;

    public function __construct()
    {
        $this->judging_runs  = new ArrayCollection();
    }

    public function getJudgetaskid(): int
    {
        return $this->judgetaskid;
    }

    public function setJudgehost(?Judgehost $judgehost = null): JudgeTask
    {
        $this->judgehost = $judgehost;
        return $this;
    }

    public function getJudgehost(): ?Judgehost
    {
        return $this->judgehost;
    }

    public function setType(string $type): JudgeTask
    {
        $this->type = $type;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setPriority(int $priority): JudgeTask
    {
        $this->priority = $priority;
        return $this;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setJobId(?int $jobid): JudgeTask
    {
        $this->jobid = $jobid;
        return $this;
    }

    public function getJobId(): ?int
    {
        return $this->jobid;
    }

    public function setUuid(string $uuid): JudgeTask
    {
        $this->uuid = $uuid;
        return $this;
    }

    public function getUuid(): ?string
    {
        return $this->uuid;
    }

    public function setSubmission(?Submission $submission): JudgeTask
    {
        $this->submission = $submission;
        return $this;
    }

    public function getSubmission(): ?Submission
    {
        return $this->submission;
    }

    public function setCompileScriptId(int $compile_script_id): JudgeTask
    {
        $this->compile_script_id = $compile_script_id;
        return $this;
    }

    public function getCompileScriptId(): int
    {
        return $this->compile_script_id;
    }

    public function setRunScriptId(int $run_script_id): JudgeTask
    {
        $this->run_script_id = $run_script_id;
        return $this;
    }

    public function getRunScriptId(): ?int
    {
        return $this->run_script_id;
    }

    public function setCompareScriptId(int $compare_script_id): JudgeTask
    {
        $this->compare_script_id = $compare_script_id;
        return $this;
    }

    public function getCompareScriptId(): int
    {
        return $this->compare_script_id;
    }

    public function setTestcaseId(int $testcase_id): JudgeTask
    {
        $this->testcase_id = $testcase_id;
        return $this;
    }

    public function getTestcaseId(): int
    {
        return $this->testcase_id;
    }

    public function setTestcaseHash(?string $testcase_hash): JudgeTask
    {
        $this->testcase_hash = $testcase_hash;
        return $this;
    }

    public function getTestcaseHash(): ?string
    {
        return $this->testcase_hash;
    }

    public function setCompileConfig(string $compile_config): JudgeTask
    {
        $this->compile_config = $compile_config;
        return $this;
    }

    public function getCompileConfig(): string
    {
        return $this->compile_config;
    }

    public function setRunConfig(string $run_config): JudgeTask
    {
        $this->run_config = $run_config;
        return $this;
    }

    public function getRunConfig(): string
    {
        return $this->run_config;
    }

    public function setCompareConfig(string $compare_config): JudgeTask
    {
        $this->compare_config = $compare_config;
        return $this;
    }

    public function getCompareConfig(): string
    {
        return $this->compare_config;
    }

    public function setValid(bool $valid): JudgeTask
    {
        $this->valid = $valid;
        return $this;
    }

    public function getValid(): bool
    {
        return $this->valid;
    }

    public function setStarttime(string|float|null $starttime): JudgeTask
    {
        $this->starttime = $starttime;
        return $this;
    }

    public function getStarttime(): string|float|null
    {
        return $this->starttime;
    }

    public static function parsePriority(string $priorityString): int
    {
        return match ($priorityString) {
            'low' => JudgeTask::PRIORITY_LOW,
            'high' => JudgeTask::PRIORITY_HIGH,
            default => JudgeTask::PRIORITY_DEFAULT,
        };
    }

    public function addJudgingRun(JudgingRun $judgingRun): JudgeTask
    {
        $this->judging_runs[] = $judgingRun;
        return $this;
    }

    /**
     * @return Collection<int, JudgingRun>
     */
    public function getJudgingRuns(): Collection
    {
        return $this->judging_runs;
    }

    /**
     * Gets the first judging run for this judgetask.
     *
     * This is useful when this judgetask is joined to a single run to get code completion in Twig templates.
     */
    public function getFirstJudgingRun(): ?JudgingRun
    {
        return $this->judging_runs->first() ?: null;
    }

    public function setVersion(Version $version): JudgeTask
    {
        $this->version = $version;
        return $this;
    }

    public function getVersion(): ?Version
    {
        return $this->version;
    }
}
