<?php declare(strict_types=1);
namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

/**
 * A single pass of a testcase run for a multi-pass problem.
 *
 * The submission of a multi-pass problem is run repeatedly on the same
 * testcase, where the output validator provides the input for the next pass.
 * The verdict of the testcase is the verdict of the last pass that ran, which
 * is what the parent JudgingRun stores; this entity keeps the details of every
 * individual pass so they can be inspected afterwards.
 *
 * Single-pass problems do not get any rows here: their JudgingRun already holds
 * everything there is to know about the one and only pass.
 */
#[ORM\Entity]
#[ORM\Table(options: [
    'collation' => 'utf8mb4_unicode_ci',
    'charset' => 'utf8mb4',
    'comment' => 'Result of a single pass of a testcase run',
])]
#[ORM\Index(name: 'runid', columns: ['runid'])]
#[ORM\UniqueConstraint(name: 'runid_pass', columns: ['runid', 'pass'])]
class JudgingRunPass
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['comment' => 'Judging run pass ID', 'unsigned' => true])]
    #[Serializer\SerializedName('id')]
    #[Serializer\Type('string')]
    private int $passid;

    #[ORM\ManyToOne(inversedBy: 'passes')]
    #[ORM\JoinColumn(name: 'runid', referencedColumnName: 'runid', onDelete: 'CASCADE')]
    #[Serializer\Exclude]
    private JudgingRun $run;

    #[ORM\Column(options: ['comment' => 'Pass number, starting at 1', 'unsigned' => true])]
    private int $pass;

    #[ORM\Column(
        length: 32,
        nullable: true,
        options: ['comment' => 'Result of this pass, NULL if not finished yet']
    )]
    private ?string $runresult = null;

    #[ORM\Column(
        type: 'float',
        nullable: true,
        options: ['comment' => 'Submission running time on this pass']
    )]
    private string|float|null $runtime = null;

    #[ORM\Column(
        type: 'decimal',
        precision: 32,
        scale: 9,
        nullable: true,
        options: ['comment' => 'Time this pass started', 'unsigned' => true]
    )]
    private string|float|null $startTime = null;

    #[ORM\Column(
        type: 'decimal',
        precision: 32,
        scale: 9,
        nullable: true,
        options: ['comment' => 'Time this pass ended', 'unsigned' => true]
    )]
    private string|float|null $endtime = null;

    /**
     * @var Collection<int, JudgingRunPassOutput>
     *
     * We use a OneToMany instead of a OneToOne here, because otherwise this
     * relation will always be loaded. See the commit message of commit
     * 9e421f96691ec67ed62767fe465a6d8751edd884 for a more elaborate explanation.
     */
    #[ORM\OneToMany(targetEntity: JudgingRunPassOutput::class, mappedBy: 'pass', cascade: ['persist'], orphanRemoval: true)]
    #[Serializer\Exclude]
    private Collection $output;

    public function __construct()
    {
        $this->output = new ArrayCollection();
    }

    public function getPassid(): int
    {
        return $this->passid;
    }

    public function setRun(JudgingRun $run): JudgingRunPass
    {
        $this->run = $run;
        return $this;
    }

    public function getRun(): JudgingRun
    {
        return $this->run;
    }

    public function setPass(int $pass): JudgingRunPass
    {
        $this->pass = $pass;
        return $this;
    }

    public function getPass(): int
    {
        return $this->pass;
    }

    public function setRunresult(?string $runresult): JudgingRunPass
    {
        $this->runresult = $runresult;
        return $this;
    }

    public function getRunresult(): ?string
    {
        return $this->runresult;
    }

    public function setRuntime(string|float|null $runtime): JudgingRunPass
    {
        $this->runtime = $runtime;
        return $this;
    }

    public function getRuntime(): string|float|null
    {
        return $this->runtime;
    }

    public function setStartTime(string|float|null $startTime): JudgingRunPass
    {
        $this->startTime = $startTime;
        return $this;
    }

    public function getStartTime(): string|float|null
    {
        return $this->startTime;
    }

    public function setEndtime(string|float|null $endtime): JudgingRunPass
    {
        $this->endtime = $endtime;
        return $this;
    }

    public function getEndtime(): string|float|null
    {
        return $this->endtime;
    }

    public function setOutput(JudgingRunPassOutput $output): JudgingRunPass
    {
        $this->output->clear();
        $this->output->add($output);
        $output->setPass($this);
        return $this;
    }

    public function getOutput(): ?JudgingRunPassOutput
    {
        return $this->output->first() ?: null;
    }
}
