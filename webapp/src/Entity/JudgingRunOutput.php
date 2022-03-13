<?php declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Output of a judging run.
 *
 * @ORM\Entity
 * @ORM\Table(
 *     name="judging_run_output",
 *     options={"collation"="utf8mb4_unicode_ci", "charset"="utf8mb4", "comment"="Stores output of judging run"},
 *     indexes={@ORM\Index(name="runid", columns={"runid"})})
 */
class JudgingRunOutput
{
    /**
     * We use a ManyToOne instead of a OneToOne here, because otherwise the
     * reverse of this relation will always be loaded. See the commit message of commit
     * 9e421f96691ec67ed62767fe465a6d8751edd884 for a more elaborate explanation
     *
     * @ORM\Id
     * @ORM\ManyToOne(targetEntity="App\Entity\JudgingRun", inversedBy="output")
     * @ORM\JoinColumn(name="runid", referencedColumnName="runid", onDelete="CASCADE")
     */
    private JudgingRun $run;

    /**
     * @ORM\Column(type="blobtext", length=4294967295, name="output_run",
     *     options={"comment"="Output of running the program"},
     *     nullable=true)
     */
    private ?string $output_run;

    /**
     * @ORM\Column(type="blobtext", length=4294967295, name="output_diff",
     *     options={"comment"="Diffing the program output and testcase output"},
     *     nullable=true)
     */
    private ?string $output_diff;

    /**
     * @ORM\Column(type="blobtext", length=4294967295, name="output_error",
     *     options={"comment"="Standard error output of the program"},
     *     nullable=true)
     */
    private ?string $output_error;

    /**
     * @ORM\Column(type="blobtext", length=4294967295, name="output_system",
     *     options={"comment"="Judging system output"},
     *     nullable=true)
     */
    private ?string $output_system;

    /**
     * @ORM\Column(type="blobtext", length=4294967295, name="metadata",
     *     options={"comment"="Judging metadata"},
     *     nullable=true)
     */
    private ?string $metadata;

    public function setRun(JudgingRun $run): JudgingRunOutput
    {
        $this->run = $run;
        return $this;
    }

    public function getRun(): JudgingRun
    {
        return $this->run;
    }

    public function setOutputRun($outputRun): JudgingRunOutput
    {
        $this->output_run = $outputRun;
        return $this;
    }

    public function getOutputRun(): string
    {
        return $this->output_run;
    }

    public function setOutputDiff(string $outputDiff): JudgingRunOutput
    {
        $this->output_diff = $outputDiff;
        return $this;
    }

    public function getOutputDiff(): string
    {
        return $this->output_diff;
    }

    public function setOutputError(string $outputError): JudgingRunOutput
    {
        $this->output_error = $outputError;
        return $this;
    }

    public function getOutputError(): string
    {
        return $this->output_error;
    }

    public function setOutputSystem(string $outputSystem): JudgingRunOutput
    {
        $this->output_system = $outputSystem;
        return $this;
    }

    public function getOutputSystem(): string
    {
        return $this->output_system;
    }

    public function getMetadata(): string
    {
        return $this->metadata;
    }

    public function setMetadata($metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }
}
