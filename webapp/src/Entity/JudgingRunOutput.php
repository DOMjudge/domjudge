<?php declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Output of a judging run
 *
 * @ORM\Entity
 * @ORM\Table(
 *     name="judging_run_output",
 *     options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4", "comment"="Stores output of judging run"},
 *     indexes={@ORM\Index(name="runid", columns={"runid"})})
 */
class JudgingRunOutput
{
    /**
     * We use a ManyToOne instead of a OneToOne here, because otherwise the
     * reverse of this relation will always be loaded. See the commit message of commit
     * 9e421f96691ec67ed62767fe465a6d8751edd884 for a more elaborate explanation
     *
     * @var JudgingRun
     * @ORM\Id
     * @ORM\ManyToOne(targetEntity="App\Entity\JudgingRun", inversedBy="output")
     * @ORM\JoinColumn(name="runid", referencedColumnName="runid", onDelete="CASCADE")
     */
    private $run;

    /**
     * @var string
     * @ORM\Column(type="blobtext", length=4294967295, name="output_run",
     *     options={"comment"="Output of running the program", "default"="NULL"},
     *     nullable=true)
     */
    private $output_run;

    /**
     * @var string
     * @ORM\Column(type="blobtext", length=4294967295, name="output_diff",
     *     options={"comment"="Diffing the program output and testcase output",
     *              "default"="NULL"},
     *     nullable=true)
     */
    private $output_diff;

    /**
     * @var string
     * @ORM\Column(type="blobtext", length=4294967295, name="output_error",
     *     options={"comment"="Standard error output of the program", "default"="NULL"},
     *     nullable=true)
     */
    private $output_error;

    /**
     * @var string
     * @ORM\Column(type="blobtext", length=4294967295, name="output_system",
     *     options={"comment"="Judging system output", "default"="NULL"},
     *     nullable=true)
     */
    private $output_system;

    /**
     * @var string
     * @ORM\Column(type="blobtext", length=4294967295, name="metadata",
     *     options={"comment"="Judging metadata", "default"="NULL"},
     *     nullable=true)
     */
    private $metadata;

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

    public function getMetadata()
    {
        return $this->metadata;
    }

    public function setMetadata($metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }
}
