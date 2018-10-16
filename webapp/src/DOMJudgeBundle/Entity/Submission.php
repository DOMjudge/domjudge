<?php declare(strict_types=1);
namespace DOMJudgeBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use DOMJudgeBundle\Utils\Utils;
use JMS\Serializer\Annotation as Serializer;

/**
 * All incoming submissions
 * @ORM\Entity()
 * @ORM\Table(name="submission", options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4"})
 */
class Submission
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
    private $submitid;

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
     * @ORM\Column(type="decimal", precision=32, scale=9, name="submittime", options={"comment"="Time submitted", "unsigned"=true}, nullable=false)
     * @Serializer\Exclude()
     */
    private $submittime;


    /**
     * @var string
     * @ORM\Column(type="string", name="judgehost", length=50, options={"comment"="Current/last judgehost judging this submission", "collation"="utf8mb4_bin"}, nullable=true)
     * @Serializer\Exclude()
     */
    private $judgehost;

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
     * @var string
     * @ORM\Column(type="string", name="expected_results", length=255, options={"comment"="JSON encoded list of expected results - used to validate jury submissions", "collation"="utf8mb4_bin"}, nullable=true)
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
     * @ORM\ManyToOne(targetEntity="Contest", inversedBy="submissions")
     * @ORM\JoinColumn(name="cid", referencedColumnName="cid")
     * @Serializer\Exclude()
     */
    private $contest;

    /**
     * @ORM\ManyToOne(targetEntity="Language", inversedBy="submissions")
     * @ORM\JoinColumn(name="langid", referencedColumnName="langid")
     * @Serializer\Exclude()
     */
    private $language;

    /**
     * @ORM\ManyToOne(targetEntity="Team", inversedBy="submissions")
     * @ORM\JoinColumn(name="teamid", referencedColumnName="teamid")
     * @Serializer\Exclude()
     */
    private $team;

    /**
     * @ORM\ManyToOne(targetEntity="Problem", inversedBy="submissions")
     * @ORM\JoinColumn(name="probid", referencedColumnName="probid")
     * @Serializer\Exclude()
     */
    private $problem;

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
     * @ORM\OneToMany(targetEntity="Balloon", mappedBy="submission")
     * @Serializer\Exclude()
     */
    private $balloons;


    public function getResult() {
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
     * @param string $judgehost
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
     * @return string
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
     * @param string $expectedResults
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
     * @return string
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
        $this->judgings = new ArrayCollection();
        $this->files = new ArrayCollection();
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
}
