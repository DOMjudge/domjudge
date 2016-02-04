<?php

namespace DOMjudge\MainBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Role
 *
 * @ORM\Table(name="role", uniqueConstraints={@ORM\UniqueConstraint(name="role", columns={"role"})})
 * @ORM\Entity
 */
class Role
{
	/**
	 * @var integer
	 *
	 * @ORM\Column(name="roleid", type="integer")
	 * @ORM\Id
	 * @ORM\GeneratedValue(strategy="IDENTITY")
	 */
	private $roleid;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="name", type="string", length=25, nullable=false)
	 */
	private $name;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="description", type="string", length=255, nullable=false)
	 */
	private $description;

	/**
	 * @var \Doctrine\Common\Collections\Collection
	 *
	 * @ORM\ManyToMany(targetEntity="DOMjudge\MainBundle\Entity\User", mappedBy="users")
	 */
	private $users;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		$this->users = new \Doctrine\Common\Collections\ArrayCollection();
	}


	/**
	 * Get roleid
	 *
	 * @return integer
	 */
	public function getRoleid()
	{
		return $this->roleid;
	}

	/**
	 * Set name
	 *
	 * @param string $name
	 * @return Role
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
	 * Set description
	 *
	 * @param string $description
	 * @return Role
	 */
	public function setDescription($description)
	{
		$this->description = $description;

		return $this;
	}

	/**
	 * Get description
	 *
	 * @return string
	 */
	public function getDescription()
	{
		return $this->description;
	}

	/**
	 * Add users
	 *
	 * @param \DOMjudge\MainBundle\Entity\User $users
	 * @return Role
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
}
