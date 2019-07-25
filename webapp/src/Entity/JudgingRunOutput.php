<?php declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Output of a judging run
 *
 * @ORM\Entity
 * @ORM\Table(name="judging_run_output", options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4"})
 */
class JudgingRunOutput
{
    /**
     * @var JudgingRun
     * @ORM\Id
     * @ORM\OneToOne(targetEntity="App\Entity\JudgingRun", inversedBy="output")
     * @ORM\JoinColumn(name="runid", referencedColumnName="runid", onDelete="CASCADE")
     */
    private $run;

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
     * @param JudgingRun $run
     *
     * @return JudgingRunOutput
     */
    public function setRun(JudgingRun $run)
    {
        $this->run = $run;

        return $this;
    }

    /**
     * Get run
     *
     * @return JudgingRun
     */
    public function getRun(): JudgingRun
    {
        return $this->run;
    }

    /**
     * Set outputRun
     *
     * @param string $outputRun
     *
     * @return JudgingRunOutput
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
     * @return JudgingRunOutput
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
     * @return JudgingRunOutput
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
     * @return JudgingRunOutput
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
}
