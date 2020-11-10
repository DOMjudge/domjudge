<?php declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

/**
 * Judgement in external system
 * @ORM\Table(
 *     name="external_judgement",
 *     options={"comment":"Judgement in external system"},
 *     indexes={
 *         @ORM\Index(name="submitid", columns={"submitid"}),
 *         @ORM\Index(name="cid", columns={"cid"}),
 *         @ORM\Index(name="verified", columns={"verified"}),
 *     },
 *     uniqueConstraints={
 *         @ORM\UniqueConstraint(name="externalid", columns={"cid", "externalid"}, options={"lengths": {null, 190}}),
 *     })
 * @ORM\Entity
 */
class ExternalJudgement
{
    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="extjudgementid",
     *     options={"comment"="External judgement ID","unsigned"=true},
     *     nullable=false)
     */
    private $extjudgementid;

    /**
     * @var string
     * @ORM\Column(type="string", name="externalid", length=255,
     *     options={"comment"="Judgement ID in external system, should be unique inside a single contest",
     *              "collation"="utf8mb4_bin"},
     *     nullable=true)
     */
    protected $externalid;

    /**
     * @var string|null
     *
     * @ORM\Column(name="result", type="string", length=32,
     *     options={"comment"="Result string as obtained from external system. null if not finished yet"},
     *     nullable=true)
     */
    private $result = null;

    /**
     * @var boolean
     * @ORM\Column(type="boolean", name="verified",
     *     options={"comment"="Result / difference verified?",
     *              "default"=0},
     *     nullable=false)
     * @Serializer\Exclude()
     */
    private $verified = false;

    /**
     * @var string
     * @ORM\Column(type="string", name="jury_member", length=255,
     *     options={"comment"="Name of user who verified the result / diference",
     *              "default"=NULL},
     *     nullable=true)
     * @Serializer\Exclude()
     */
    private $jury_member;

    /**
     * @var string
     * @ORM\Column(type="string", name="verify_comment", length=255,
     *     options={"comment"="Optional additional information provided by the verifier",
     *              "default"=NULL},
     *     nullable=true)
     * @Serializer\Exclude()
     */
    private $verify_comment;

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
     *     options={"comment"="Time judging ended, null = still busy",
     *              "unsigned"=true},
     *     nullable=true)
     */
    private $endtime = null;

    /**
     * @var boolean
     * @ORM\Column(type="boolean", name="valid",
     *     options={"comment"="Old external judgement is marked as invalid when receiving a new one",
     *              "default"="1"},
     *     nullable=false)
     */
    private $valid = true;

    /**
     * @var Contest
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Contest")
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
     * Set verified status of the result / difference
     *
     * @param boolean $verified
     *
     * @return ExternalJudgement
     */
    public function setVerified($verified)
    {
        $this->verified = $verified;

        return $this;
    }

    /**
     * Get verified status of the result / difference
     *
     * @return boolean
     */
    public function getVerified()
    {
        return $this->verified;
    }

    /**
     * Set jury member who verified this judgement
     *
     * @param string $juryMember
     *
     * @return ExternalJudgement
     */
    public function setJuryMember($juryMember)
    {
        $this->jury_member = $juryMember;

        return $this;
    }

    /**
     * Get jury member who verified this judgement
     *
     * @return string
     */
    public function getJuryMember()
    {
        return $this->jury_member;
    }

    /**
     * Set verify comment
     *
     * @param string $verifyComment
     *
     * @return ExternalJudgement
     */
    public function setVerifyComment($verifyComment)
    {
        $this->verify_comment = $verifyComment;

        return $this;
    }

    /**
     * Get verifyComment
     *
     * @return string
     */
    public function getVerifyComment()
    {
        return $this->verify_comment;
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

    /**
     * Get the max runtime for this external judgement
     * @return float
     */
    public function getMaxRuntime()
    {
        $max = 0;
        foreach ($this->external_runs as $run) {
            $max = max($run->getRuntime(), $max);
        }
        return $max;
    }

    /**
     * Get the sum runtime for this external judgement
     * @return float
     */
    public function getSumRuntime()
    {
        $sum = 0;
        foreach ($this->external_runs as $run) {
            $sum += $run->getRuntime();
        }
        return $sum;
    }
}
