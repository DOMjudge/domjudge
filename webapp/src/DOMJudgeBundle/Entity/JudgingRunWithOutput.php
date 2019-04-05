<?php declare(strict_types=1);

namespace DOMJudgeBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use DOMJudgeBundle\Utils\Utils;

/**
 * Output of a judging run
 *
 * This is a seperate class with a OneToOne relationship with JudgingRunWithOutput so we can load it separately
 * @ORM\Entity()
 * @ORM\Table(name="judging_run", options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4"})
 */
class JudgingRunWithOutput extends BaseApiEntity
{
    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="runid", options={"comment"="Unique ID"}, nullable=false)
     */
    protected $runid;

    /**
     * @var string
     * @ORM\Column(type="string", name="runresult", length=32, options={"comment"="Result of this run, NULL if not finished yet"}, nullable=true)
     */
    private $runresult;

    /**
     * @var double
     * @ORM\Column(type="float", name="runtime", options={"comment"="Submission running time on this testcase"}, nullable=true)
     */
    private $runtime = 1;

    /**
     * @var double
     * @ORM\Column(type="decimal", precision=32, scale=9, name="endtime", options={"comment"="Time run judging finished", "unsigned"=true}, nullable=false)
     */
    private $endtime;

    /**
     * @var string
     * @ORM\Column(type="string", name="output_run", options={"comment"="Output of running the program"}, nullable=true)
     */
    private $output_run;

    /**
     * @var string
     * @ORM\Column(type="string", name="output_diff", options={"comment"="Diffing the program output and testcase output"}, nullable=true)
     */
    private $output_diff;

    /**
     * @var string
     * @ORM\Column(type="string", name="output_error", options={"comment"="Standard error output of the program"}, nullable=true)
     */
    private $output_error;

    /**
     * @var string
     * @ORM\Column(type="string", name="output_system", options={"comment"="Judging system output"}, nullable=true)
     */
    private $output_system;

    /**
     * @ORM\ManyToOne(targetEntity="Judging", inversedBy="runs")
     * @ORM\JoinColumn(name="judgingid", referencedColumnName="judgingid", onDelete="CASCADE")
     */
    private $judging;

    /**
     * @ORM\ManyToOne(targetEntity="Testcase", inversedBy="judging_runs")
     * @ORM\JoinColumn(name="testcaseid", referencedColumnName="testcaseid")
     */
    private $testcase;

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
     * Set runresult
     *
     * @param string $runresult
     *
     * @return JudgingRunWithOutput
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
     * @return JudgingRunWithOutput
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
     * @return JudgingRunWithOutput
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
     * Set outputRun
     *
     * @param string $outputRun
     *
     * @return JudgingRunWithOutput
     */
    public function setOutputRun($outputRun)
    {
        $this->output_run = $outputRun;

        return $this;
    }

    /**
     * Get outputRun
     *
     * @return string
     */
    public function getOutputRun()
    {
        return $this->output_run;
    }

    /**
     * Set outputDiff
     *
     * @param string $outputDiff
     *
     * @return JudgingRunWithOutput
     */
    public function setOutputDiff($outputDiff)
    {
        $this->output_diff = $outputDiff;

        return $this;
    }

    /**
     * Get outputDiff
     *
     * @return string
     */
    public function getOutputDiff()
    {
        return $this->output_diff;
    }

    /**
     * Set outputError
     *
     * @param string $outputError
     *
     * @return JudgingRunWithOutput
     */
    public function setOutputError($outputError)
    {
        $this->output_error = $outputError;

        return $this;
    }

    /**
     * Get outputError
     *
     * @return string
     */
    public function getOutputError()
    {
        return $this->output_error;
    }

    /**
     * Set outputSystem
     *
     * @param string $outputSystem
     *
     * @return JudgingRunWithOutput
     */
    public function setOutputSystem($outputSystem)
    {
        $this->output_system = $outputSystem;

        return $this;
    }

    /**
     * Get outputSystem
     *
     * @return string
     */
    public function getOutputSystem()
    {
        return $this->output_system;
    }

    /**
     * Set judging
     *
     * @param Judging $judging
     *
     * @return JudgingRunWithOutput
     */
    public function setJudging(Judging $judging = null)
    {
        $this->judging = $judging;

        return $this;
    }

    /**
     * Get judging
     *
     * @return Judging
     */
    public function getJudging()
    {
        return $this->judging;
    }

    /**
     * Set testcase
     *
     * @param Testcase $testcase
     *
     * @return JudgingRunWithOutput
     */
    public function setTestcase(Testcase $testcase = null)
    {
        $this->testcase = $testcase;

        return $this;
    }

    /**
     * Get testcase
     *
     * @return Testcase
     */
    public function getTestcase()
    {
        return $this->testcase;
    }
}
