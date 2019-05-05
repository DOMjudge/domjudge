<?php declare(strict_types=1);

namespace DOMJudgeBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Judgement in external system
 * @ORM\Table(name="external_judgement",
 *             indexes={@ORM\Index(name="submitid", columns={"submitid"})},
 *             options={"comment":"Judgement in external system"})
 * @ORM\Entity
 */
class ExternalJudgement
{
    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="extjudgementid", options={"comment"="Unique ID"}, nullable=false)
     */
    private $extjudgementid;

    /**
     * @var string
     * @ORM\Column(type="string", name="externalid", length=255, options={"comment"="Judgement ID in external system, should be unique inside a single contest", "collation"="utf8mb4_bin"}, nullable=true)
     */
    protected $externalid;

    /**
     * @var string|null
     *
     * @ORM\Column(name="result", type="string", length=32, nullable=true)
     */
    private $result = null;

    /**
     * @var double
     *
     * @ORM\Column(type="decimal", precision=32, scale=9, name="starttime",
     *              options={"comment"="Time judging started", "unsigned"=true},
     *              nullable=false)
     */
    private $starttime;

    /**
     * @var double
     *
     * @ORM\Column(type="decimal", precision=32, scale=9, name="endtime",
     *              options={"comment"="Time judging ended, null = stil busy", "unsigned"=true},
     *              nullable=true)
     */
    private $endtime = null;

    /**
     * @var boolean
     * @ORM\Column(type="boolean", name="valid", options={"comment"="Old external judgement is marked as invalid when receiving a new one"}, nullable=false)
     */
    private $valid = true;

    /**
     * @var Contest
     *
     * @ORM\ManyToOne(targetEntity="DOMJudgeBundle\Entity\Contest")
     * @ORM\JoinColumn(name="cid", referencedColumnName="cid", onDelete="CASCADE")
     */
    private $contest;

    /**
     * @var Submission
     *
     * @ORM\ManyToOne(targetEntity="Submission", inversedBy="external_judgements")
     * @ORM\JoinColumn(name="submitid", referencedColumnName="submitid", onDelete="CASCADE")
     */
    private $submission;

    /**
     * @ORM\OneToMany(targetEntity="ExternalRun", mappedBy="external_judgement")
     */
    private $external_runs;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->external_runs = new ArrayCollection();
    }

    /**
     * Get extjudgementid
     *
     * @return int
     */
    public function getExtjudgementid()
    {
        return $this->extjudgementid;
    }

    /**
     * Set externalid
     *
     * @param string $externalid
     *
     * @return ExternalJudgement
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
     * @param string|null $result
     *
     * @return ExternalJudgement
     */
    public function setResult($result)
    {
        $this->result = $result;

        return $this;
    }

    /**
     * Get result
     *
     * @return string|null
     */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * Set starttime
     *
     * @param double $starttime
     *
     * @return ExternalJudgement
     */
    public function setStarttime($starttime)
    {
        $this->starttime = $starttime;

        return $this;
    }

    /**
     * Get starttime
     *
     * @return double
     */
    public function getStarttime()
    {
        return $this->starttime;
    }

    /**
     * Set endtime
     *
     * @param double $endtime
     *
     * @return ExternalJudgement
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
     * Set valid
     *
     * @param boolean $valid
     *
     * @return ExternalJudgement
     */
    public function setValid($valid)
    {
        $this->valid = $valid;

        return $this;
    }

    /**
     * Get valid
     *
     * @return boolean
     */
    public function getValid()
    {
        return $this->valid;
    }

    /**
     * Set contest
     *
     * @param Contest $contest
     *
     * @return ExternalJudgement
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

    /**
     * Set submission
     *
     * @param Submission $submission
     *
     * @return ExternalJudgement
     */
    public function setSubmission(Submission $submission)
    {
        $this->submission = $submission;

        return $this;
    }

    /**
     * Get submission
     *
     * @return Submission
     */
    public function getSubmission()
    {
        return $this->submission;
    }

    /**
     * Add externalRun
     *
     * @param ExternalRun $externalRun
     *
     * @return ExternalJudgement
     */
    public function addExternalRun(ExternalRun $externalRun)
    {
        $this->external_runs[] = $externalRun;

        return $this;
    }

    /**
     * Remove externalRun
     *
     * @param ExternalRun $externalRun
     */
    public function removeExternalRun(ExternalRun $externalRun)
    {
        $this->external_runs->removeElement($externalRun);
    }

    /**
     * Get externalRuns
     *
     * @return Collection
     */
    public function getExternalRuns()
    {
        return $this->external_runs;
    }
}
