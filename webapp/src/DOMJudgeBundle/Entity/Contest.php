<?php
namespace DOMJudgeBundle\Entity;
use Doctrine\ORM\Mapping as ORM;
/**
 * Contests that will be run with this install
 * @ORM\Entity()
 * @ORM\Table(name="contest", options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4"})
 */
class Contest
{
    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="SEQUENCE")
     * @ORM\Column(type="integer", name="cid", options={"comment"="Unique ID"}, nullable=false)
     */
    private $cid;

    /**
     * @var string
     * TODO: ORM\Unique on first 190 characters
     * @ORM\Column(type="string", name="externalid", length=255, options={"comment"="Contest ID in an external system", "collation"="utf8mb4_bin"}, nullable=true)
     */
    private $externalid;

    /**
     * @var string
     * @ORM\Column(type="string", name="name", length=255, options={"comment"="Descriptive name"}, nullable=false)
     */
    private $name;

    /**
     * @var string
     * @ORM\Column(type="string", name="shortname", length=255, options={"comment"="Short name for this contest"}, nullable=false)
     */
    private $shortname;

    /**
     * @var double
     * @ORM\Column(type="decimal", precision=32, scale=9, name="activatetime", options={"comment"="Time contest becomes visible in team/public views", "unsigned"=true}, nullable=false)
     */
    private $activatetime;

    /**
     * @var double
     * @ORM\Column(type="decimal", precision=32, scale=9, name="starttime", options={"comment"="Time contest starts, submissions accepted", "unsigned"=true}, nullable=false)
     */
    private $starttime;

    /**
     * @var double
     * @ORM\Column(type="decimal", precision=32, scale=9, name="freezetime", options={"comment"="Time scoreboard is frozen", "unsigned"=true}, nullable=true)
     */
    private $freezetime;

    /**
     * @var double
     * @ORM\Column(type="decimal", precision=32, scale=9, name="endtime", options={"comment"="Time after which no more submissions are accepted", "unsigned"=true}, nullable=false)
     */
    private $endtime;

    /**
     * @var double
     * @ORM\Column(type="decimal", precision=32, scale=9, name="unfreezetime", options={"comment"="Unfreeze a frozen scoreboard at this time", "unsigned"=true}, nullable=true)
     */
    private $unfreezetime;

    /**
     * @var double
     * @ORM\Column(type="decimal", precision=32, scale=9, name="deactivatetime", options={"comment"="Time contest becomes invisible in team/public views", "unsigned"=true}, nullable=true)
     */
    private $deactivatetime;

    /**
     * @var string
     * @ORM\Column(type="string", length=64, name="activatetime_string", options={"comment"="Authoritative absolute or relative string representation of activatetime"}, nullable=false)
     */
    private $activatetime_string;

    /**
     * @var string
     * @ORM\Column(type="string", length=64, name="starttime_string", options={"comment"="Authoritative absolute (only!) string representation of starttime"}, nullable=false)
     */
    private $starttime_string;

    /**
     * @var string
     * @ORM\Column(type="string", length=64, name="freezetime_string", options={"comment"="Authoritative absolute or relative string representation of freezetime"}, nullable=true)
     */
    private $freezetime_string;

    /**
     * @var string
     * @ORM\Column(type="string", length=64, name="endtime_string", options={"comment"="Authoritative absolute or relative string representation of endtime"}, nullable=false)
     */
    private $endtime_string;

    /**
     * @var string
     * @ORM\Column(type="string", length=64, name="unfreezetime_string", options={"comment"="Authoritative absolute or relative string representation of unfreezetime"}, nullable=true)
     */
    private $unfreezetime_string;

    /**
     * @var string
     * @ORM\Column(type="string", length=64, name="deactivatetime_string", options={"comment"="Authoritative absolute or relative string representation of deactivatetime"}, nullable=true)
     */
    private $deactivatetime_string;

    /**
     * @var boolean
     * @ORM\Column(type="boolean", name="enabled", options={"comment"="Whether this contest can be active"}, nullable=false)
     */
    private $enabled = true;

    /**
     * @var boolean
     * @ORM\Column(type="boolean", name="process_balloons", options={"comment"="Will balloons be processed for this contest?"}, nullable=false)
     */
    private $process_balloons = true;

    /**
     * @var boolean
     * @ORM\Column(type="boolean", name="public", options={"comment"="Is this contest visible for the public and non-associated teams?"}, nullable=false)
     */
    private $public = true;

    /**
     * @ORM\ManyToMany(targetEntity="Team", inversedBy="contests")
     * @ORM\JoinTable(name="contestteam",
     *      joinColumns={@ORM\JoinColumn(name="cid", referencedColumnName="cid")},
     *      inverseJoinColumns={@ORM\JoinColumn(name="teamid", referencedColumnName="teamid")}
     *      )
     */
    private $teams;

    /**
     * @ORM\OneToMany(targetEntity="Clarification", mappedBy="contest")
     */
    private $clarifications;

    /**
     * @ORM\OneToMany(targetEntity="Submission", mappedBy="contest")
     */
    private $submissions;

    /**
     * @ORM\OneToMany(targetEntity="ContestProblem", mappedBy="contest")
     */
    private $problems;

    /**
     * @ORM\OneToMany(targetEntity="Event", mappedBy="contest")
     */
    private $events;

    /**
     * @ORM\OneToMany(targetEntity="InternalError", mappedBy="contest")
     */
    private $internal_errors;

    /**
     * @ORM\OneToMany(targetEntity="ScoreCache", mappedBy="contest")
     */
    private $scorecache;

    /**
     * @ORM\OneToMany(targetEntity="RankCache", mappedBy="contest")
     */
    private $rankcache;


    /**
     * Constructor
     */
    public function __construct()
    {
        $this->teams = new \Doctrine\Common\Collections\ArrayCollection();
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
     * Set externalid
     *
     * @param string $externalid
     *
     * @return Contest
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
     * Set name
     *
     * @param string $name
     *
     * @return Contest
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set shortname
     *
     * @param string $shortname
     *
     * @return Contest
     */
    public function setShortname($shortname)
    {
        $this->shortname = $shortname;

        return $this;
    }

    /**
     * Get shortname
     *
     * @return string
     */
    public function getShortname()
    {
        return $this->shortname;
    }

    /**
     * Set activatetime
     *
     * @param string $activatetime
     *
     * @return Contest
     */
    public function setActivatetime($activatetime)
    {
        $this->activatetime = $activatetime;

        return $this;
    }

    /**
     * Get activatetime
     *
     * @return string
     */
    public function getActivatetime()
    {
        return $this->activatetime;
    }

    /**
     * Set starttime
     *
     * @param string $starttime
     *
     * @return Contest
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
     * Set freezetime
     *
     * @param string $freezetime
     *
     * @return Contest
     */
    public function setFreezetime($freezetime)
    {
        $this->freezetime = $freezetime;

        return $this;
    }

    /**
     * Get freezetime
     *
     * @return string
     */
    public function getFreezetime()
    {
        return $this->freezetime;
    }

    /**
     * Set endtime
     *
     * @param string $endtime
     *
     * @return Contest
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
     * Set unfreezetime
     *
     * @param string $unfreezetime
     *
     * @return Contest
     */
    public function setUnfreezetime($unfreezetime)
    {
        $this->unfreezetime = $unfreezetime;

        return $this;
    }

    /**
     * Get unfreezetime
     *
     * @return string
     */
    public function getUnfreezetime()
    {
        return $this->unfreezetime;
    }

    /**
     * Set deactivatetime
     *
     * @param string $deactivatetime
     *
     * @return Contest
     */
    public function setDeactivatetime($deactivatetime)
    {
        $this->deactivatetime = $deactivatetime;

        return $this;
    }

    /**
     * Get deactivatetime
     *
     * @return string
     */
    public function getDeactivatetime()
    {
        return $this->deactivatetime;
    }

    /**
     * Set activatetimeString
     *
     * @param string $activatetimeString
     *
     * @return Contest
     */
    public function setActivatetimeString($activatetimeString)
    {
        $this->activatetime_string = $activatetimeString;

        return $this;
    }

    /**
     * Get activatetimeString
     *
     * @return string
     */
    public function getActivatetimeString()
    {
        return $this->activatetime_string;
    }

    /**
     * Set starttimeString
     *
     * @param string $starttimeString
     *
     * @return Contest
     */
    public function setStarttimeString($starttimeString)
    {
        $this->starttime_string = $starttimeString;

        return $this;
    }

    /**
     * Get starttimeString
     *
     * @return string
     */
    public function getStarttimeString()
    {
        return $this->starttime_string;
    }

    /**
     * Set freezetimeString
     *
     * @param string $freezetimeString
     *
     * @return Contest
     */
    public function setFreezetimeString($freezetimeString)
    {
        $this->freezetime_string = $freezetimeString;

        return $this;
    }

    /**
     * Get freezetimeString
     *
     * @return string
     */
    public function getFreezetimeString()
    {
        return $this->freezetime_string;
    }

    /**
     * Set endtimeString
     *
     * @param string $endtimeString
     *
     * @return Contest
     */
    public function setEndtimeString($endtimeString)
    {
        $this->endtime_string = $endtimeString;

        return $this;
    }

    /**
     * Get endtimeString
     *
     * @return string
     */
    public function getEndtimeString()
    {
        return $this->endtime_string;
    }

    /**
     * Set unfreezetimeString
     *
     * @param string $unfreezetimeString
     *
     * @return Contest
     */
    public function setUnfreezetimeString($unfreezetimeString)
    {
        $this->unfreezetime_string = $unfreezetimeString;

        return $this;
    }

    /**
     * Get unfreezetimeString
     *
     * @return string
     */
    public function getUnfreezetimeString()
    {
        return $this->unfreezetime_string;
    }

    /**
     * Set deactivatetimeString
     *
     * @param string $deactivatetimeString
     *
     * @return Contest
     */
    public function setDeactivatetimeString($deactivatetimeString)
    {
        $this->deactivatetime_string = $deactivatetimeString;

        return $this;
    }

    /**
     * Get deactivatetimeString
     *
     * @return string
     */
    public function getDeactivatetimeString()
    {
        return $this->deactivatetime_string;
    }

    /**
     * Set enabled
     *
     * @param boolean $enabled
     *
     * @return Contest
     */
    public function setEnabled($enabled)
    {
        $this->enabled = $enabled;

        return $this;
    }

    /**
     * Get enabled
     *
     * @return boolean
     */
    public function getEnabled()
    {
        return $this->enabled;
    }

    /**
     * Set processBalloons
     *
     * @param boolean $processBalloons
     *
     * @return Contest
     */
    public function setProcessBalloons($processBalloons)
    {
        $this->process_balloons = $processBalloons;

        return $this;
    }

    /**
     * Get processBalloons
     *
     * @return boolean
     */
    public function getProcessBalloons()
    {
        return $this->process_balloons;
    }

    /**
     * Set public
     *
     * @param boolean $public
     *
     * @return Contest
     */
    public function setPublic($public)
    {
        $this->public = $public;

        return $this;
    }

    /**
     * Get public
     *
     * @return boolean
     */
    public function getPublic()
    {
        return $this->public;
    }

    /**
     * Add team
     *
     * @param \DOMJudgeBundle\Entity\Team $team
     *
     * @return Contest
     */
    public function addTeam(\DOMJudgeBundle\Entity\Team $team)
    {
        $this->teams[] = $team;

        return $this;
    }

    /**
     * Remove team
     *
     * @param \DOMJudgeBundle\Entity\Team $team
     */
    public function removeTeam(\DOMJudgeBundle\Entity\Team $team)
    {
        $this->teams->removeElement($team);
    }

    /**
     * Get teams
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getTeams()
    {
        return $this->teams;
    }

    /**
     * Add contestProblem
     *
     * @param \DOMJudgeBundle\Entity\ContestProblem $contestProblem
     *
     * @return Contest
     */
    public function addContestProblem(\DOMJudgeBundle\Entity\ContestProblem $contestProblem)
    {
        $this->contest_problems[] = $contestProblem;

        return $this;
    }

    /**
     * Remove contestProblem
     *
     * @param \DOMJudgeBundle\Entity\ContestProblem $contestProblem
     */
    public function removeContestProblem(\DOMJudgeBundle\Entity\ContestProblem $contestProblem)
    {
        $this->contest_problems->removeElement($contestProblem);
    }

    /**
     * Get contestProblems
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getContestProblems()
    {
        return $this->contest_problems;
    }

    /**
     * Add problem
     *
     * @param \DOMJudgeBundle\Entity\ContestProblem $problem
     *
     * @return Contest
     */
    public function addProblem(\DOMJudgeBundle\Entity\ContestProblem $problem)
    {
        $this->problems[] = $problem;

        return $this;
    }

    /**
     * Remove problem
     *
     * @param \DOMJudgeBundle\Entity\ContestProblem $problem
     */
    public function removeProblem(\DOMJudgeBundle\Entity\ContestProblem $problem)
    {
        $this->problems->removeElement($problem);
    }

    /**
     * Get problems
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getProblems()
    {
        return $this->problems;
    }

    /**
     * Add clarification
     *
     * @param \DOMJudgeBundle\Entity\Clarification $clarification
     *
     * @return Contest
     */
    public function addClarification(\DOMJudgeBundle\Entity\Clarification $clarification)
    {
        $this->clarifications[] = $clarification;

        return $this;
    }

    /**
     * Remove clarification
     *
     * @param \DOMJudgeBundle\Entity\Clarification $clarification
     */
    public function removeClarification(\DOMJudgeBundle\Entity\Clarification $clarification)
    {
        $this->clarifications->removeElement($clarification);
    }

    /**
     * Get clarifications
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getClarifications()
    {
        return $this->clarifications;
    }

    /**
     * Add submission
     *
     * @param \DOMJudgeBundle\Entity\Submission $submission
     *
     * @return Contest
     */
    public function addSubmission(\DOMJudgeBundle\Entity\Submission $submission)
    {
        $this->submissions[] = $submission;

        return $this;
    }

    /**
     * Remove submission
     *
     * @param \DOMJudgeBundle\Entity\Submission $submission
     */
    public function removeSubmission(\DOMJudgeBundle\Entity\Submission $submission)
    {
        $this->submissions->removeElement($submission);
    }

    /**
     * Get submissions
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getSubmissions()
    {
        return $this->submissions;
    }

    /**
     * Add event
     *
     * @param \DOMJudgeBundle\Entity\Event $event
     *
     * @return Contest
     */
    public function addEvent(\DOMJudgeBundle\Entity\Event $event)
    {
        $this->events[] = $event;

        return $this;
    }

    /**
     * Remove event
     *
     * @param \DOMJudgeBundle\Entity\Event $event
     */
    public function removeEvent(\DOMJudgeBundle\Entity\Event $event)
    {
        $this->events->removeElement($event);
    }

    /**
     * Get events
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getEvents()
    {
        return $this->events;
    }

    /**
     * Add internalError
     *
     * @param \DOMJudgeBundle\Entity\InternalError $internalError
     *
     * @return Contest
     */
    public function addInternalError(\DOMJudgeBundle\Entity\InternalError $internalError)
    {
        $this->internal_errors[] = $internalError;

        return $this;
    }

    /**
     * Remove internalError
     *
     * @param \DOMJudgeBundle\Entity\InternalError $internalError
     */
    public function removeInternalError(\DOMJudgeBundle\Entity\InternalError $internalError)
    {
        $this->internal_errors->removeElement($internalError);
    }

    /**
     * Get internalErrors
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getInternalErrors()
    {
        return $this->internal_errors;
    }

    /**
     * Add scorecache
     *
     * @param \DOMJudgeBundle\Entity\ScoreCache $scorecache
     *
     * @return Contest
     */
    public function addScorecache(\DOMJudgeBundle\Entity\ScoreCache $scorecache)
    {
        $this->scorecache[] = $scorecache;

        return $this;
    }

    /**
     * Remove scorecache
     *
     * @param \DOMJudgeBundle\Entity\ScoreCache $scorecache
     */
    public function removeScorecache(\DOMJudgeBundle\Entity\ScoreCache $scorecache)
    {
        $this->scorecache->removeElement($scorecache);
    }

    /**
     * Get scorecache
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getScorecache()
    {
        return $this->scorecache;
    }

    /**
     * Add rankcache
     *
     * @param \DOMJudgeBundle\Entity\RankCache $rankcache
     *
     * @return Contest
     */
    public function addRankcache(\DOMJudgeBundle\Entity\RankCache $rankcache)
    {
        $this->rankcache[] = $rankcache;

        return $this;
    }

    /**
     * Remove rankcache
     *
     * @param \DOMJudgeBundle\Entity\RankCache $rankcache
     */
    public function removeRankcache(\DOMJudgeBundle\Entity\RankCache $rankcache)
    {
        $this->rankcache->removeElement($rankcache);
    }

    /**
     * Get rankcache
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getRankcache()
    {
        return $this->rankcache;
    }
}
