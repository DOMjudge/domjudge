<?php declare(strict_types=1);
namespace App\Entity;

use App\Doctrine\DBAL\Types\JudgeTaskType;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

/**
 * Individual judge tasks.
 *
 * @ORM\Entity()
 * @ORM\Table(
 *     name="judgetask",
 *     indexes={
 *         @ORM\Index(name="judgehostid", columns={"judgehostid"}),
 *         @ORM\Index(name="priority", columns={"priority"}),
 *         @ORM\Index(name="jobid", columns={"jobid"}),
 *         @ORM\Index(name="submitid", columns={"submitid"}),
 *         @ORM\Index(name="valid", columns={"valid"}),
 *         @ORM\Index(name="judgehostid_jobid", columns={"judgehostid", "jobid"}),
 *         @ORM\Index(name="judgehostid_valid_priority", columns={"judgehostid", "valid", "priority"}),
 *         @ORM\Index(name="specific_type", columns={"judgehostid", "starttime", "valid", "type", "priority", "judgetaskid"}),
 *     },
 *     options={"collation"="utf8mb4_unicode_ci", "charset"="utf8mb4", "comment"="Individual judge tasks."}
 *     )
 */
class JudgeTask
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="judgetaskid", length=4,
     *     options={"comment"="Judgetask ID","unsigned"=true},
     *     nullable=false)
     */
    private int $judgetaskid;

    /**
     * @ORM\ManyToOne(targetEntity="Judgehost", inversedBy="judgetasks")
     * @ORM\JoinColumn(name="judgehostid", referencedColumnName="judgehostid")
     * @Serializer\Exclude()
     */
    private ?Judgehost $judgehost;

    /**
     * @ORM\Column(type="judge_task_type", name="type",
     *     options={"comment"="Type of the judge task.","default"="judging_run"},
     *     nullable=false)
     */
    private string $type = JudgeTaskType::JUDGING_RUN;

    /**
     * @ORM\Column(type="integer", name="priority", length=4,
     *     options={"comment"="Priority; negative means higher priority",
     *              "unsigned"=false},
     *     nullable=false)
     */
    private int $priority;

    const PRIORITY_HIGH = -10;
    const PRIORITY_DEFAULT = 0;
    const PRIORITY_LOW = 10;

    /**
     * @ORM\Column(type="integer", name="jobid", length=4,
     *     options={"comment"="All judgetasks with the same jobid belong together.","unsigned"=true},
     *     nullable=true)
     * @Serializer\Type("string")
     */
    private ?int $jobid;

    /**
     * @ORM\Column(type="string", name="uuid",
     *     options={"comment"="Optional UUID for the associated judging, used for caching."},
     *     nullable=true)
     */
    private ?string $uuid;

    /**
     * @ORM\Column(type="integer", name="submitid", length=4,
     *     options={"comment"="Submission ID being judged","unsigned"=true},
     *     nullable=true)
     * @Serializer\Type("string")
     */
    private ?int $submitid;

    // Note that we rely on the fact here that files with an ID are immutable,
    // so clients are allowed to cache them on disk.

    /**
     * @ORM\Column(type="integer", name="compile_script_id", length=4,
     *     options={"comment"="Compile script ID","unsigned"=true},
     *     nullable=true)
     * @Serializer\Type("string")
     */
    private ?int $compile_script_id;

    /**
     * @ORM\Column(type="integer", name="run_script_id", length=4,
     *     options={"comment"="Run script ID","unsigned"=true},
     *     nullable=true)
     * @Serializer\Type("string")
     */
    private ?int $run_script_id;

    /**
     * @ORM\Column(type="integer", name="compare_script_id", length=4,
     *     options={"comment"="Compare script ID","unsigned"=true},
     *     nullable=true)
     * @Serializer\Type("string")
     */
    private ?int $compare_script_id;

    /**
     * @ORM\Column(type="integer", name="testcase_id", length=4,
     *     options={"comment"="Testcase ID","unsigned"=true},
     *     nullable=true)
     * @Serializer\Type("string")
     */
    private ?int $testcase_id;

    /**
     * @ORM\Column(type="string", name="testcase_hash", length=100,
     *     options={"comment"="Testcase Hash"},
     *     nullable=true)
     * @Serializer\Type("string")
     */
    private ?string $testcase_hash;

    /**
     * @ORM\Column(type="text", name="compile_config",
     *     options={"comment"="The compile config as JSON-blob.",
     *              "collation"="utf8mb4_bin", "default"="NULL"},
     *     nullable=true)
     */
    protected ?string $compile_config;

    /**
     * @ORM\Column(type="text", name="run_config",
     *     options={"comment"="The run config as JSON-blob.",
     *              "collation"="utf8mb4_bin", "default"="NULL"},
     *     nullable=true)
     */
    protected ?string $run_config;

    /**
     * @ORM\Column(type="text", name="compare_config",
     *     options={"comment"="The compare config as JSON-blob.",
     *              "collation"="utf8mb4_bin", "default"="NULL"},
     *     nullable=true)
     */
    protected ?string $compare_config;

    /**
     * @ORM\Column(type="boolean", name="valid",
     *     options={"comment"="Only handed out if still valid.",
     *              "default"="1"},
     *     nullable=false)
     * @Serializer\Exclude()
     */
    protected bool $valid = true;

    /**
     * @var double|string
     * @ORM\Column(type="decimal", precision=32, scale=9, name="starttime",
     *     options={"comment"="Time the judgetask was started", "unsigned"=true},
     *     nullable=true)
     * @Serializer\Exclude()
     */
    private $starttime;

    /**
     * @ORM\OneToMany(targetEntity="JudgingRun", mappedBy="judgetask")
     * @Serializer\Exclude()
     */
    private Collection $judging_runs;

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

    public function setJobId($jobid): JudgeTask
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

    public function setSubmitid(int $submitid): JudgeTask
    {
        $this->submitid = $submitid;
        return $this;
    }

    public function getSubmitid(): ?int
    {
        return $this->submitid;
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

    /** @param string|float $starttime */
    public function setStarttime($starttime): JudgeTask
    {
        $this->starttime = $starttime;
        return $this;
    }

    /** @return string|float|null */
    public function getStarttime()
    {
        return $this->starttime;
    }

    public static function parsePriority(string $priorityString): int
    {
        switch ($priorityString) {
            case 'low':
                return JudgeTask::PRIORITY_LOW;
            case 'high':
                return JudgeTask::PRIORITY_HIGH;
            default:
                return JudgeTask::PRIORITY_DEFAULT;
        }
    }

    public function addJudgingRun(JudgingRun $judgingRun): JudgeTask
    {
        $this->judging_runs[] = $judgingRun;
        return $this;
    }

    public function removeJudgingRun(JudgingRun $judgingRun): JudgeTask
    {
        $this->judging_runs->removeElement($judgingRun);
        return $this;
    }

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
}
