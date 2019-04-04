<?php declare(strict_types=1);

namespace DOMJudgeBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use DOMJudgeBundle\Utils\Utils;
use JMS\Serializer\Annotation as Serializer;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * All incoming submissions
 * @ORM\Entity()
 * @ORM\Table(name="submission", options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4"})
 * @UniqueEntity("externalid")
 */
class Submission extends BaseApiEnttiy implements ExternalRelationshipEntityInterface
{
    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="submitid", options={"comment"="Unique ID"}, nullable=false)
     * @Serializer\SerializedName("id")
     * @Serializer\Type("string")
     */
    protected $submitid;

    /**
     * @var string
     * @ORM\Column(type="string", name="externalid", length=255, options={"comment"="Submission ID in an external system", "collation"="utf8mb4_bin"}, nullable=true)
     */
    protected $externalid;

    /**
     * @var string
     * @ORM\Column(type="string", name="externalresult", length=255, options={"comment"="Result string as returned from external CCS, e.g. Kattis"}, nullable=true)
     * @Serializer\Exclude()
     */
    private $externalresult;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", name="origsubmitid", options={"comment"="If set, specifies original submission in case of edit/resubmit"}, nullable=true)
     * @Serializer\Exclude()
     */
    private $origsubmitid;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", name="cid", options={"comment"="Contest ID"}, nullable=false)
     * @Serializer\Exclude()
     */
    private $cid;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", name="teamid", options={"comment"="Team ID"}, nullable=false)
     * @Serializer\SerializedName("team_id")
     * @Serializer\Type("string")
     */
    private $teamid;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", name="probid", options={"comment"="Problem ID"}, nullable=false)
     * @Serializer\SerializedName("problem_id")
     * @Serializer\Type("string")
     */
    private $probid;

    /**
     * @var int
     *
     * @ORM\Column(type="string", name="langid", options={"comment"="Language ID"}, nullable=false)
     * @Serializer\Exclude()
     */
    private $langid;

    /**
     * @var double
     * @ORM\Column(type="decimal", precision=32, scale=9, name="submittime", options={"comment"="Time submitted",
     *                             "unsigned"=true}, nullable=false)
     * @Serializer\Exclude()
     */
    private $submittime;

    /**
     * @var boolean
     * @ORM\Column(type="boolean", name="valid", options={"comment"="If false ignore this submission in all scoreboard calculations"}, nullable=false)
     * @Serializer\Exclude()
     */
    private $valid = true;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", name="rejudgingid", options={"comment"="Rejudging ID (if rejudge)"}, nullable=true)
     * @Serializer\Exclude()
     */
    private $rejudgingid;

    /**
     * @var array
     * @ORM\Column(type="json_array", name="expected_results", length=255, options={"comment"="JSON encoded list of expected results - used to validate jury submissions", "collation"="utf8mb4_bin"}, nullable=true)
     * @Serializer\Exclude()
     */
    private $expected_results;

    /**
     * @var string
     * @ORM\Column(type="string", name="entry_point", length=255, options={"comment"="Optional entry point. Can be used e.g. for java main class.", "collation"="utf8mb4_bin"}, nullable=true)
     * @Serializer\Expose(if="context.getAttribute('domjudge_service').checkrole('jury')")
     */
    private $entry_point;

    /**
     * @var Judgehost|null
     *
     * @ORM\ManyToOne(targetEntity="Judgehost")
     * @ORM\JoinColumn(name="judgehost", referencedColumnName="hostname", onDelete="SET NULL")
     * @Serializer\Exclude()
     */
    private $judgehost;

    /**
     * @ORM\ManyToOne(targetEntity="Contest", inversedBy="submissions")
     * @ORM\JoinColumn(name="cid", referencedColumnName="cid", onDelete="CASCADE")
     * @Serializer\Exclude()
     */
    private $contest;

    /**
     * @ORM\ManyToOne(targetEntity="Language", inversedBy="submissions")
     * @ORM\JoinColumn(name="langid", referencedColumnName="langid", onDelete="CASCADE")
     * @Serializer\Exclude()
     */
    private $language;

    /**
     * @ORM\ManyToOne(targetEntity="Team", inversedBy="submissions")
     * @ORM\JoinColumn(name="teamid", referencedColumnName="teamid", onDelete="CASCADE")
     * @Serializer\Exclude()
     */
    private $team;

    /**
     * @ORM\ManyToOne(targetEntity="Problem", inversedBy="submissions")
     * @ORM\JoinColumn(name="probid", referencedColumnName="probid", onDelete="CASCADE")
     * @Serializer\Exclude()
     */
    private $problem;

    /**
     * @ORM\ManyToOne(targetEntity="DOMJudgeBundle\Entity\ContestProblem", inversedBy="submissions")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="probid", referencedColumnName="probid"),
     *   @ORM\JoinColumn(name="cid", referencedColumnName="cid")
     * })
     * @Serializer\Exclude()
     */
    private $contest_problem;

    /**
     * @ORM\OneToMany(targetEntity="Judging", mappedBy="submission")
     * @Serializer\Exclude()
     */
    private $judgings;

    /**
     * @var ArrayCollection
     * @ORM\OneToMany(targetEntity="SubmissionFile", mappedBy="submission")
     * @Serializer\Exclude()
     */
    private $files;

    /**
     * @var ArrayCollection
     * @ORM\OneToMany(targetEntity="DOMJudgeBundle\Entity\SubmissionFileWithSourceCode", mappedBy="submission")
     * @Serializer\Exclude()
     */
    private $files_with_source_code;

    /**
     * @ORM\OneToMany(targetEntity="Balloon", mappedBy="submission")
     * @Serializer\Exclude()
     */
    private $balloons;

    /**
     * rejudgings have one parent judging
     * @ORM\ManyToOne(targetEntity="Rejudging", inversedBy="submissions")
     * @ORM\JoinColumn(name="rejudgingid", referencedColumnName="rejudgingid", onDelete="SET NULL")
     * @Serializer\Exclude()
     */
    private $rejudging;

    /**
     * @var string Holds the old result in the case this submission is displayed in a rejudging table
     * @Serializer\Exclude()
     */
    private $old_result;

    public function getResult()
    {
        foreach ($this->judgings as $j) {
            if ($j->getValid()) {
                return $j->getResult();
            }
        }
        return null;
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
     * Set externalid
     *
     * @param string $externalid
     *
     * @return Submission
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
     * Set externalresult
     *
     * @param string $externalresult
     *
     * @return Submission
     */
    public function setExternalresult($externalresult)
    {
        $this->externalresult = $externalresult;

        return $this;
    }

    /**
     * Get externalresult
     *
     * @return string
     */
    public function getExternalresult()
    {
        return $this->externalresult;
    }

    /**
     * Set origsubmitid
     *
     * @param integer $origsubmitid
     *
     * @return Submission
     */
    public function setOrigsubmitid($origsubmitid)
    {
        $this->origsubmitid = $origsubmitid;

        return $this;
    }

    /**
     * Get origsubmitid
     *
     * @return integer
     */
    public function getOrigsubmitid()
    {
        return $this->origsubmitid;
    }

    /**
     * Set cid
     *
     * @param integer $cid
     *
     * @return Submission
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
     * Set teamid
     *
     * @param integer $teamid
     *
     * @return Submission
     */
    public function setTeamid($teamid)
    {
        $this->teamid = $teamid;

        return $this;
    }

    /**
     * Get teamid
     *
     * @return integer
     */
    public function getTeamid()
    {
        return $this->teamid;
    }

    /**
     * Set probid
     *
     * @param integer $probid
     *
     * @return Submission
     */
    public function setProbid($probid)
    {
        $this->probid = $probid;

        return $this;
    }

    /**
     * Get probid
     *
     * @return integer
     */
    public function getProbid()
    {
        return $this->probid;
    }

    /**
     * Set langid
     *
     * @param string $langid
     *
     * @return Submission
     */
    public function setLangid($langid)
    {
        $this->langid = $langid;

        return $this;
    }

    /**
     * Get langid
     *
     * @return string
     */
    public function getLangid()
    {
        return $this->langid;
    }

    /**
     * Get the language ID
     * @return string
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("language_id")
     * @Serializer\Type("string")
     */
    public function getLanguageId()
    {
        return $this->getLanguage()->getExternalid();
    }

    /**
     * Set submittime
     *
     * @param string $submittime
     *
     * @return Submission
     */
    public function setSubmittime($submittime)
    {
        $this->submittime = $submittime;

        return $this;
    }

    /**
     * Get submittime
     *
     * @return string
     */
    public function getSubmittime()
    {
        return $this->submittime;
    }

    /**
     * Get the absolute submit time for this submission
     *
     * @return string
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("time")
     * @Serializer\Type("string")
     */
    public function getAbsoluteSubmitTime()
    {
        return Utils::absTime($this->getSubmittime());
    }

    /**
     * Get the relative submit time for this submission
     *
     * @return string
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("contest_time")
     * @Serializer\Type("string")
     */
    public function getRelativeSubmitTime()
    {
        return Utils::relTime($this->getSubmittime() - $this->getContest()->getStarttime());
    }

    /**
     * Set judgehost
     *
     * @param Judgehost|null $judgehost
     *
     * @return Submission
     */
    public function setJudgehost($judgehost)
    {
        $this->judgehost = $judgehost;

        return $this;
    }

    /**
     * Get judgehost
     *
     * @return Judgehost|null
     */
    public function getJudgehost()
    {
        return $this->judgehost;
    }

    /**
     * Set valid
     *
     * @param boolean $valid
     *
     * @return Submission
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
     * Set rejudgingid
     *
     * @param integer $rejudgingid
     *
     * @return Submission
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
     * Set expectedResults
     *
     * @param array $expectedResults
     *
     * @return Submission
     */
    public function setExpectedResults($expectedResults)
    {
        $this->expected_results = $expectedResults;

        return $this;
    }

    /**
     * Get expectedResults
     *
     * @return array
     */
    public function getExpectedResults()
    {
        return $this->expected_results;
    }

    /**
     * Set entry_point
     *
     * @param string $entryPoint
     *
     * @return Submission
     */
    public function setEntryPoint($entryPoint)
    {
        $this->entry_point = $entryPoint;

        return $this;
    }

    /**
     * Get entry_point
     *
     * @return string
     */
    public function getEntryPoint()
    {
        return $this->entry_point;
    }

    /**
     * Set team
     *
     * @param \DOMJudgeBundle\Entity\Team $team
     *
     * @return Submission
     */
    public function setTeam(\DOMJudgeBundle\Entity\Team $team = null)
    {
        $this->team = $team;

        return $this;
    }

    /**
     * Get team
     *
     * @return \DOMJudgeBundle\Entity\Team
     */
    public function getTeam()
    {
        return $this->team;
    }

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->judgings               = new ArrayCollection();
        $this->files                  = new ArrayCollection();
        $this->files_with_source_code = new ArrayCollection();
    }

    /**
     * Add judging
     *
     * @param Judging $judging
     *
     * @return Submission
     */
    public function addJudging(Judging $judging)
    {
        $this->judgings[] = $judging;

        return $this;
    }

    /**
     * Remove judging
     *
     * @param Judging $judging
     */
    public function removeJudging(Judging $judging)
    {
        $this->judgings->removeElement($judging);
    }

    /**
     * Get judgings
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getJudgings()
    {
        return $this->judgings;
    }

    /**
     * Set language
     *
     * @param Language $language
     *
     * @return Submission
     */
    public function setLanguage(Language $language = null)
    {
        $this->language = $language;

        return $this;
    }

    /**
     * Get language
     *
     * @return Language
     */
    public function getLanguage()
    {
        return $this->language;
    }

    /**
     * Add file
     *
     * @param SubmissionFile $file
     *
     * @return Submission
     */
    public function addFile(SubmissionFile $file)
    {
        $this->files->add($file);

        return $this;
    }

    /**
     * Remove file
     *
     * @param SubmissionFile $file
     */
    public function removeFile(SubmissionFile $file)
    {
        $this->files->removeElement($file);
    }

    /**
     * Get files
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getFiles()
    {
        return $this->files;
    }

    /**
     * Add file with source code
     *
     * @param SubmissionFileWithSourceCode $file
     *
     * @return Submission
     */
    public function addFileWithSourceCode(SubmissionFileWithSourceCode $file)
    {
        $this->files_with_source_code->add($file);

        return $this;
    }

    /**
     * Remove file with source code
     *
     * @param SubmissionFileWithSourceCode $file
     */
    public function removeFileWithSourceCode(SubmissionFileWithSourceCode $file)
    {
        $this->files_with_source_code->removeElement($file);
    }

    /**
     * Get files with source code
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getFilesWithSourceCode()
    {
        return $this->files_with_source_code;
    }

    /**
     * Add balloon
     *
     * @param Balloon $balloon
     *
     * @return Submission
     */
    public function addBalloon(Balloon $balloon)
    {
        $this->balloons[] = $balloon;

        return $this;
    }

    /**
     * Remove balloon
     *
     * @param Balloon $balloon
     */
    public function removeBalloon(Balloon $balloon)
    {
        $this->balloons->removeElement($balloon);
    }

    /**
     * Get balloons
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getBalloons()
    {
        return $this->balloons;
    }

    /**
     * Set contest
     *
     * @param Contest $contest
     *
     * @return Submission
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
     * Set problem
     *
     * @param Problem $problem
     *
     * @return Submission
     */
    public function setProblem(Problem $problem = null)
    {
        $this->problem = $problem;

        return $this;
    }

    /**
     * Get problem
     *
     * @return Problem
     */
    public function getProblem()
    {
        return $this->problem;
    }

    /**
     * Set contest problem
     *
     * @param ContestProblem $contestProblem
     *
     * @return Submission
     */
    public function setContestProblem(ContestProblem $contestProblem = null)
    {
        $this->contest_problem = $contestProblem;

        return $this;
    }

    /**
     * Get contest problem
     *
     * @return ContestProblem
     */
    public function getContestProblem()
    {
        return $this->contest_problem;
    }

    /**
     * Set rejudging
     *
     * @param Rejudging $rejudging
     *
     * @return Submission
     */
    public function setRejudging(Rejudging $rejudging = null)
    {
        $this->rejudging = $rejudging;

        return $this;
    }

    /**
     * Get rejudging
     *
     * @return Rejudging
     */
    public function getRejudging()
    {
        return $this->rejudging;
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
        return [
            'language_id' => $this->getLanguage(),
            'problem_id' => $this->getProblem(),
            'team_id' => $this->getTeam(),
        ];
    }

    /**
     * Return whether this submission is after the freeze
     * @return bool
     */
    public function isAfterFreeze(): bool
    {
        return $this->getContest()->getFreezetime() !== null && (float)$this->getSubmittime() >= (float)$this->getContest()->getFreezetime();
    }

    /**
     * @return string
     */
    public function getOldResult(): string
    {
        return $this->old_result;
    }

    /**
     * @param string $old_result
     * @return Submission
     */
    public function setOldResult(string $old_result): Submission
    {
        $this->old_result = $old_result;
        return $this;
    }

    /**
     * Check whether this submission is for an aborted judging
     * @return bool
     */
    public function isAborted()
    {
        // This logic has been copied from putSubmissions()
        /** @var Judging|null $judging */
        $judging = $this->getJudgings()->first();
        if (!$judging) {
            return false;
        }

        return $judging->getEndtime() === null && !$judging->getValid() &&
            (!$judging->getRejudging() || !$judging->getRejudging()->getValid());
    }

    /**
     * Check whether this submission is still busy while the final result is already known,
     * e.g. with non-lazy evaluation.
     * @return bool
     */
    public function isStillBusy()
    {
        /** @var Judging|null $judging */
        $judging = $this->getJudgings()->first();
        if (!$judging) {
            return false;
        }

        return !empty($judging->getResult()) && empty($judging->getEndtime()) && !$this->isAborted();
    }
}
