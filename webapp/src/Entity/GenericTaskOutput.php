<?php declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Output of a generic task.
 */
#[ORM\Entity]
#[ORM\Table(options: [
    'collation' => 'utf8mb4_unicode_ci',
    'charset' => 'utf8mb4',
    'comment' => 'Stores output of generic task',
])]
#[ORM\Index(columns: ['taskid'], name: 'taskid')]
class GenericTaskOutput
{
    /**
     * We use a ManyToOne instead of a OneToOne here, because otherwise the
     * reverse of this relation will always be loaded. See the commit message of commit
     * 9e421f96691ec67ed62767fe465a6d8751edd884 for a more elaborate explanation
     */
    #[ORM\Id]
    #[ORM\ManyToOne(inversedBy: 'output')]
    #[ORM\JoinColumn(name: 'taskid', referencedColumnName: 'taskid', onDelete: 'CASCADE')]
    private GenericTask $task;

    #[ORM\Column(
        type: 'blobtext',
        nullable: true,
        options: ['comment' => 'Output of running the program']
    )]
    private ?string $output_task = null;

    #[ORM\Column(
        type: 'blobtext',
        nullable: true,
        options: ['comment' => 'Standard error output of the program']
    )]
    private ?string $output_error = null;

    public function setGenericTask(GenericTask $task): GenericTaskOutput
    {
        $this->task = $task;
        return $this;
    }

    public function getGenericTask(): GenericTask
    {
        return $this->task;
    }

    public function setOutputTask(?string $outputTask): GenericTaskOutput
    {
        $this->output_task = $outputTask;
        return $this;
    }

    public function getOutputTask(): string
    {
        return $this->output_task;
    }

    public function setOutputError(string $outputError): GenericTaskOutput
    {
        $this->output_error = $outputError;
        return $this;
    }

    public function getOutputError(): string
    {
        return $this->output_error;
    }
}
