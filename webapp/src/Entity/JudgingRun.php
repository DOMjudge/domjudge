<?php declare(strict_types=1);
namespace App\Entity;

use App\Utils\Utils;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

/**
 * Result of a testcase run.
 * @ORM\Entity()
 * @ORM\Table(
 *     name="judging_run",
 *     options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4", "comment"="Result of a testcase run within a judging"},
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
     * @var int
     *
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="runid", length=4,
     *     options={"comment"="Run ID","unsigned"=true},
     *     nullable=false)
     * @Serializer\SerializedName("id")
     * @Serializer\Type("string")
     */
    protected $runid;

    /**
     * @var int
     * @ORM\Column(type="integer", name="judgingid", length=4,
     *     options={"comment"="Judging ID","unsigned"=true},
     *     nullable=false)
     * @Serializer\SerializedName("judgement_id")
     * @Serializer\Type("string")
     */
    private $judgingid;

    /**
     * @var int
     * @ORM\Column(type="integer", name="testcaseid", length=4,
     *     options={"comment"="Testcase ID","unsigned"=true},
     *     nullable=false)
     * @Serializer\Exclude()
     */
    private $testcaseid;

    /**
     * @var string
     * @ORM\Column(type="string", name="runresult", length=32,
     *     options={"comment"="Result of this run, NULL if not finished yet",
     *              "default"="NULL"},
     *     nullable=true)
     * @Serializer\Exclude()
     */
    private $runresult;

    /**
     * @var double
     * @ORM\Column(type="float", name="runtime",
     *     options={"comment"="Submission running time on this testcase",
     *              "default"="NULL"},
     *     nullable=true)
     * @Serializer\Exclude()
     */
    private $runtime;

    /**
     * @var double
     * @ORM\Column(type="decimal", precision=32, scale=9, name="endtime",
     *     options={"comment"="Time run judging ended", "unsigned"=true},
     *     nullable=false)
     * @Serializer\Exclude()
     */
    private $endtime;

    /**
     * @ORM\ManyToOne(targetEntity="Judging", inversedBy="runs")
     * @ORM\JoinColumn(name="judgingid", referencedColumnName="judgingid", onDelete="CASCADE")
     * @Serializer\Exclude()
     */
    private $judging;

    /**
     * @ORM\ManyToOne(targetEntity="Testcase", inversedBy="judging_runs")
     * @ORM\JoinColumn(name="testcaseid", referencedColumnName="testcaseid")
     * @Serializer\Exclude()
     */
    private $testcase;

    /**
     * We use a OneToMany instead of a OneToOne here, because otherwise this
     * relation will always be loaded. See the commit message of commit
     * 9e421f96691ec67ed62767fe465a6d8751edd884 for a more elaborate explanation
     *
     * @var JudgingRunOutput[]|ArrayCollection
     * @ORM\OneToMany(targetEntity="JudgingRunOutput", mappedBy="run", cascade={"persist"}, orphanRemoval=true)
     * @Serializer\Exclude()
     */
    private $output;

    public function __construct()
    {
        $this->output = new ArrayCollection();
    }

    /**
     * Get runid
     *
     * @return integer
     */
    public function getRunid()
    {
        return $this->runid;
    }

    /**
     * Set judgingid
     *
     * @param integer $judgingid
     *
     * @return JudgingRun
     */
    public function setJudgingid($judgingid)
    {
        $this->judgingid = $judgingid;

        return $this;
    }

    /**
     * Get judgingid
     *
     * @return integer
     */
    public function getJudgingid()
    {
        return $this->judgingid;
    }

    /**
     * Set testcaseid
     *
     * @param integer $testcaseid
     *
     * @return JudgingRun
     */
    public function setTestcaseid($testcaseid)
    {
        $this->testcaseid = $testcaseid;

        return $this;
    }

    /**
     * Get testcaseid
     *
     * @return integer
     */
    public function getTestcaseid()
    {
        return $this->testcaseid;
    }

    /**
     * Set runresult
     *
     * @param string $runresult
     *
     * @return JudgingRun
     */
    public function setRunresult($runresult)
    {
        $this->runresult = $runresult;

        return $this;
    }

    /**
     * Get runresult
     *
     * @return string
     */
    public function getRunresult()
    {
        return $this->runresult;
    }

    /**
     * Set runtime
     *
     * @param float $runtime
     *
     * @return JudgingRun
     */
    public function setRuntime($runtime)
    {
        $this->runtime = $runtime;

        return $this;
    }

    /**
     * Get runtime
     *
     * @return float
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("run_time")
     * @Serializer\Type("float")
     */
    public function getRuntime()
    {
        return Utils::roundedFloat($this->runtime);
    }

    /**
     * Set endtime
     *
     * @param float $endtime
     *
     * @return JudgingRun
     */
    public function setEndtime($endtime)
    {
        $this->endtime = $endtime;

        return $this;
    }

    /**
     * Get endtime
     *
     * @return float
     */
    public function getEndtime()
    {
        return $this->endtime;
    }

    /**
     * Get the absolute end time for this run
     *
     * @return string
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("time")
     * @Serializer\Type("string")
     */
    public function getAbsoluteEndTime()
    {
        return Utils::absTime($this->getEndtime());
    }

    /**
     * Get the relative end time for this run
     *
     * @return string
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("contest_time")
     * @Serializer\Type("string")
     */
    public function getRelativeEndTime()
    {
        return Utils::relTime($this->getEndtime() - $this->getJudging()->getContest()->getStarttime());
    }

    /**
     * Set judging
     *
     * @param \App\Entity\Judging $judging
     *
     * @return JudgingRun
     */
    public function setJudging(\App\Entity\Judging $judging = null)
    {
        $this->judging = $judging;

        return $this;
    }

    /**
     * Get judging
     *
     * @return \App\Entity\Judging
     */
    public function getJudging()
    {
        return $this->judging;
    }

    /**
     * Set testcase
     *
     * @param \App\Entity\Testcase $testcase
     *
     * @return JudgingRun
     */
    public function setTestcase(\App\Entity\Testcase $testcase = null)
    {
        $this->testcase = $testcase;

        return $this;
    }

    /**
     * Get testcase
     *
     * @return \App\Entity\Testcase
     */
    public function getTestcase()
    {
        return $this->testcase;
    }

    /**
     * Get testcase rank
     * @return int
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("ordinal")
     * @Serializer\Type("int")
     */
    public function getTestcaseRank()
    {
        return $this->getTestcase()->getRank();
    }

    /**
     * Set output
     *
     * @param JudgingRunOutput $output
     *
     * @return JudgingRun
     */
    public function setOutput(JudgingRunOutput $output)
    {
        $this->output->clear();
        $this->output->add($output);
        $output->setRun($this);

        return $this;
    }

    /**
     * Get output
     *
     * @return JudgingRunOutput
     */
    public function getOutput()
    {
        return $this->output->first() ?: null;
    }
}
