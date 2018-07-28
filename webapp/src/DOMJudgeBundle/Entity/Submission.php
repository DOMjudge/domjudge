<?php
namespace DOMJudgeBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

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
     */
    private $submitid;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", name="origsubmitid", options={"comment"="If set, specifies original submission in case of edit/resubmit"}, nullable=true)
     */
    private $origsubmitid;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", name="cid", options={"comment"="Contest ID"}, nullable=false)
     */
    private $cid;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", name="teamid", options={"comment"="Team ID"}, nullable=false)
     */
    private $teamid;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", name="probid", options={"comment"="Problem ID"}, nullable=false)
     */
    private $probid;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", name="langid", options={"comment"="Language ID"}, nullable=false)
     */
    private $langid;

    /**
     * @var double
     * @ORM\Column(type="decimal", precision=32, scale=9, name="submittime", options={"comment"="Time submitted", "unsigned"=true}, nullable=false)
     */
    private $submittime;


    /**
     * @var string
     * @ORM\Column(type="string", name="judgehost", length=50, options={"comment"="Current/last judgehost judging this submission", "collation"="utf8mb4_bin"}, nullable=true)
     */
    private $judgehost;

    /**
     * @var boolean
     * @ORM\Column(type="boolean", name="valid", options={"comment"="If false ignore this submission in all scoreboard calculations"}, nullable=false)
     */
    private $valid = true;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", name="rejudgingid", options={"comment"="Rejudging ID (if rejudge)"}, nullable=true)
     */
    private $rejudgingid;

    /**
     * @var string
     * @ORM\Column(type="string", name="expected_results", length=255, options={"comment"="JSON encoded list of expected results - used to validate jury submissions", "collation"="utf8mb4_bin"}, nullable=true)
     */
    private $expected_results;

    /**
     * @var string
     * @ORM\Column(type="string", name="entry_point", length=255, options={"comment"="Optional entry point. Can be used e.g. for java main class.", "collation"="utf8mb4_bin"}, nullable=true)
     */
    private $entry_point;

    /**
     * @ORM\ManyToOne(targetEntity="Contest", inversedBy="submissions")
     * @ORM\JoinColumn(name="cid", referencedColumnName="cid")
     */
    private $contest;

    /**
     * @ORM\ManyToOne(targetEntity="Language", inversedBy="submissions")
     * @ORM\JoinColumn(name="langid", referencedColumnName="langid")
     */
    private $language;

    /**
     * @ORM\ManyToOne(targetEntity="Team", inversedBy="submissions")
     * @ORM\JoinColumn(name="teamid", referencedColumnName="teamid")
     */
    private $team;

    /**
     * @ORM\ManyToOne(targetEntity="Problem", inversedBy="submissions")
     * @ORM\JoinColumn(name="probid", referencedColumnName="probid")
     */
    private $problem;

    /**
     * @ORM\OneToMany(targetEntity="Judging", mappedBy="submission")
     */
    private $judgings;

    /**
     * @ORM\OneToMany(targetEntity="SubmissionFile", mappedBy="submission")
     */
    private $files;

    /**
     * @ORM\OneToMany(targetEntity="Balloon", mappedBy="submission")
     */
    private $balloons;

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
     * @param integer $langid
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
     * @return integer
     */
    public function getLangid()
    {
        return $this->langid;
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
        $this->judgings = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * Add judging
     *
     * @param \DOMJudgeBundle\Entity\Judging $judging
     *
     * @return Submission
     */
    public function addJudging(\DOMJudgeBundle\Entity\Judging $judging)
    {
        $this->judgings[] = $judging;

        return $this;
    }

    /**
     * Remove judging
     *
     * @param \DOMJudgeBundle\Entity\Judging $judging
     */
    public function removeJudging(\DOMJudgeBundle\Entity\Judging $judging)
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
     * @param \DOMJudgeBundle\Entity\Language $language
     *
     * @return Submission
     */
    public function setLanguage(\DOMJudgeBundle\Entity\Language $language = null)
    {
        $this->language = $language;

        return $this;
    }

    /**
     * Get language
     *
     * @return \DOMJudgeBundle\Entity\Language
     */
    public function getLanguage()
    {
        return $this->language;
    }

    /**
     * Add file
     *
     * @param \DOMJudgeBundle\Entity\SubmissionFile $file
     *
     * @return Submission
     */
    public function addFile(\DOMJudgeBundle\Entity\SubmissionFile $file)
    {
        $this->files[] = $file;

        return $this;
    }

    /**
     * Remove file
     *
     * @param \DOMJudgeBundle\Entity\SubmissionFile $file
     */
    public function removeFile(\DOMJudgeBundle\Entity\SubmissionFile $file)
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
     * @param \DOMJudgeBundle\Entity\Balloon $balloon
     *
     * @return Submission
     */
    public function addBalloon(\DOMJudgeBundle\Entity\Balloon $balloon)
    {
        $this->balloons[] = $balloon;

        return $this;
    }

    /**
     * Remove balloon
     *
     * @param \DOMJudgeBundle\Entity\Balloon $balloon
     */
    public function removeBalloon(\DOMJudgeBundle\Entity\Balloon $balloon)
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
     * @param \DOMJudgeBundle\Entity\Contest $contest
     *
     * @return Submission
     */
    public function setContest(\DOMJudgeBundle\Entity\Contest $contest = null)
    {
        $this->contest = $contest;

        return $this;
    }

    /**
     * Get contest
     *
     * @return \DOMJudgeBundle\Entity\Contest
     */
    public function getContest()
    {
        return $this->contest;
    }

    /**
     * Set problem
     *
     * @param \DOMJudgeBundle\Entity\Problem $problem
     *
     * @return Submission
     */
    public function setProblem(\DOMJudgeBundle\Entity\Problem $problem = null)
    {
        $this->problem = $problem;

        return $this;
    }

    /**
     * Get problem
     *
     * @return \DOMJudgeBundle\Entity\Problem
     */
    public function getProblem()
    {
        return $this->problem;
    }
}
