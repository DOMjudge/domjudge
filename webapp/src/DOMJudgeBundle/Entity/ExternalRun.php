<?php declare(strict_types=1);

namespace DOMJudgeBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Run in external system
 * @ORM\Table(name="external_run",
 *             indexes={@ORM\Index(name="extjudgementid", columns={"extjudgementid"}),
 *                      @ORM\Index(name="testcaseid", columns={"testcaseid"})},
 *             options={"comment":"Run in external system"})
 * @ORM\Entity
 */
class ExternalRun
{
    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="extrunid", options={"comment"="Unique ID"}, nullable=false)
     */
    private $extrunid;

    /**
     * @var string
     * @ORM\Column(type="string", name="externalid", length=255, options={"comment"="Run ID in external system, should be unique inside a single contest", "collation"="utf8mb4_bin"}, nullable=true)
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
     *              options={"comment"="Time run ende", "unsigned"=true},
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
     * @var ExternalJudgement
     *
     * @ORM\ManyToOne(targetEntity="ExternalJudgement", inversedBy="external_runs")
     * @ORM\JoinColumn(name="extjudgementid", referencedColumnName="extjudgementid")
     */
    private $external_judgement;

    /**
     * @var Testcase
     *
     * @ORM\ManyToOne(targetEntity="Testcase", inversedBy="external_runs")
     * @ORM\JoinColumn(name="testcaseid", referencedColumnName="testcaseid")
     */
    private $testcase;

    /**
     * @var Contest
     *
     * @ORM\ManyToOne(targetEntity="DOMJudgeBundle\Entity\Contest")
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
