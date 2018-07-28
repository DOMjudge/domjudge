<?php
namespace DOMJudgeBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Result of a testcase run.
 * @ORM\Entity()
 * @ORM\Table(name="judging_run", options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4"})
 */
class JudgingRun
{

    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="runid", options={"comment"="Unique ID"}, nullable=false)
     */
    private $runid;

    /**
     * @var int
     * @ORM\Column(type="integer", name="judgingid", options={"comment"="Judging ID"}, nullable=false)
     */
    private $judgingid;

    /**
     * @var int
     * @ORM\Column(type="integer", name="testcaseid", options={"comment"="Testcase ID"}, nullable=false)
     */
    private $testcaseid;

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
     * @ORM\Column(type="blob", name="output_run", options={"comment"="Output of running the program"}, nullable=true)
     */
    private $output_run;

    /**
     * @var string
     * @ORM\Column(type="blob", name="output_diff", options={"comment"="Diffing the program output and testcase output"}, nullable=true)
     */
    private $output_diff;

    /**
     * @var string
     * @ORM\Column(type="blob", name="output_error", options={"comment"="Standard error output of the program"}, nullable=true)
     */
    private $output_error;

    /**
     * @var string
     * @ORM\Column(type="blob", name="output_system", options={"comment"="Judging system output"}, nullable=true)
     */
    private $output_system;

    /**
     * @ORM\ManyToOne(targetEntity="Judging", inversedBy="runs")
     * @ORM\JoinColumn(name="judgingid", referencedColumnName="judgingid")
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
     */
    public function getRuntime()
    {
        return $this->runtime;
    }

    /**
     * Set outputRun
     *
     * @param string $outputRun
     *
     * @return JudgingRun
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
     * @return JudgingRun
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
     * @return JudgingRun
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
     * @return JudgingRun
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
     * @param \DOMJudgeBundle\Entity\Judging $judging
     *
     * @return JudgingRun
     */
    public function setJudging(\DOMJudgeBundle\Entity\Judging $judging = null)
    {
        $this->judging = $judging;

        return $this;
    }

    /**
     * Get judging
     *
     * @return \DOMJudgeBundle\Entity\Judging
     */
    public function getJudging()
    {
        return $this->judging;
    }

    /**
     * Set testcase
     *
     * @param \DOMJudgeBundle\Entity\Testcase $testcase
     *
     * @return JudgingRun
     */
    public function setTestcase(\DOMJudgeBundle\Entity\Testcase $testcase = null)
    {
        $this->testcase = $testcase;

        return $this;
    }

    /**
     * Get testcase
     *
     * @return \DOMJudgeBundle\Entity\Testcase
     */
    public function getTestcase()
    {
        return $this->testcase;
    }
}
