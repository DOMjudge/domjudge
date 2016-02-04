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

}
