<?php declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Run in external system.
 */
#[ORM\Entity]
#[ORM\Table(options: [
    'collation' => 'utf8mb4_unicode_ci',
    'charset' => 'utf8mb4',
    'comment' => 'Run in external system',
])]
#[ORM\Index(columns: ['extjudgementid'], name: 'extjudgementid')]
#[ORM\Index(columns: ['testcaseid'], name: 'testcaseid')]
#[ORM\UniqueConstraint(
    name: 'externalid',
    columns: ['cid', 'externalid'],
    options: ['lengths' => [null, 190]]
)]
class ExternalRun
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['comment' => 'External run ID', 'unsigned' => true])]
    private int $extrunid;

    #[ORM\Column(
        nullable: true,
        options: ['comment' => 'Run ID in external system, should be unique inside a single contest', 'collation' => 'utf8mb4_bin']
    )]
    protected ?string $externalid = null;

    #[ORM\Column(
        length: 32,
        options: ['comment' => 'Result string as obtained from external system']
    )]
    private string $result;

    #[ORM\Column(
        type: 'decimal',
        precision: 32,
        scale: 9,
        options: ['comment' => 'Time run ended', 'unsigned' => true]
    )]
    private string|float $endtime;

    #[ORM\Column(options: ['comment' => 'Running time on this testcase'])]
    private float $runtime;

    #[ORM\ManyToOne(inversedBy: 'external_runs')]
    #[ORM\JoinColumn(name: 'extjudgementid', referencedColumnName: 'extjudgementid', onDelete: 'CASCADE')]
    private ExternalJudgement $external_judgement;

    #[ORM\ManyToOne(inversedBy: 'external_runs')]
    #[ORM\JoinColumn(name: 'testcaseid', referencedColumnName: 'testcaseid', onDelete: 'CASCADE')]
    private Testcase $testcase;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'cid', referencedColumnName: 'cid', onDelete: 'CASCADE')]
    private Contest $contest;

    public function getExtrunid(): int
    {
        return $this->extrunid;
    }

    public function setExternalid(string $externalid): ExternalRun
    {
        $this->externalid = $externalid;
        return $this;
    }

    public function getExternalid(): string
    {
        return $this->externalid;
    }

    public function setResult(string $result): ExternalRun
    {
        $this->result = $result;
        return $this;
    }

    public function getResult(): string
    {
        return $this->result;
    }

    public function setEndtime(string|float $endtime): ExternalRun
    {
        $this->endtime = $endtime;
        return $this;
    }

    public function getEndtime(): string|float
    {
        return $this->endtime;
    }

    public function setRuntime(float $runtime): ExternalRun
    {
        $this->runtime = $runtime;
        return $this;
    }

    public function getRuntime(): float
    {
        return $this->runtime;
    }

    public function setExternalJudgement(ExternalJudgement $externalJudgement): ExternalRun
    {
        $this->external_judgement = $externalJudgement;
        return $this;
    }

    public function getExternalJudgement(): ExternalJudgement
    {
        return $this->external_judgement;
    }

    public function setTestcase(Testcase $testcase): ExternalRun
    {
        $this->testcase = $testcase;
        return $this;
    }

    public function getTestcase(): Testcase
    {
        return $this->testcase;
    }

    public function setContest(?Contest $contest = null): ExternalRun
    {
        $this->contest = $contest;
        return $this;
    }

    public function getContest(): Contest
    {
        return $this->contest;
    }
}
