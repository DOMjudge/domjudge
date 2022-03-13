<?php declare(strict_types=1);
namespace App\Entity;

use App\Utils\Utils;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

/**
 * Result of a testcase run.
 *
 * @ORM\Entity()
 * @ORM\Table(
 *     name="judging_run",
 *     options={"collation"="utf8mb4_unicode_ci", "charset"="utf8mb4", "comment"="Result of a testcase run within a judging"},
 *     uniqueConstraints={
 *         @ORM\UniqueConstraint(name="testcaseid", columns={"judgingid", "testcaseid"})
 *     },
 *     indexes={
 *         @ORM\Index(name="judgingid", columns={"judgingid"}),
 *         @ORM\Index(name="testcaseid_2", columns={"testcaseid"})
 *     })
 */
class JudgingRun extends BaseApiEntity
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="runid", length=4,
     *     options={"comment"="Run ID","unsigned"=true},
     *     nullable=false)
     * @Serializer\SerializedName("id")
     * @Serializer\Type("string")
     */
    protected int $runid;

    /**
     * @ORM\Column(type="integer", name="judgetaskid", length=4,
     *     options={"comment"="JudgeTask ID","unsigned"=true,"default"=NULL},
     *     nullable=true)
     * @Serializer\Exclude()
     */
    private ?int $judgetaskid;

    /**
     * @ORM\Column(type="string", name="runresult", length=32,
     *     options={"comment"="Result of this run, NULL if not finished yet"},
     *     nullable=true)
     * @Serializer\Exclude()
     */
    private ?string $runresult;

    /**
     * @var double|string|null
     * @ORM\Column(type="float", name="runtime",
     *     options={"comment"="Submission running time on this testcase"},
     *     nullable=true)
     * @Serializer\Exclude()
     */
    private $runtime;

    /**
     * @var double|string|null
     * @ORM\Column(type="decimal", precision=32, scale=9, name="endtime",
     *     options={"comment"="Time run judging ended", "unsigned"=true},
     *     nullable=true)
     * @Serializer\Exclude()
     */
    private $endtime;

    /**
     * @ORM\ManyToOne(targetEntity="Judging", inversedBy="runs")
     * @ORM\JoinColumn(name="judgingid", referencedColumnName="judgingid", onDelete="CASCADE")
     * @Serializer\Exclude()
     */
    private Judging $judging;

    /**
     * @ORM\ManyToOne(targetEntity="Testcase", inversedBy="judging_runs")
     * @ORM\JoinColumn(name="testcaseid", referencedColumnName="testcaseid")
     * @Serializer\Exclude()
     */
    private Testcase $testcase;

    /**
     * We use a OneToMany instead of a OneToOne here, because otherwise this
     * relation will always be loaded. See the commit message of commit
     * 9e421f96691ec67ed62767fe465a6d8751edd884 for a more elaborate explanation
     *
     * @var JudgingRunOutput[]|ArrayCollection
     * @ORM\OneToMany(targetEntity="JudgingRunOutput", mappedBy="run", cascade={"persist"}, orphanRemoval=true)
     * @Serializer\Exclude()
     */
    private Collection $output;

    /**
     * @ORM\ManyToOne(targetEntity="JudgeTask", inversedBy="judging_runs")
     * @ORM\JoinColumn(name="judgetaskid", referencedColumnName="judgetaskid")
     * @Serializer\Exclude()
     */
    private ?JudgeTask $judgetask;

    public function __construct()
    {
        $this->output = new ArrayCollection();
    }

    public function getRunid(): int
    {
        return $this->runid;
    }

    public function setJudgeTaskId(int $judgetaskid): JudgingRun
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

    public function setJudgeTask(JudgeTask $judgeTask): JudgingRun
    {
        $this->judgetask = $judgeTask;
        return $this;
    }

    public function setRunresult(string $runresult): JudgingRun
    {
        $this->runresult = $runresult;
        return $this;
    }

    public function getRunresult(): ?string
    {
        return $this->runresult;
    }

    public function setRuntime(float $runtime): JudgingRun
    {
        $this->runtime = $runtime;
        return $this;
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("run_time")
     * @Serializer\Type("float")
     */
    public function getRuntime(): ?float
    {
        return Utils::roundedFloat($this->runtime);
    }

    /** @param string|float $endtime */
    public function setEndtime($endtime): JudgingRun
    {
        $this->endtime = $endtime;
        return $this;
    }

    /** @return string|float */
    public function getEndtime()
    {
        return $this->endtime;
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("time")
     * @Serializer\Type("string")
     */
    public function getAbsoluteEndTime(): string
    {
        return Utils::absTime($this->getEndtime());
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("contest_time")
     * @Serializer\Type("string")
     */
    public function getRelativeEndTime(): string
    {
        return Utils::relTime($this->getEndtime() - $this->getJudging()->getContest()->getStarttime());
    }

    public function setJudging(?Judging $judging = null): JudgingRun
    {
        $this->judging = $judging;
        return $this;
    }

    public function getJudging(): Judging
    {
        return $this->judging;
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("judgement_id")
     * @Serializer\Type("string")
     */
    public function getJudgingId(): int
    {
        return $this->getJudging()->getJudgingid();
    }

    public function setTestcase(?Testcase $testcase = null): JudgingRun
    {
        $this->testcase = $testcase;
        return $this;
    }

    public function getTestcase(): Testcase
    {
        return $this->testcase;
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("ordinal")
     * @Serializer\Type("int")
     */
    public function getTestcaseRank(): int
    {
        return $this->getTestcase()->getRank();
    }

    public function setOutput(JudgingRunOutput $output): JudgingRun
    {
        $this->output->clear();
        $this->output->add($output);
        $output->setRun($this);

        return $this;
    }

    public function getOutput(): ?JudgingRunOutput
    {
        return $this->output->first() ?: null;
    }
}
