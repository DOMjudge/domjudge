<?php

namespace DOMjudge\MainBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * User
 *
 * @ORM\Table(name="user", uniqueConstraints={@ORM\UniqueConstraint(name="username", columns={"username"})}, indexes={@ORM\Index(name="teamid", columns={"teamid"})})
 * @ORM\Entity
 */
class User
{
    /**
     * @var integer
     *
     * @ORM\Column(name="userid", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $userid;

    /**
     * @var string
     *
     * @ORM\Column(name="username", type="string", length=255, nullable=false)
     */
    private $username;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=255, nullable=false)
     */
    private $name;

    /**
     * @var string
     *
     * @ORM\Column(name="email", type="string", length=255, nullable=true)
     */
    private $email;

    /**
     * @var string
     *
     * @ORM\Column(name="last_login", type="decimal", precision=32, scale=9, nullable=true)
     */
    private $lastLogin;

    /**
     * @var string
     *
     * @ORM\Column(name="last_ip_address", type="string", length=255, nullable=true)
     */
    private $lastIpAddress;

    /**
     * @var string
     *
     * @ORM\Column(name="password", type="string", length=32, nullable=true)
     */
    private $password;

    /**
     * @var string
     *
     * @ORM\Column(name="ip_address", type="string", length=255, nullable=true)
     */
    private $ipAddress;

    /**
     * @var boolean
     *
     * @ORM\Column(name="enabled", type="boolean", nullable=false)
     */
    private $enabled;

    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\OneToMany(targetEntity="DOMjudge\MainBundle\Entity\Judging", mappedBy="startedByUser")
     */
    private $startedRejudgings;

    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\OneToMany(targetEntity="DOMjudge\MainBundle\Entity\Judging", mappedBy="finishedByUser")
     */
    private $finishedRejudgings;

    /**
     * @var \DOMjudge\MainBundle\Entity\Team
     *
     * @ORM\ManyToOne(targetEntity="DOMjudge\MainBundle\Entity\Team")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="teamid", referencedColumnName="teamid")
     * })
     */
    private $team;

    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\ManyToMany(targetEntity="DOMjudge\MainBundle\Entity\Role", inversedBy="users")
     * @ORM\JoinTable(name="userrole",
     *   joinColumns={
     *     @ORM\JoinColumn(name="userid", referencedColumnName="userid")
     *   },
     *   inverseJoinColumns={
     *     @ORM\JoinColumn(name="roleid", referencedColumnName="roleid")
     *   }
     * )
     */
    private $userRoles;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->startedRejudgings = new \Doctrine\Common\Collections\ArrayCollection();
        $this->finishedRejudgings = new \Doctrine\Common\Collections\ArrayCollection();
        $this->userRoles = new \Doctrine\Common\Collections\ArrayCollection();
    }

}
