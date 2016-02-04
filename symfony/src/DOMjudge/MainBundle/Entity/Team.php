<?php

namespace DOMjudge\MainBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Team
 *
 * @ORM\Table(name="team", uniqueConstraints={@ORM\UniqueConstraint(name="externalid", columns={"externalid"})}, indexes={@ORM\Index(name="affilid", columns={"affilid"}), @ORM\Index(name="categoryid", columns={"categoryid"})})
 * @ORM\Entity
 */
class Team
{
    /**
     * @var integer
     *
     * @ORM\Column(name="teamid", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $teamid;

    /**
     * @var string
     *
     * @ORM\Column(name="externalid", type="string", length=255, nullable=true)
     */
    private $externalId;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=255, nullable=false)
     */
    private $name;

    /**
     * @var boolean
     *
     * @ORM\Column(name="enabled", type="boolean", nullable=false)
     */
    private $enabled;

    /**
     * @var string
     *
     * @ORM\Column(name="members", type="text", nullable=true)
     */
    private $members;

    /**
     * @var string
     *
     * @ORM\Column(name="room", type="string", length=15, nullable=true)
     */
    private $room;

    /**
     * @var string
     *
     * @ORM\Column(name="comments", type="text", nullable=true)
     */
    private $comments;

    /**
     * @var string
     *
     * @ORM\Column(name="judging_last_started", type="decimal", precision=32, scale=9, nullable=true)
     */
    private $judgingLastStarted;

    /**
     * @var string
     *
     * @ORM\Column(name="teampage_first_visited", type="decimal", precision=32, scale=9, nullable=true)
     */
    private $teampageFirstVisited;

    /**
     * @var string
     *
     * @ORM\Column(name="hostname", type="string", length=255, nullable=true)
     */
    private $hostName;

    /**
     * @var integer
     *
     * @ORM\Column(name="penalty", type="integer", nullable=false)
     */
    private $penalty;

    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\OneToMany(targetEntity="DOMjudge\MainBundle\Entity\Submission", mappedBy="team")
     */
    private $submissions;

    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\OneToMany(targetEntity="DOMjudge\MainBundle\Entity\User", mappedBy="team")
     */
    private $users;

    /**
     * @var \DOMjudge\MainBundle\Entity\TeamCategory
     *
     * @ORM\ManyToOne(targetEntity="DOMjudge\MainBundle\Entity\TeamCategory")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="categoryid", referencedColumnName="categoryid")
     * })
     */
    private $category;

    /**
     * @var \DOMjudge\MainBundle\Entity\TeamAffiliation
     *
     * @ORM\ManyToOne(targetEntity="DOMjudge\MainBundle\Entity\TeamAffiliation")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="affilid", referencedColumnName="affilid")
     * })
     */
    private $affiliation;

    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\ManyToMany(targetEntity="DOMjudge\MainBundle\Entity\Contest", inversedBy="teams")
     * @ORM\JoinTable(name="contestteam",
     *   joinColumns={
     *     @ORM\JoinColumn(name="teamid", referencedColumnName="teamid")
     *   },
     *   inverseJoinColumns={
     *     @ORM\JoinColumn(name="cid", referencedColumnName="cid")
     *   }
     * )
     */
    private $contests;

    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\ManyToMany(targetEntity="DOMjudge\MainBundle\Entity\Clarification", inversedBy="unreadTeams")
     * @ORM\JoinTable(name="team_unread",
     *   joinColumns={
     *     @ORM\JoinColumn(name="teamid", referencedColumnName="teamid")
     *   },
     *   inverseJoinColumns={
     *     @ORM\JoinColumn(name="mesgid", referencedColumnName="clarid")
     *   }
     * )
     */
    private $unreadClarifications;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->submissions = new \Doctrine\Common\Collections\ArrayCollection();
        $this->users = new \Doctrine\Common\Collections\ArrayCollection();
        $this->contests = new \Doctrine\Common\Collections\ArrayCollection();
        $this->unreadClarifications = new \Doctrine\Common\Collections\ArrayCollection();
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
     * Set externalId
     *
     * @param string $externalId
     * @return Team
     */
    public function setExternalId($externalId)
    {
        $this->externalId = $externalId;

        return $this;
    }

    /**
     * Get externalId
     *
     * @return string 
     */
    public function getExternalId()
    {
        return $this->externalId;
    }

    /**
     * Set name
     *
     * @param string $name
     * @return Team
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
     * Set enabled
     *
     * @param boolean $enabled
     * @return Team
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
     * Set members
     *
     * @param string $members
     * @return Team
     */
    public function setMembers($members)
    {
        $this->members = $members;

        return $this;
    }

    /**
     * Get members
     *
     * @return string 
     */
    public function getMembers()
    {
        return $this->members;
    }

    /**
     * Set room
     *
     * @param string $room
     * @return Team
     */
    public function setRoom($room)
    {
        $this->room = $room;

        return $this;
    }

    /**
     * Get room
     *
     * @return string 
     */
    public function getRoom()
    {
        return $this->room;
    }

    /**
     * Set comments
     *
     * @param string $comments
     * @return Team
     */
    public function setComments($comments)
    {
        $this->comments = $comments;

        return $this;
    }

    /**
     * Get comments
     *
     * @return string 
     */
    public function getComments()
    {
        return $this->comments;
    }

    /**
     * Set judgingLastStarted
     *
     * @param string $judgingLastStarted
     * @return Team
     */
    public function setJudgingLastStarted($judgingLastStarted)
    {
        $this->judgingLastStarted = $judgingLastStarted;

        return $this;
    }

    /**
     * Get judgingLastStarted
     *
     * @return string 
     */
    public function getJudgingLastStarted()
    {
        return $this->judgingLastStarted;
    }

    /**
     * Set teampageFirstVisited
     *
     * @param string $teampageFirstVisited
     * @return Team
     */
    public function setTeampageFirstVisited($teampageFirstVisited)
    {
        $this->teampageFirstVisited = $teampageFirstVisited;

        return $this;
    }

    /**
     * Get teampageFirstVisited
     *
     * @return string 
     */
    public function getTeampageFirstVisited()
    {
        return $this->teampageFirstVisited;
    }

    /**
     * Set hostName
     *
     * @param string $hostName
     * @return Team
     */
    public function setHostName($hostName)
    {
        $this->hostName = $hostName;

        return $this;
    }

    /**
     * Get hostName
     *
     * @return string 
     */
    public function getHostName()
    {
        return $this->hostName;
    }

    /**
     * Set penalty
     *
     * @param integer $penalty
     * @return Team
     */
    public function setPenalty($penalty)
    {
        $this->penalty = $penalty;

        return $this;
    }

    /**
     * Get penalty
     *
     * @return integer 
     */
    public function getPenalty()
    {
        return $this->penalty;
    }

    /**
     * Add submissions
     *
     * @param \DOMjudge\MainBundle\Entity\Submission $submissions
     * @return Team
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
     * Add users
     *
     * @param \DOMjudge\MainBundle\Entity\User $users
     * @return Team
     */
    public function addUser(\DOMjudge\MainBundle\Entity\User $users)
    {
        $this->users[] = $users;

        return $this;
    }

    /**
     * Remove users
     *
     * @param \DOMjudge\MainBundle\Entity\User $users
     */
    public function removeUser(\DOMjudge\MainBundle\Entity\User $users)
    {
        $this->users->removeElement($users);
    }

    /**
     * Get users
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getUsers()
    {
        return $this->users;
    }

    /**
     * Set category
     *
     * @param \DOMjudge\MainBundle\Entity\TeamCategory $category
     * @return Team
     */
    public function setCategory(\DOMjudge\MainBundle\Entity\TeamCategory $category = null)
    {
        $this->category = $category;

        return $this;
    }

    /**
     * Get category
     *
     * @return \DOMjudge\MainBundle\Entity\TeamCategory 
     */
    public function getCategory()
    {
        return $this->category;
    }

    /**
     * Set affiliation
     *
     * @param \DOMjudge\MainBundle\Entity\TeamAffiliation $affiliation
     * @return Team
     */
    public function setAffiliation(\DOMjudge\MainBundle\Entity\TeamAffiliation $affiliation = null)
    {
        $this->affiliation = $affiliation;

        return $this;
    }

    /**
     * Get affiliation
     *
     * @return \DOMjudge\MainBundle\Entity\TeamAffiliation 
     */
    public function getAffiliation()
    {
        return $this->affiliation;
    }

    /**
     * Add contests
     *
     * @param \DOMjudge\MainBundle\Entity\Contest $contests
     * @return Team
     */
    public function addContest(\DOMjudge\MainBundle\Entity\Contest $contests)
    {
        $this->contests[] = $contests;

        return $this;
    }

    /**
     * Remove contests
     *
     * @param \DOMjudge\MainBundle\Entity\Contest $contests
     */
    public function removeContest(\DOMjudge\MainBundle\Entity\Contest $contests)
    {
        $this->contests->removeElement($contests);
    }

    /**
     * Get contests
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getContests()
    {
        return $this->contests;
    }

    /**
     * Add unreadClarifications
     *
     * @param \DOMjudge\MainBundle\Entity\Clarification $unreadClarifications
     * @return Team
     */
    public function addUnreadClarification(\DOMjudge\MainBundle\Entity\Clarification $unreadClarifications)
    {
        $this->unreadClarifications[] = $unreadClarifications;

        return $this;
    }

    /**
     * Remove unreadClarifications
     *
     * @param \DOMjudge\MainBundle\Entity\Clarification $unreadClarifications
     */
    public function removeUnreadClarification(\DOMjudge\MainBundle\Entity\Clarification $unreadClarifications)
    {
        $this->unreadClarifications->removeElement($unreadClarifications);
    }

    /**
     * Get unreadClarifications
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getUnreadClarifications()
    {
        return $this->unreadClarifications;
    }
}
