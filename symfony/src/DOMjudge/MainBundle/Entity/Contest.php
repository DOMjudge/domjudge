<?php

namespace DOMjudge\MainBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Contest
 *
 * @ORM\Table(name="contest", uniqueConstraints={@ORM\UniqueConstraint(name="shortname", columns={"shortname"})}, indexes={@ORM\Index(name="cid", columns={"cid", "enabled"})})
 * @ORM\Entity
 */
class Contest
{
    /**
     * @var integer
     *
     * @ORM\Column(name="cid", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $cid;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=255, nullable=false)
     */
    private $name;

    /**
     * @var string
     *
     * @ORM\Column(name="shortname", type="string", length=255, nullable=false)
     */
    private $shortName;

    /**
     * @var string
     *
     * @ORM\Column(name="activatetime", type="decimal", precision=32, scale=9, nullable=false)
     */
    private $activateTime;

    /**
     * @var string
     *
     * @ORM\Column(name="starttime", type="decimal", precision=32, scale=9, nullable=false)
     */
    private $startTime;

    /**
     * @var string
     *
     * @ORM\Column(name="freezetime", type="decimal", precision=32, scale=9, nullable=true)
     */
    private $freezeTime;

    /**
     * @var string
     *
     * @ORM\Column(name="endtime", type="decimal", precision=32, scale=9, nullable=false)
     */
    private $endTime;

    /**
     * @var string
     *
     * @ORM\Column(name="unfreezetime", type="decimal", precision=32, scale=9, nullable=true)
     */
    private $unfreezeTime;

    /**
     * @var string
     *
     * @ORM\Column(name="deactivatetime", type="decimal", precision=32, scale=9, nullable=true)
     */
    private $deactivateTime;

    /**
     * @var string
     *
     * @ORM\Column(name="activatetime_string", type="string", length=64, nullable=false)
     */
    private $activateTimeString;

    /**
     * @var string
     *
     * @ORM\Column(name="starttime_string", type="string", length=64, nullable=false)
     */
    private $startTimeString;

    /**
     * @var string
     *
     * @ORM\Column(name="freezetime_string", type="string", length=64, nullable=true)
     */
    private $freezeTimeString;

    /**
     * @var string
     *
     * @ORM\Column(name="endtime_string", type="string", length=64, nullable=false)
     */
    private $endTimeString;

    /**
     * @var string
     *
     * @ORM\Column(name="unfreezetime_string", type="string", length=64, nullable=true)
     */
    private $unfreezeTimeString;

    /**
     * @var string
     *
     * @ORM\Column(name="deactivatetime_string", type="string", length=64, nullable=true)
     */
    private $deactivateTimeString;

    /**
     * @var boolean
     *
     * @ORM\Column(name="enabled", type="boolean", nullable=false)
     */
    private $enabled;

    /**
     * @var boolean
     *
     * @ORM\Column(name="process_balloons", type="boolean", nullable=true)
     */
    private $processBalloons;

    /**
     * @var boolean
     *
     * @ORM\Column(name="public", type="boolean", nullable=true)
     */
    private $public;

    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\OneToMany(targetEntity="DOMjudge\MainBundle\Entity\AuditLog", mappedBy="contest")
     */
    private $auditlogs;

    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\OneToMany(targetEntity="DOMjudge\MainBundle\Entity\Clarification", mappedBy="contest")
     */
    private $clarifications;

    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\OneToMany(targetEntity="DOMjudge\MainBundle\Entity\Event", mappedBy="contest")
     */
    private $events;

    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\OneToMany(targetEntity="DOMjudge\MainBundle\Entity\Judging", mappedBy="contest")
     */
    private $judgings;

    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\OneToMany(targetEntity="DOMjudge\MainBundle\Entity\Submission", mappedBy="contest")
     */
    private $submissions;

    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\ManyToMany(targetEntity="DOMjudge\MainBundle\Entity\Problem", inversedBy="contests")
     * @ORM\JoinTable(name="contestproblem",
     *   joinColumns={
     *     @ORM\JoinColumn(name="cid", referencedColumnName="cid")
     *   },
     *   inverseJoinColumns={
     *     @ORM\JoinColumn(name="probid", referencedColumnName="probid")
     *   }
     * )
     */
    private $problems;

    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\ManyToMany(targetEntity="DOMjudge\MainBundle\Entity\Team", mappedBy="contests")
     */
    private $teams;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->auditlogs = new \Doctrine\Common\Collections\ArrayCollection();
        $this->clarifications = new \Doctrine\Common\Collections\ArrayCollection();
        $this->events = new \Doctrine\Common\Collections\ArrayCollection();
        $this->judgings = new \Doctrine\Common\Collections\ArrayCollection();
        $this->submissions = new \Doctrine\Common\Collections\ArrayCollection();
        $this->problems = new \Doctrine\Common\Collections\ArrayCollection();
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
     * Set name
     *
     * @param string $name
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
     * Set shortName
     *
     * @param string $shortName
     * @return Contest
     */
    public function setShortName($shortName)
    {
        $this->shortName = $shortName;

        return $this;
    }

    /**
     * Get shortName
     *
     * @return string 
     */
    public function getShortName()
    {
        return $this->shortName;
    }

    /**
     * Set activateTime
     *
     * @param string $activateTime
     * @return Contest
     */
    public function setActivateTime($activateTime)
    {
        $this->activateTime = $activateTime;

        return $this;
    }

    /**
     * Get activateTime
     *
     * @return string 
     */
    public function getActivateTime()
    {
        return $this->activateTime;
    }

    /**
     * Set startTime
     *
     * @param string $startTime
     * @return Contest
     */
    public function setStartTime($startTime)
    {
        $this->startTime = $startTime;

        return $this;
    }

    /**
     * Get startTime
     *
     * @return string 
     */
    public function getStartTime()
    {
        return $this->startTime;
    }

    /**
     * Set freezeTime
     *
     * @param string $freezeTime
     * @return Contest
     */
    public function setFreezeTime($freezeTime)
    {
        $this->freezeTime = $freezeTime;

        return $this;
    }

    /**
     * Get freezeTime
     *
     * @return string 
     */
    public function getFreezeTime()
    {
        return $this->freezeTime;
    }

    /**
     * Set endTime
     *
     * @param string $endTime
     * @return Contest
     */
    public function setEndTime($endTime)
    {
        $this->endTime = $endTime;

        return $this;
    }

    /**
     * Get endTime
     *
     * @return string 
     */
    public function getEndTime()
    {
        return $this->endTime;
    }

    /**
     * Set unfreezeTime
     *
     * @param string $unfreezeTime
     * @return Contest
     */
    public function setUnfreezeTime($unfreezeTime)
    {
        $this->unfreezeTime = $unfreezeTime;

        return $this;
    }

    /**
     * Get unfreezeTime
     *
     * @return string 
     */
    public function getUnfreezeTime()
    {
        return $this->unfreezeTime;
    }

    /**
     * Set deactivateTime
     *
     * @param string $deactivateTime
     * @return Contest
     */
    public function setDeactivateTime($deactivateTime)
    {
        $this->deactivateTime = $deactivateTime;

        return $this;
    }

    /**
     * Get deactivateTime
     *
     * @return string 
     */
    public function getDeactivateTime()
    {
        return $this->deactivateTime;
    }

    /**
     * Set activateTimeString
     *
     * @param string $activateTimeString
     * @return Contest
     */
    public function setActivateTimeString($activateTimeString)
    {
        $this->activateTimeString = $activateTimeString;

        return $this;
    }

    /**
     * Get activateTimeString
     *
     * @return string 
     */
    public function getActivateTimeString()
    {
        return $this->activateTimeString;
    }

    /**
     * Set startTimeString
     *
     * @param string $startTimeString
     * @return Contest
     */
    public function setStartTimeString($startTimeString)
    {
        $this->startTimeString = $startTimeString;

        return $this;
    }

    /**
     * Get startTimeString
     *
     * @return string 
     */
    public function getStartTimeString()
    {
        return $this->startTimeString;
    }

    /**
     * Set freezeTimeString
     *
     * @param string $freezeTimeString
     * @return Contest
     */
    public function setFreezeTimeString($freezeTimeString)
    {
        $this->freezeTimeString = $freezeTimeString;

        return $this;
    }

    /**
     * Get freezeTimeString
     *
     * @return string 
     */
    public function getFreezeTimeString()
    {
        return $this->freezeTimeString;
    }

    /**
     * Set endTimeString
     *
     * @param string $endTimeString
     * @return Contest
     */
    public function setEndTimeString($endTimeString)
    {
        $this->endTimeString = $endTimeString;

        return $this;
    }

    /**
     * Get endTimeString
     *
     * @return string 
     */
    public function getEndTimeString()
    {
        return $this->endTimeString;
    }

    /**
     * Set unfreezeTimeString
     *
     * @param string $unfreezeTimeString
     * @return Contest
     */
    public function setUnfreezeTimeString($unfreezeTimeString)
    {
        $this->unfreezeTimeString = $unfreezeTimeString;

        return $this;
    }

    /**
     * Get unfreezeTimeString
     *
     * @return string 
     */
    public function getUnfreezeTimeString()
    {
        return $this->unfreezeTimeString;
    }

    /**
     * Set deactivateTimeString
     *
     * @param string $deactivateTimeString
     * @return Contest
     */
    public function setDeactivateTimeString($deactivateTimeString)
    {
        $this->deactivateTimeString = $deactivateTimeString;

        return $this;
    }

    /**
     * Get deactivateTimeString
     *
     * @return string 
     */
    public function getDeactivateTimeString()
    {
        return $this->deactivateTimeString;
    }

    /**
     * Set enabled
     *
     * @param boolean $enabled
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
     * @return Contest
     */
    public function setProcessBalloons($processBalloons)
    {
        $this->processBalloons = $processBalloons;

        return $this;
    }

    /**
     * Get processBalloons
     *
     * @return boolean 
     */
    public function getProcessBalloons()
    {
        return $this->processBalloons;
    }

    /**
     * Set public
     *
     * @param boolean $public
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
     * Add auditlogs
     *
     * @param \DOMjudge\MainBundle\Entity\AuditLog $auditlogs
     * @return Contest
     */
    public function addAuditlog(\DOMjudge\MainBundle\Entity\AuditLog $auditlogs)
    {
        $this->auditlogs[] = $auditlogs;

        return $this;
    }

    /**
     * Remove auditlogs
     *
     * @param \DOMjudge\MainBundle\Entity\AuditLog $auditlogs
     */
    public function removeAuditlog(\DOMjudge\MainBundle\Entity\AuditLog $auditlogs)
    {
        $this->auditlogs->removeElement($auditlogs);
    }

    /**
     * Get auditlogs
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getAuditlogs()
    {
        return $this->auditlogs;
    }

    /**
     * Add clarifications
     *
     * @param \DOMjudge\MainBundle\Entity\Clarification $clarifications
     * @return Contest
     */
    public function addClarification(\DOMjudge\MainBundle\Entity\Clarification $clarifications)
    {
        $this->clarifications[] = $clarifications;

        return $this;
    }

    /**
     * Remove clarifications
     *
     * @param \DOMjudge\MainBundle\Entity\Clarification $clarifications
     */
    public function removeClarification(\DOMjudge\MainBundle\Entity\Clarification $clarifications)
    {
        $this->clarifications->removeElement($clarifications);
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
     * Add events
     *
     * @param \DOMjudge\MainBundle\Entity\Event $events
     * @return Contest
     */
    public function addEvent(\DOMjudge\MainBundle\Entity\Event $events)
    {
        $this->events[] = $events;

        return $this;
    }

    /**
     * Remove events
     *
     * @param \DOMjudge\MainBundle\Entity\Event $events
     */
    public function removeEvent(\DOMjudge\MainBundle\Entity\Event $events)
    {
        $this->events->removeElement($events);
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
     * Add judgings
     *
     * @param \DOMjudge\MainBundle\Entity\Judging $judgings
     * @return Contest
     */
    public function addJudging(\DOMjudge\MainBundle\Entity\Judging $judgings)
    {
        $this->judgings[] = $judgings;

        return $this;
    }

    /**
     * Remove judgings
     *
     * @param \DOMjudge\MainBundle\Entity\Judging $judgings
     */
    public function removeJudging(\DOMjudge\MainBundle\Entity\Judging $judgings)
    {
        $this->judgings->removeElement($judgings);
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
     * Add submissions
     *
     * @param \DOMjudge\MainBundle\Entity\Submission $submissions
     * @return Contest
     */
    public function addSubmission(\DOMjudge\MainBundle\Entity\Submission $submissions)
    {
        $this->submissions[] = $submissions;

        return $this;
    }

    /**
     * Remove submissions
     *
     * @param \DOMjudge\MainBundle\Entity\Submission $submissions
     */
    public function removeSubmission(\DOMjudge\MainBundle\Entity\Submission $submissions)
    {
        $this->submissions->removeElement($submissions);
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
     * Add problems
     *
     * @param \DOMjudge\MainBundle\Entity\Problem $problems
     * @return Contest
     */
    public function addProblem(\DOMjudge\MainBundle\Entity\Problem $problems)
    {
        $this->problems[] = $problems;

        return $this;
    }

    /**
     * Remove problems
     *
     * @param \DOMjudge\MainBundle\Entity\Problem $problems
     */
    public function removeProblem(\DOMjudge\MainBundle\Entity\Problem $problems)
    {
        $this->problems->removeElement($problems);
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
     * Add teams
     *
     * @param \DOMjudge\MainBundle\Entity\Team $teams
     * @return Contest
     */
    public function addTeam(\DOMjudge\MainBundle\Entity\Team $teams)
    {
        $this->teams[] = $teams;

        return $this;
    }

    /**
     * Remove teams
     *
     * @param \DOMjudge\MainBundle\Entity\Team $teams
     */
    public function removeTeam(\DOMjudge\MainBundle\Entity\Team $teams)
    {
        $this->teams->removeElement($teams);
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
}
