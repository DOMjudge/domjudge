<?php declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Output of a judging run.
 */
#[ORM\Entity]
#[ORM\Table(options: [
    'collation' => 'utf8mb4_unicode_ci',
    'charset' => 'utf8mb4',
    'comment' => 'Stores output of judging run',
])]
#[ORM\Index(name: 'runid', columns: ['runid'])]
class JudgingRunOutput
{
    /**
     * We use a ManyToOne instead of a OneToOne here, because otherwise the
     * reverse of this relation will always be loaded. See the commit message of commit
     * 9e421f96691ec67ed62767fe465a6d8751edd884 for a more elaborate explanation
     */
    #[ORM\Id]
    #[ORM\ManyToOne(inversedBy: 'output')]
    #[ORM\JoinColumn(name: 'runid', referencedColumnName: 'runid', onDelete: 'CASCADE')]
    private JudgingRun $run;

    #[ORM\Column(
        type: 'blobtext',
        nullable: true,
        options: ['comment' => 'Output of running the program']
    )]
    private ?string $output_run = null;

    #[ORM\Column(
        type: 'blobtext',
        nullable: true,
        options: ['comment' => 'Diffing the program output and testcase output']
    )]
    private ?string $output_diff = null;

    #[ORM\Column(
        type: 'blobtext',
        nullable: true,
        options: ['comment' => 'Standard error output of the program']
    )]
    private ?string $output_error = null;

    #[ORM\Column(
        type: 'blobtext',
        nullable: true,
        options: ['comment' => 'Judging system output']
    )]
    private ?string $output_system = null;

    #[ORM\Column(
        type: 'blobtext',
        nullable: true,
        options: ['comment' => 'Judge message for the team']
    )]
    private ?string $team_message = null;

    #[ORM\Column(
        type: 'blobtext',
        nullable: true,
        options: ['comment' => 'Judging metadata of the run']
    )]
    private ?string $metadata = null;

    #[ORM\Column(
        type: 'blobtext',
        nullable: true,
        options: ['comment' => 'Judging metadata of the validator']
    )]
    private ?string $validatorMetadata = null;

    public function setRun(JudgingRun $run): JudgingRunOutput
    {
        $this->run = $run;
        return $this;
    }

    public function getRun(): JudgingRun
    {
        return $this->run;
    }

    public function setOutputRun(?string $outputRun): JudgingRunOutput
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

    public function setTeamMessage(string $teamMessage) : JudgingRunOutput
    {
        $this->team_message = $teamMessage;
        return $this;
    }

    public function getTeamMessage(): ?string
    {
        return $this->team_message;
    }

    public function getMetadata(): string
    {
        return $this->metadata;
    }

    public function setMetadata(?string $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function getValidatorMetadata(): string
    {
        return $this->validatorMetadata;
    }

    public function setValidatorMetadata(?string $validatorMetadata): self
    {
        $this->validatorMetadata = $validatorMetadata;
        return $this;
    }
}
