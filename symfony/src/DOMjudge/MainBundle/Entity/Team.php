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

}
