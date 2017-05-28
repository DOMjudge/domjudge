<?php
namespace DOMJudgeBundle\Entity;
use Doctrine\ORM\Mapping as ORM;
/**
 * Log of all events during a contest
 * @ORM\Entity()
 * @ORM\Table(name="event", options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4"})
 */
class Event
{
    /**
     * @var int
     * @ORM\Id
     * @ORM\Column(type="integer", name="eventid", options={"comment"="Unique ID"}, nullable=false)
     */
    private $eventid;

    /**
     * @var double
     * @ORM\Column(type="decimal", precision=32, scale=9, name="eventtime", options={"comment"="When the event occurred", "unsigned"=true}, nullable=false)
     */
    private $eventtime;

    /**
     * @var int
     * @ORM\Column(type="integer", name="cid", options={"comment"="Contest ID"}, nullable=false)
     */
    private $cid;

    /**
     * @var int
     * @ORM\Column(type="integer", name="clarid", options={"comment"="In reply to clarification ID"}, nullable=true)
     */
    private $clarid;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", name="langid", options={"comment"="Language ID"}, nullable=true)
     */
    private $langid;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", name="probid", options={"comment"="Problem ID"}, nullable=true)
     */
    private $probid;

    /**
     * @var int
     * @ORM\Column(type="integer", name="submitid", options={"comment"="Submission ID"}, nullable=true)
     */
    private $submitid;

    /**
     * @var int
     * @ORM\Column(type="integer", name="judgingid", options={"comment"="Judging ID"}, nullable=true)
     */
    private $judgingid;

    /**
     * @var int
     * @ORM\Column(type="integer", name="teamid", options={"comment"="Team ID"}, nullable=true)
     */
    private $teamid;

    /**
     * @var string
     * @ORM\Column(type="text", length=4294967295, name="body", options={"comment"="Event Description"}, nullable=false)
     */
    private $body;

  /**
   * @ORM\ManyToOne(targetEntity="Contest", inversedBy="events")
   * @ORM\JoinColumn(name="cid", referencedColumnName="cid")
   */
  private $contest;

  /**
   * @ORM\ManyToOne(targetEntity="Clarification", inversedBy="events")
   * @ORM\JoinColumn(name="clarid", referencedColumnName="clarid")
   */
  private $clarification;

  /**
   * @ORM\ManyToOne(targetEntity="Language", inversedBy="events")
   * @ORM\JoinColumn(name="langid", referencedColumnName="langid")
   */
  private $language;

  /**
   * @ORM\ManyToOne(targetEntity="Problem", inversedBy="events")
   * @ORM\JoinColumn(name="probid", referencedColumnName="probid")
   */
  private $problem;

  /**
   * @ORM\ManyToOne(targetEntity="Submission", inversedBy="events")
   * @ORM\JoinColumn(name="submitid", referencedColumnName="submitid")
   */
  private $submission;

  /**
   * @ORM\ManyToOne(targetEntity="Judging", inversedBy="events")
   * @ORM\JoinColumn(name="prevjudgingid", referencedColumnName="judgingid")
   */
  private $judging;

  /**
   * @ORM\ManyToOne(targetEntity="Team", inversedBy="events")
   * @ORM\JoinColumn(name="teamid", referencedColumnName="teamid")
   */
  private $team;


    /**
     * Set eventid
     *
     * @param integer $eventid
     *
     * @return Event
     */
    public function setEventid($eventid)
    {
        $this->eventid = $eventid;

        return $this;
    }

    /**
     * Get eventid
     *
     * @return integer
     */
    public function getEventid()
    {
        return $this->eventid;
    }

    /**
     * Set eventtime
     *
     * @param string $eventtime
     *
     * @return Event
     */
    public function setEventtime($eventtime)
    {
        $this->eventtime = $eventtime;

        return $this;
    }

    /**
     * Get eventtime
     *
     * @return string
     */
    public function getEventtime()
    {
        return $this->eventtime;
    }

    /**
     * Set cid
     *
     * @param integer $cid
     *
     * @return Event
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
     * Set clarid
     *
     * @param integer $clarid
     *
     * @return Event
     */
    public function setClarid($clarid)
    {
        $this->clarid = $clarid;

        return $this;
    }

    /**
     * Get clarid
     *
     * @return integer
     */
    public function getClarid()
    {
        return $this->clarid;
    }

    /**
     * Set langid
     *
     * @param integer $langid
     *
     * @return Event
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
     * Set probid
     *
     * @param integer $probid
     *
     * @return Event
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
     * Set submitid
     *
     * @param integer $submitid
     *
     * @return Event
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
     * Set judgingid
     *
     * @param integer $judgingid
     *
     * @return Event
     */
    public function setJudgingid($judgingid)
    {
        $this->judgingid = $judgingid;

        return $this;
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
     * Set teamid
     *
     * @param integer $teamid
     *
     * @return Event
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
     * Set body
     *
     * @param string $body
     *
     * @return Event
     */
    public function setBody($body)
    {
        $this->body = $body;

        return $this;
    }

    /**
     * Get body
     *
     * @return string
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * Set clarification
     *
     * @param \DOMJudgeBundle\Entity\Clarification $clarification
     *
     * @return Event
     */
    public function setClarification(\DOMJudgeBundle\Entity\Clarification $clarification = null)
    {
        $this->clarification = $clarification;

        return $this;
    }

    /**
     * Get clarification
     *
     * @return \DOMJudgeBundle\Entity\Clarification
     */
    public function getClarification()
    {
        return $this->clarification;
    }

    /**
     * Set language
     *
     * @param \DOMJudgeBundle\Entity\Language $language
     *
     * @return Event
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
     * Set problem
     *
     * @param \DOMJudgeBundle\Entity\Problem $problem
     *
     * @return Event
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

    /**
     * Set submission
     *
     * @param \DOMJudgeBundle\Entity\Submission $submission
     *
     * @return Event
     */
    public function setSubmission(\DOMJudgeBundle\Entity\Submission $submission = null)
    {
        $this->submission = $submission;

        return $this;
    }

    /**
     * Get submission
     *
     * @return \DOMJudgeBundle\Entity\Submission
     */
    public function getSubmission()
    {
        return $this->submission;
    }

    /**
     * Set judging
     *
     * @param \DOMJudgeBundle\Entity\Judging $judging
     *
     * @return Event
     */
    public function setJudging(\DOMJudgeBundle\Entity\Judging $judging = null)
    {
        $this->judging = $judging;

        return $this;
    }

    /**
     * Get judging
     *
     * @return \DOMJudgeBundle\Entity\Judging
     */
    public function getJudging()
    {
        return $this->judging;
    }

    /**
     * Set team
     *
     * @param \DOMJudgeBundle\Entity\Team $team
     *
     * @return Event
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
     * Set contest
     *
     * @param \DOMJudgeBundle\Entity\Contest $contest
     *
     * @return Event
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
}
