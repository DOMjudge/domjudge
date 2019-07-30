<?php declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Run in external system
 * @ORM\Table(
 *     name="external_run",
 *     options={"comment":"Run in external system"},
 *     indexes={
 *         @ORM\Index(name="extjudgementid", columns={"extjudgementid"}),
 *         @ORM\Index(name="testcaseid", columns={"testcaseid"})
 *     },
 *     uniqueConstraints={
 *         @ORM\UniqueConstraint(name="externalid", columns={"cid", "externalid"}, options={"lengths": {null, "190"}}),
 *     })
 * @ORM\Entity
 */
class ExternalRun
{
    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="extrunid", length=4,
     *     options={"comment"="External run ID","unsigned"=true}, nullable=false)
     */
    private $extrunid;

    /**
     * @var string
     * @ORM\Column(type="string", name="externalid", length=255,
     *     options={"comment"="Run ID in external system, should be unique inside a single contest",
     *              "collation"="utf8mb4_bin","default"="NULL"},
     *     nullable=true)
     */
    protected $externalid;

    /**
     * @var string
     *
     * @ORM\Column(name="result", type="string", length=32,
     *              options={"comment"="Result string as obtained from external system"},
     *              nullable=false)
     */
    private $result;

    /**
     * @var double
     *
     * @ORM\Column(type="decimal", precision=32, scale=9, name="endtime",
     *              options={"comment"="Time run ended", "unsigned"=true},
     *              nullable=false)
     */
    private $endtime;

    /**
     * @var double
     *
     * @ORM\Column(type="float", name="runtime",
     *              options={"comment"="Running time on this testcase"}, nullable=false)
     */
    private $runtime;

    /**
     * @var int
     * @ORM\Column(type="integer", name="extjudgementid", length=4,
     *     options={"comment"="Judging ID this run belongs to","unsigned"=true},
     *     nullable=false)
     */
    private $extjudgementid;

    /**
     * @var ExternalJudgement
     *
     * @ORM\ManyToOne(targetEntity="ExternalJudgement", inversedBy="external_runs")
     * @ORM\JoinColumn(name="extjudgementid", referencedColumnName="extjudgementid", onDelete="CASCADE")
     */
    private $external_judgement;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", name="testcaseid", length=4,
     *     options={"comment"="Testcase ID","unsigned"=true},
     *     nullable=false)
     */
    private $testcaseid;

    /**
     * @var Testcase
     *
     * @ORM\ManyToOne(targetEntity="Testcase", inversedBy="external_runs")
     * @ORM\JoinColumn(name="testcaseid", referencedColumnName="testcaseid", onDelete="CASCADE")
     */
    private $testcase;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", name="cid",
     *     options={"comment"="Contest ID", "unsigned"=true},
     *     nullable=false, length=4)
     */
    protected $cid;

    /**
     * @var Contest
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Contest")
     * @ORM\JoinColumn(name="cid", referencedColumnName="cid", onDelete="CASCADE")
     */
    private $contest;

    /**
     * Get extrunid
     *
     * @return int
     */
    public function getExtrunid()
    {
        return $this->extrunid;
    }

    /**
     * Set externalid
     *
     * @param string $externalid
     *
     * @return ExternalRun
     */
    public function setExternalid($externalid)
    {
        $this->externalid = $externalid;

        return $this;
    }

    /**
     * Get externalid
     *
     * @return string
     */
    public function getExternalid()
    {
        return $this->externalid;
    }

    /**
     * Set result
     *
     * @param string $result
     *
     * @return ExternalRun
     */
    public function setResult($result)
    {
        $this->result = $result;

        return $this;
    }

    /**
     * Get result
     *
     * @return string
     */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * Set endtime
     *
     * @param double $endtime
     *
     * @return ExternalRun
     */
    public function setEndtime($endtime)
    {
        $this->endtime = $endtime;

        return $this;
    }

    /**
     * Get endtime
     *
     * @return double
     */
    public function getEndtime()
    {
        return $this->endtime;
    }

    /**
     * Set runtime
     *
     * @param double $runtime
     *
     * @return ExternalRun
     */
    public function setRuntime($runtime)
    {
        $this->runtime = $runtime;

        return $this;
    }

    /**
     * Get runtime
     *
     * @return double
     */
    public function getRuntime()
    {
        return $this->runtime;
    }

    /**
     * Set externalJudgementId
     *
     * @param int $extjudgementid
     *
     * @return ExternalRun
     */
    public function setExtjudgementid(int $extjudgementid)
    {
        $this->extjudgementid = $extjudgementid;

        return $this;
    }

    /**
     * Get externalJudgementId
     *
     * @return int
     */
    public function getExtjudgementid()
    {
        return $this->extjudgementid;
    }

    /**
     * Set externalJudgement
     *
     * @param ExternalJudgement $externalJudgement
     *
     * @return ExternalRun
     */
    public function setExternalJudgement(ExternalJudgement $externalJudgement)
    {
        $this->external_judgement = $externalJudgement;

        return $this;
    }

    /**
     * Get externalJudgement
     *
     * @return ExternalJudgement
     */
    public function getExternalJudgement()
    {
        return $this->external_judgement;
    }

    /**
     * Set testcase ID
     *
     * @param int $testcaseid
     *
     * @return ExternalRun
     */
    public function setTestcaseid(int $testcaseid)
    {
        $this->testcaseid = $testcaseid;

        return $this;
    }

    /**
     * Get testcase ID
     *
     * @return int
     */
    public function getTestcaseid()
    {
        return $this->testcaseid;
    }

    /**
     * Set testcase
     *
     * @param Testcase $testcase
     *
     * @return ExternalRun
     */
    public function setTestcase(Testcase $testcase)
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

    /**
     * Set cid
     *
     * @param int $cid
     *
     * @return ExternalRun
     */
    public function setCid(int $cid)
    {
        $this->cid = $cid;

        return $this;
    }

    /**
     * Get cid
     *
     * @return int
     */
    public function getCid()
    {
        return $this->cid;
    }

    /**
     * Set contest
     *
     * @param Contest $contest
     *
     * @return ExternalRun
     */
    public function setContest(Contest $contest = null)
    {
        $this->contest = $contest;

        return $this;
    }

    /**
     * Get contest
     *
     * @return Contest
     */
    public function getContest()
    {
        return $this->contest;
    }
}
