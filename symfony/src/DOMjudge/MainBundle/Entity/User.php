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


	/**
	 * Get userid
	 *
	 * @return integer
	 */
	public function getUserid()
	{
		return $this->userid;
	}

	/**
	 * Set username
	 *
	 * @param string $username
	 * @return User
	 */
	public function setUsername($username)
	{
		$this->username = $username;

		return $this;
	}

	/**
	 * Get username
	 *
	 * @return string
	 */
	public function getUsername()
	{
		return $this->username;
	}

	/**
	 * Set name
	 *
	 * @param string $name
	 * @return User
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
	 * Set email
	 *
	 * @param string $email
	 * @return User
	 */
	public function setEmail($email)
	{
		$this->email = $email;

		return $this;
	}

	/**
	 * Get email
	 *
	 * @return string
	 */
	public function getEmail()
	{
		return $this->email;
	}

	/**
	 * Set lastLogin
	 *
	 * @param string $lastLogin
	 * @return User
	 */
	public function setLastLogin($lastLogin)
	{
		$this->lastLogin = $lastLogin;

		return $this;
	}

	/**
	 * Get lastLogin
	 *
	 * @return string
	 */
	public function getLastLogin()
	{
		return $this->lastLogin;
	}

	/**
	 * Set lastIpAddress
	 *
	 * @param string $lastIpAddress
	 * @return User
	 */
	public function setLastIpAddress($lastIpAddress)
	{
		$this->lastIpAddress = $lastIpAddress;

		return $this;
	}

	/**
	 * Get lastIpAddress
	 *
	 * @return string
	 */
	public function getLastIpAddress()
	{
		return $this->lastIpAddress;
	}

	/**
	 * Set password
	 *
	 * @param string $password
	 * @return User
	 */
	public function setPassword($password)
	{
		$this->password = $password;

		return $this;
	}

	/**
	 * Get password
	 *
	 * @return string
	 */
	public function getPassword()
	{
		return $this->password;
	}

	/**
	 * Set ipAddress
	 *
	 * @param string $ipAddress
	 * @return User
	 */
	public function setIpAddress($ipAddress)
	{
		$this->ipAddress = $ipAddress;

		return $this;
	}

	/**
	 * Get ipAddress
	 *
	 * @return string
	 */
	public function getIpAddress()
	{
		return $this->ipAddress;
	}

	/**
	 * Set enabled
	 *
	 * @param boolean $enabled
	 * @return User
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
	 * Add startedRejudgings
	 *
	 * @param \DOMjudge\MainBundle\Entity\Judging $startedRejudgings
	 * @return User
	 */
	public function addStartedRejudging(\DOMjudge\MainBundle\Entity\Judging $startedRejudgings)
	{
		$this->startedRejudgings[] = $startedRejudgings;

		return $this;
	}

	/**
	 * Remove startedRejudgings
	 *
	 * @param \DOMjudge\MainBundle\Entity\Judging $startedRejudgings
	 */
	public function removeStartedRejudging(\DOMjudge\MainBundle\Entity\Judging $startedRejudgings)
	{
		$this->startedRejudgings->removeElement($startedRejudgings);
	}

	/**
	 * Get startedRejudgings
	 *
	 * @return \Doctrine\Common\Collections\Collection
	 */
	public function getStartedRejudgings()
	{
		return $this->startedRejudgings;
	}

	/**
	 * Add finishedRejudgings
	 *
	 * @param \DOMjudge\MainBundle\Entity\Judging $finishedRejudgings
	 * @return User
	 */
	public function addFinishedRejudging(\DOMjudge\MainBundle\Entity\Judging $finishedRejudgings)
	{
		$this->finishedRejudgings[] = $finishedRejudgings;

		return $this;
	}

	/**
	 * Remove finishedRejudgings
	 *
	 * @param \DOMjudge\MainBundle\Entity\Judging $finishedRejudgings
	 */
	public function removeFinishedRejudging(\DOMjudge\MainBundle\Entity\Judging $finishedRejudgings)
	{
		$this->finishedRejudgings->removeElement($finishedRejudgings);
	}

	/**
	 * Get finishedRejudgings
	 *
	 * @return \Doctrine\Common\Collections\Collection
	 */
	public function getFinishedRejudgings()
	{
		return $this->finishedRejudgings;
	}

	/**
	 * Set team
	 *
	 * @param \DOMjudge\MainBundle\Entity\Team $team
	 * @return User
	 */
	public function setTeam(\DOMjudge\MainBundle\Entity\Team $team = null)
	{
		$this->team = $team;

		return $this;
	}

	/**
	 * Get team
	 *
	 * @return \DOMjudge\MainBundle\Entity\Team
	 */
	public function getTeam()
	{
		return $this->team;
	}

	/**
	 * Add userRoles
	 *
	 * @param \DOMjudge\MainBundle\Entity\Role $userRoles
	 * @return User
	 */
	public function addUserRole(\DOMjudge\MainBundle\Entity\Role $userRoles)
	{
		$this->userRoles[] = $userRoles;

		return $this;
	}

	/**
	 * Remove userRoles
	 *
	 * @param \DOMjudge\MainBundle\Entity\Role $userRoles
	 */
	public function removeUserRole(\DOMjudge\MainBundle\Entity\Role $userRoles)
	{
		$this->userRoles->removeElement($userRoles);
	}

	/**
	 * Get userRoles
	 *
	 * @return \Doctrine\Common\Collections\Collection
	 */
	public function getUserRoles()
	{
		return $this->userRoles;
	}
}
