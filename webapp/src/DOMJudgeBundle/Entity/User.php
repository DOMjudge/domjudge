<?php
namespace DOMJudgeBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Users that have access to DOMjudge
 * @ORM\Entity()
 * @ORM\Table(name="user", options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4"})
 */
class User implements UserInterface, \Serializable
{
    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="userid", options={"comment"="Unique ID"}, nullable=false)
     */
    private $userid;

    /**
     * @var string
     * @ORM\Column(type="string", name="username", length=255, options={"comment"="User login name"}, nullable=false)
     */
    private $username;

    /**
     * @var string
     * @ORM\Column(type="string", name="name", length=255, options={"comment"="Name"}, nullable=false)
     */
    private $name;

    /**
     * @var string
     * @ORM\Column(type="string", name="email", length=255, options={"comment"="Email address"}, nullable=true)
     */
    private $email;

    /**
     * @var double
     * @ORM\Column(type="decimal", precision=32, scale=9, name="last_login", options={"comment"="Time of last successful login", "unsigned"=true}, nullable=true)
     */
    private $last_login;

    /**
     * @var string
     * @ORM\Column(type="string", name="last_ip_address", length=255, options={"comment"="Last IP address of successful login"}, nullable=true)
     */
    private $last_ip_address;

    /**
     * @var string
     * @ORM\Column(type="string", name="password", length=255, options={"comment"="Password hash"}, nullable=true)
     */
    private $password;

    /**
     * @var string
     * @ORM\Column(type="string", name="ip_address", length=255, options={"comment"="IP Address used to autologin"}, nullable=true)
     */
    private $ipaddress;

    /**
     * @var boolean
     * @ORM\Column(type="boolean", name="enabled", options={"comment"="Whether the team is visible and operational"}, nullable=true)
     */
    private $enabled = true;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", name="teamid", options={"comment"="Team associated with"}, nullable=true)
     */
    private $teamid;

    /**
     * @ORM\ManyToOne(targetEntity="Team", inversedBy="users")
     * @ORM\JoinColumn(name="teamid", referencedColumnName="teamid")
     */
    private $team;

    /**
      * @ORM\ManyToMany(targetEntity="Role", inversedBy="users")
      * @ORM\JoinTable(name="userrole",
      *                joinColumns={@ORM\JoinColumn(name="userid", referencedColumnName="userid")},
      *                inverseJoinColumns={@ORM\JoinColumn(name="roleid", referencedColumnName="roleid")}
      *               )
      */
    private $roles;


    public function getSalt()
    {
        return null;
    }
    public function eraseCredentials()
    {
    }
    public function serialize()
    {
        return serialize(array(
            $this->userid,
            $this->username,
            $this->password,
        ));
    }
    public function unserialize($serialized)
    {
        list(
            $this->userid,
            $this->username,
            $this->password
        ) = unserialize($serialized);
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
     *
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
     *
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
     *
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
     *
     * @return User
     */
    public function setLastLogin($lastLogin)
    {
        $this->last_login = $lastLogin;

        return $this;
    }

    /**
     * Get lastLogin
     *
     * @return string
     */
    public function getLastLogin()
    {
        return $this->last_login;
    }

    /**
     * Set lastIpAddress
     *
     * @param string $lastIpAddress
     *
     * @return User
     */
    public function setLastIpAddress($lastIpAddress)
    {
        $this->last_ip_address = $lastIpAddress;

        return $this;
    }

    /**
     * Get lastIpAddress
     *
     * @return string
     */
    public function getLastIpAddress()
    {
        return $this->last_ip_address;
    }

    /**
     * Set password
     *
     * @param string $password
     *
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
     * Set ipaddress
     *
     * @param string $ipaddress
     *
     * @return User
     */
    public function setIpaddress($ipaddress)
    {
        $this->ipaddress = $ipaddress;

        return $this;
    }

    /**
     * Get ipaddress
     *
     * @return string
     */
    public function getIpaddress()
    {
        return $this->ipaddress;
    }

    /**
     * Set enabled
     *
     * @param boolean $enabled
     *
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
     * Set teamid
     *
     * @param integer $teamid
     *
     * @return User
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
     * Set team
     *
     * @param \DOMJudgeBundle\Entity\Team $team
     *
     * @return User
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
        $this->roles = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * Add role
     *
     * @param \DOMJudgeBundle\Entity\Role $role
     *
     * @return User
     */
    public function addRole(\DOMJudgeBundle\Entity\Role $role)
    {
        $this->roles[] = $role;

        return $this;
    }

    /**
     * Remove role
     *
     * @param \DOMJudgeBundle\Entity\Role $role
     */
    public function removeRole(\DOMJudgeBundle\Entity\Role $role)
    {
        $this->roles->removeElement($role);
    }

    /**
     * Get roles
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getRoles()
    {
        return $this->roles->toArray();
    }
}
