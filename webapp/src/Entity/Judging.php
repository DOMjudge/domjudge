<?php declare(strict_types=1);
namespace App\Entity;

use App\Utils\Utils;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

/**
 * Result of judging a submission
 * @ORM\Entity()
 * @ORM\Table(
 *     name="judging",
 *     options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4", "comment"="Result of judging a submission"},
 *     indexes={
 *         @ORM\Index(name="submitid", columns={"submitid"}),
 *         @ORM\Index(name="judgehost", columns={"judgehost"}),
 *         @ORM\Index(name="cid", columns={"cid"}),
 *         @ORM\Index(name="rejudgingid", columns={"rejudgingid"}),
 *         @ORM\Index(name="prevjudgingid", columns={"prevjudgingid"})
 *     })
 */
class Judging extends BaseApiEntity implements ExternalRelationshipEntityInterface
{
    // constants for results
    const RESULT_CORRECT = 'correct';
    const RESULT_COMPILER_ERROR = 'compiler-error';

    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="judgingid", length=4,
     *     options={"comment"="Judging ID","unsigned"=true}, nullable=false)
     * @Serializer\SerializedName("id")
     * @Serializer\Type("string")
     */
    protected $judgingid;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", name="cid", length=4,
     *     options={"comment"="Contest ID","unsigned"=true,"default"="0"},
     *     nullable=false)
     * @Serializer\Exclude()
     */
    private $cid;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", name="submitid", length=4,
     *     options={"comment"="Submission ID being judged","unsigned"=true},
     *     nullable=false)
     * @Serializer\SerializedName("submission_id")
     * @Serializer\Type("string")
     */
    private $submitid;

    /**
     * @var double
     * @ORM\Column(type="decimal", precision=32, scale=9, name="starttime",
     *     options={"comment"="Time judging started", "unsigned"=true},
     *     nullable=false)
     * @Serializer\Exclude()
     */
    private $starttime;

    /**
     * @var double
     * @ORM\Column(type="decimal", precision=32, scale=9, name="endtime",
     *     options={"comment"="Time judging ended, null = still busy",
     *              "unsigned"=true,"default"="NULL"},
     *     nullable=true)
     * @Serializer\Exclude()
     */
    private $endtime;

    /**
     * @var string
     * @ORM\Column(type="string", name="result", length=32,
     *     options={"comment"="Result string as defined in config.php",
     *              "default"="NULL"},
     *     nullable=true)
     * @Serializer\Exclude()
     */
    private $result;

    /**
     * @var boolean
     * @ORM\Column(type="boolean", name="verified",
     *     options={"comment"="Result verified by jury member?",
     *              "default"="0"},
     *     nullable=false)
     * @Serializer\Exclude()
     */
    private $verified = false;

    /**
     * @var string
     * @ORM\Column(type="string", name="jury_member", length=255,
     *     options={"comment"="Name of jury member who verified this",
     *              "default"="NULL"},
     *     nullable=true)
     * @Serializer\Exclude()
     */
    private $jury_member;

    /**
     * @var string
     * @ORM\Column(type="string", name="verify_comment", length=255,
     *     options={"comment"="Optional additional information provided by the verifier",
     *              "default"="NULL"},
     *     nullable=true)
     * @Serializer\Exclude()
     */
    private $verify_comment;

    /**
     * @var boolean
     * @ORM\Column(type="boolean", name="valid",
     *     options={"comment"="Old judging is marked as invalid when rejudging",
     *              "default"="1"},
     *     nullable=false)
     */
    private $valid = true;

    /**
     * @var resource
     * @ORM\Column(type="blob", name="output_compile",
     *     options={"comment"="Output of the compiling the program",
     *              "default"="NULL"},
     *     nullable=true)
     * @Serializer\Exclude()
     */
    private $output_compile;

    /**
     * @Serializer\Exclude()
     */
    private $output_compile_as_string = null;

    /**
     * @var boolean
     * @ORM\Column(type="boolean", name="seen",
     *     options={"comment"="Whether the team has seen this judging",
     *              "default"="0"},
     *     nullable=false)
     * @Serializer\Exclude()
     */
    private $seen = false;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", name="rejudgingid", length=4,
     *     options={"comment"="Rejudging ID (if rejudge)","unsigned"=true,
     *              "default"="NULL"},
     *     nullable=true)
     * @Serializer\Exclude()
     */
    private $rejudgingid;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", name="prevjudgingid",
     *     options={"comment"="Previous valid judging (if rejudge)",
     *              "unsigned"=true,"default"="NULL"},
     *     nullable=true)
     * @Serializer\Exclude()
     */
    private $prevjudgingid;

    /**
     * @var string
     * @ORM\Column(type="string", name="judgehost", length=64,
     *     options={"comment"="Judgehost that performed the judging"},
     *     nullable=false)
     * @Serializer\Expose(if="context.getAttribute('domjudge_service').checkrole('jury')")
     * @Serializer\SerializedName("judgehost")
     */
    private $judgehost_name;

    /**
     * @ORM\ManyToOne(targetEntity="Contest")
     * @ORM\JoinColumn(name="cid", referencedColumnName="cid", onDelete="CASCADE")
     * @Serializer\Exclude()
     */
    private $contest;

    /**
     * @ORM\ManyToOne(targetEntity="Submission", inversedBy="judgings")
     * @ORM\JoinColumn(name="submitid", referencedColumnName="submitid", onDelete="CASCADE")
     * @Serializer\Exclude()
     */
    private $submission;

    /**
     * @ORM\ManyToOne(targetEntity="Judgehost", inversedBy="judgings")
     * @ORM\JoinColumn(name="judgehost", referencedColumnName="hostname")
     * @Serializer\Exclude()
     */
    private $judgehost;
    /**
     * rejudgings have one parent judging
     * @ORM\ManyToOne(targetEntity="Rejudging", inversedBy="judgings")
     * @ORM\JoinColumn(name="rejudgingid", referencedColumnName="rejudgingid", onDelete="SET NULL")
     * @Serializer\Exclude()
     */
    private $rejudging;

    /**
     * rejudgings have one parent judging
     * @ORM\ManyToOne(targetEntity="Judging")
     * @ORM\JoinColumn(name="prevjudgingid", referencedColumnName="judgingid", onDelete="SET NULL")
     * @Serializer\Exclude()
     */
    private $original_judging;

    /**
     * @ORM\OneToMany(targetEntity="JudgingRun", mappedBy="judging")
     * @Serializer\Exclude()
     */
    private $runs;


    /**
     * Get the max runtime for this judging
     * @return float
     */
    public function getMaxRuntime()
    {
        $max = 0;
        foreach ($this->runs as $run) {
            $max = max($run->getRuntime(), $max);
        }
        return $max;
    }

    /**
     * Get the sum runtime for this judging
     * @return float
     */
    public function getSumRuntime()
    {
        $sum = 0;
        foreach ($this->runs as $run) {
            $sum += $run->getRuntime();
        }
        return $sum;
    }

    /**
     * Get judgingid
     *
     * @return integer
     */
    public function getJudgingid()
    {
        return $this->judgingid;
    }

    /**
     * Set cid
     *
     * @param integer $cid
     *
     * @return Judging
     */
    public function setCid($cid)
    {
        $this->cid = $cid;

        return $this;
    }

    /**
     * Get cid
     *
     * @return integer
     */
    public function getCid()
    {
        return $this->cid;
    }

    /**
     * Set submitid
     *
     * @param integer $submitid
     *
     * @return Judging
     */
    public function setSubmitid($submitid)
    {
        $this->submitid = $submitid;

        return $this;
    }

    /**
     * Get submitid
     *
     * @return integer
     */
    public function getSubmitid()
    {
        return $this->submitid;
    }

    /**
     * Set starttime
     *
     * @param string $starttime
     *
     * @return Judging
     */
    public function setStarttime($starttime)
    {
        $this->starttime = $starttime;

        return $this;
    }

    /**
     * Get starttime
     *
     * @return string
     */
    public function getStarttime()
    {
        return $this->starttime;
    }

    /**
     * Get the absolute start time for this judging
     *
     * @return string
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("start_time")
     * @Serializer\Type("string")
     */
    public function getAbsoluteStartTime()
    {
        return Utils::absTime($this->getStarttime());
    }

    /**
     * Get the relative start time for this judging
     *
     * @return string
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("start_contest_time")
     * @Serializer\Type("string")
     */
    public function getRelativeStartTime()
    {
        return Utils::relTime($this->getStarttime() - $this->getContest()->getStarttime());
    }

    /**
     * Set endtime
     *
     * @param string $endtime
     *
     * @return Judging
     */
    public function setEndtime($endtime)
    {
        $this->endtime = $endtime;

        return $this;
    }

    /**
     * Get endtime
     *
     * @return string
     */
    public function getEndtime()
    {
        return $this->endtime;
    }

    /**
     * Get the absolute end time for this judging
     *
     * @return string|null
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("end_time")
     * @Serializer\Type("string")
     */
    public function getAbsoluteEndTime()
    {
        return $this->getEndtime() ? Utils::absTime($this->getEndtime()) : null;
    }

    /**
     * Get the relative end time for this judging
     *
     * @return string|null
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("end_contest_time")
     * @Serializer\Type("string")
     */
    public function getRelativeEndTime()
    {
        return $this->getEndtime() ? Utils::relTime($this->getEndtime() - $this->getContest()->getStarttime()) : null;
    }

    /**
     * Set result
     *
     * @param string $result
     *
     * @return Judging
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
     * Set verified
     *
     * @param boolean $verified
     *
     * @return Judging
     */
    public function setVerified($verified)
    {
        $this->verified = $verified;

        return $this;
    }

    /**
     * Get verified
     *
     * @return boolean
     */
    public function getVerified()
    {
        return $this->verified;
    }

    /**
     * Set juryMember
     *
     * @param string $juryMember
     *
     * @return Judging
     */
    public function setJuryMember($juryMember)
    {
        $this->jury_member = $juryMember;

        return $this;
    }

    /**
     * Get juryMember
     *
     * @return string
     */
    public function getJuryMember()
    {
        return $this->jury_member;
    }

    /**
     * Set verifyComment
     *
     * @param string $verifyComment
     *
     * @return Judging
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
     * Set valid
     *
     * @param boolean $valid
     *
     * @return Judging
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
     * Set outputCompile
     *
     * @param resource|string $outputCompile
     *
     * @return Judging
     */
    public function setOutputCompile($outputCompile)
    {
        $this->output_compile = $outputCompile;

        return $this;
    }

    /**
     * Get outputCompile
     *
     * @return resource|string|null
     */
    public function getOutputCompile(bool $asString = false)
    {
        if ($asString && $this->output_compile !== null) {
            if ($this->output_compile_as_string === null) {
                $this->output_compile_as_string = stream_get_contents($this->output_compile);
            }
            return $this->output_compile_as_string;
        }
        return $this->output_compile;
    }

    /**
     * Set seen
     *
     * @param boolean $seen
     *
     * @return Judging
     */
    public function setSeen($seen)
    {
        $this->seen = $seen;

        return $this;
    }

    /**
     * Get seen
     *
     * @return boolean
     */
    public function getSeen()
    {
        return $this->seen;
    }

    /**
     * Set rejudgingid
     *
     * @param integer $rejudgingid
     *
     * @return Judging
     */
    public function setRejudgingid($rejudgingid)
    {
        $this->rejudgingid = $rejudgingid;

        return $this;
    }

    /**
     * Get rejudgingid
     *
     * @return integer
     */
    public function getRejudgingid()
    {
        return $this->rejudgingid;
    }

    /**
     * Set prevjudgingid
     *
     * @param integer $prevjudgingid
     *
     * @return Judging
     */
    public function setPrevjudgingid($prevjudgingid)
    {
        $this->prevjudgingid = $prevjudgingid;

        return $this;
    }

    /**
     * Get prevjudgingid
     *
     * @return integer
     */
    public function getPrevjudgingid()
    {
        return $this->prevjudgingid;
    }

    /**
     * Get judgehost name
     *
     * @param string $judgehost_name
     *
     * @return Judging
     */
    public function setJudgehostName(string $judgehost_name)
    {
        $this->judgehost_name = $judgehost_name;

        return $this;
    }

    /**
     * Set judgehost name
     *
     * @return string
     */
    public function getJudgehostName(): string
    {
        return $this->judgehost_name;
    }

    /**
     * Set submission
     *
     * @param \App\Entity\Submission $submission
     *
     * @return Judging
     */
    public function setSubmission(\App\Entity\Submission $submission = null)
    {
        $this->submission = $submission;

        return $this;
    }

    /**
     * Get submission
     *
     * @return \App\Entity\Submission
     */
    public function getSubmission()
    {
        return $this->submission;
    }

    /**
     * Set contest
     *
     * @param \App\Entity\Contest $contest
     *
     * @return Judging
     */
    public function setContest(\App\Entity\Contest $contest = null)
    {
        $this->contest = $contest;

        return $this;
    }

    /**
     * Get contest
     *
     * @return \App\Entity\Contest
     */
    public function getContest()
    {
        return $this->contest;
    }

    /**
     * Set rejudging
     *
     * @param \App\Entity\Rejudging $rejudging
     *
     * @return Judging
     */
    public function setRejudging(\App\Entity\Rejudging $rejudging = null)
    {
        $this->rejudging = $rejudging;

        return $this;
    }

    /**
     * Get rejudging
     *
     * @return \App\Entity\Rejudging
     */
    public function getRejudging()
    {
        return $this->rejudging;
    }

    /**
     * Set originalJudging
     *
     * @param \App\Entity\Judging $originalJudging
     *
     * @return Judging
     */
    public function setOriginalJudging(\App\Entity\Judging $originalJudging = null)
    {
        $this->original_judging = $originalJudging;

        return $this;
    }

    /**
     * Get originalJudging
     *
     * @return \App\Entity\Judging
     */
    public function getOriginalJudging()
    {
        return $this->original_judging;
    }

    /**
     * Set judgehost
     *
     * @param \App\Entity\Judgehost $judgehost
     *
     * @return Judging
     */
    public function setJudgehost(\App\Entity\Judgehost $judgehost = null)
    {
        $this->judgehost = $judgehost;

        return $this;
    }

    /**
     * Get judgehost
     *
     * @return \App\Entity\Judgehost
     */
    public function getJudgehost()
    {
        return $this->judgehost;
    }
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->runs = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * Add run
     *
     * @param \App\Entity\JudgingRun $run
     *
     * @return Judging
     */
    public function addRun(\App\Entity\JudgingRun $run)
    {
        $this->runs[] = $run;

        return $this;
    }

    /**
     * Remove run
     *
     * @param \App\Entity\JudgingRun $run
     */
    public function removeRun(\App\Entity\JudgingRun $run)
    {
        $this->runs->removeElement($run);
    }

    /**
     * Get runs
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getRuns()
    {
        return $this->runs;
    }

    /**
     * Get the entities to check for external ID's while serializing.
     *
     * This method should return an array with as keys the JSON field names and as values the actual entity
     * objects that the SetExternalIdVisitor should check for applicable external ID's
     * @return array
     */
    public function getExternalRelationships(): array
    {
        return ['submission_id' => $this->getSubmission()];
    }

    /**
     * Check whether this judging is for an aborted judging
     * @return bool
     */
    public function isAborted()
    {
        // This logic has been copied from putSubmissions()
        return $this->getEndtime() === null && !$this->getValid() &&
            (!$this->getRejudging() || !$this->getRejudging()->getValid());
    }

    /**
     * Check whether this judging is still busy while the final result is already known,
     * e.g. with non-lazy evaluation.
     * @return bool
     */
    public function isStillBusy()
    {
        return !empty($this->getResult()) && empty($this->getEndtime()) && !$this->isAborted();
    }
}
