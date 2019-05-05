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
     * @var string
     *
     * @ORM\Column(name="extjudgementid", type="string", length=255, nullable=false,
     *              options={"comment": "Unique external judgement ID"})
     * @ORM\Id
     */
    private $extjudgementid;

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
     * Set extjudgementid
     *
     * @param string $extjudgementid
     *
     * @return ExternalJudgement
     */
    public function setExtjudgementid($extjudgementid): ExternalJudgement
    {
        $this->extjudgementid = $extjudgementid;

        return $this;
    }

    /**
     * Get extjudgementid
     *
     * @return string
     */
    public function getExtjudgementid()
    {
        return $this->extjudgementid;
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
