<?php declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Run in external system.
 *
 * @ORM\Table(
 *     name="external_run",
 *     options={"collation"="utf8mb4_unicode_ci", "charset"="utf8mb4", "comment":"Run in external system"},
 *     indexes={
 *         @ORM\Index(name="extjudgementid", columns={"extjudgementid"}),
 *         @ORM\Index(name="testcaseid", columns={"testcaseid"})
 *     },
 *     uniqueConstraints={
 *         @ORM\UniqueConstraint(name="externalid", columns={"cid", "externalid"}, options={"lengths": {null, 190}}),
 *     })
 * @ORM\Entity
 */
class ExternalRun
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="extrunid", length=4,
     *     options={"comment"="External run ID","unsigned"=true}, nullable=false)
     */
    private int $extrunid;

    /**
     * @ORM\Column(type="string", name="externalid", length=255,
     *     options={"comment"="Run ID in external system, should be unique inside a single contest",
     *              "collation"="utf8mb4_bin"},
     *     nullable=true)
     */
    protected ?string $externalid;

    /**
     * @ORM\Column(name="result", type="string", length=32,
     *              options={"comment"="Result string as obtained from external system"},
     *              nullable=false)
     */
    private string $result;

    /**
     * @var double|string
     *
     * @ORM\Column(type="decimal", precision=32, scale=9, name="endtime",
     *              options={"comment"="Time run ended", "unsigned"=true},
     *              nullable=false)
     */
    private $endtime;

    /**
     * @var double|string
     *
     * @ORM\Column(type="float", name="runtime",
     *              options={"comment"="Running time on this testcase"}, nullable=false)
     */
    private $runtime;

    /**
     * @ORM\ManyToOne(targetEntity="ExternalJudgement", inversedBy="external_runs")
     * @ORM\JoinColumn(name="extjudgementid", referencedColumnName="extjudgementid", onDelete="CASCADE")
     */
    private ExternalJudgement $external_judgement;

    /**
     * @ORM\ManyToOne(targetEntity="Testcase", inversedBy="external_runs")
     * @ORM\JoinColumn(name="testcaseid", referencedColumnName="testcaseid", onDelete="CASCADE")
     */
    private Testcase $testcase;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Contest")
     * @ORM\JoinColumn(name="cid", referencedColumnName="cid", onDelete="CASCADE")
     */
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

    /** @param string|float $endtime */
    public function setEndtime($endtime): ExternalRun
    {
        $this->endtime = $endtime;
        return $this;
    }

    /** @return string|float */
    public function getEndtime()
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
