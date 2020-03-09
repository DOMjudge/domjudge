<?php declare(strict_types=1);
namespace App\Entity;

use App\Utils\Utils;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Exception;
use JMS\Serializer\Annotation as Serializer;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Users that have access to DOMjudge
 * @ORM\Entity()
 * @ORM\Table(
 *     name="user",
 *     options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4", "comment"="Users that have access to DOMjudge"},
 *     indexes={@ORM\Index(name="teamid", columns={"teamid"})},
 *     uniqueConstraints={@ORM\UniqueConstraint(name="username", columns={"username"}, options={"lengths":{"190"}})})
 * @UniqueEntity("username", message="This username is already in use.")
 */
class User implements UserInterface, EquatableInterface, \Serializable
{
    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="userid", length=4,
     *     options={"comment"="User ID","unsigned"=true}, nullable=false)
     * @Serializer\SerializedName("id")
     * @Serializer\Type("string")
     */
    private $userid;

    /**
     * @var string
     * @ORM\Column(type="string", name="username", length=255,
     *     options={"comment"="User login name"}, nullable=false)
     * @Assert\Regex("/^[a-z0-9@._-]+$/i", message="Only alphanumeric characters and _-@. are allowed")
     */
    private $username;

    /**
     * @var string
     * @ORM\Column(type="string", name="name", length=255,
     *     options={"comment"="Name"}, nullable=false)
     */
    private $name;

    /**
     * @var string
     * @ORM\Column(type="string", name="email", length=255,
     *     options={"comment"="Email address","default"="NULL"}, nullable=true)
     * @Assert\Email()
     */
    private $email;

    /**
     * @var double
     * @ORM\Column(type="decimal", precision=32, scale=9, name="last_login",
     *     options={"comment"="Time of last successful login", "unsigned"=true,
     *              "default"="NULL"},
     *     nullable=true)
     * @Serializer\Exclude()
     */
    private $last_login;

    /**
     * @var double
     * @ORM\Column(type="decimal", precision=32, scale=9, name="first_login",
     *     options={"comment"="Time of first login", "unsigned"=true,
     *              "default"="NULL"},
     *     nullable=true)
     * @Serializer\Exclude()
     */
    private $first_login;

    /**
     * @var string
     * @ORM\Column(type="string", name="last_ip_address", length=255,
     *     options={"comment"="Last IP address of successful login",
     *              "default"="NULL"},
     *     nullable=true)
     * @Serializer\SerializedName("last_ip")
     */
    private $last_ip_address;

    /**
     * @var string
     * @ORM\Column(type="string", name="password", length=255,
     *     options={"comment"="Password hash","default"="NULL"}, nullable=true)
     * @Serializer\Exclude()
     */
    private $password;

    /**
     * @var string|null
     * @Serializer\Exclude()
     */
    private $plainPassword;

    /**
     * @var string
     * @ORM\Column(type="string", name="ip_address", length=255,
     *     options={"comment"="IP Address used to autologin","default"="NULL"},
     *     nullable=true)
     * @Serializer\SerializedName("ip")
     * @Assert\Ip(version="all")
     */
    private $ipAddress;

    /**
     * @var boolean
     * @ORM\Column(type="boolean", name="enabled",
     *     options={"comment"="Whether the user is able to log in",
     *              "default"="1"},
     *     nullable=false)
     */
    private $enabled = true;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", name="teamid", length=4,
     *     options={"comment"="Team associated with", "unsigned"=true,
     *              "default"="NULL"},
     *     nullable=true)
     * @Serializer\SerializedName("team_id")
     * @Serializer\Type("string")
     */
    private $teamid;

    /**
     * @ORM\ManyToOne(targetEntity="Team", inversedBy="users")
     * @ORM\JoinColumn(name="teamid", referencedColumnName="teamid", onDelete="SET NULL")
     * @Serializer\Exclude()
     */
    private $team;

    /**
     * @ORM\ManyToMany(targetEntity="Role", inversedBy="users")
     * @ORM\JoinTable(name="userrole",
     *                joinColumns={@ORM\JoinColumn(name="userid", referencedColumnName="userid", onDelete="CASCADE")},
     *                inverseJoinColumns={@ORM\JoinColumn(name="roleid", referencedColumnName="roleid", onDelete="CASCADE")}
     *               )
     * @Serializer\Exclude()
     *
     * Note that this property is called `user_roles` and not `roles` because the
     * UserInterface expects roles/getRoles to return a string list of roles, not objects.
     */
    private $user_roles;


    public function getSalt()
    {
        return null;
    }

    public function eraseCredentials()
    {
        $this->plainPassword = null;
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
     * Get the last login time for this user as a DateTime object
     *
     * @return DateTime|null
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("last_login_time")
     * @Serializer\Type("DateTime")
     * @throws Exception
     */
    public function getLastLoginObject()
    {
        return $this->getLastLogin() ? new DateTime(Utils::absTime($this->getLastLogin())) : null;
    }

    /**
     * Set firstLogin
     *
     * @param $firstLogin
     * @return User
     */
    public function setFirstLogin($firstLogin)
    {
        $this->first_login = $firstLogin;

        return $this;
    }

    /**
     * Get firstLogin
     *
     * @return string
     */
    public function getFirstLogin()
    {
        return $this->first_login;
    }

    /**
     * Get the first login time for this user as a DateTime object
     *
     * @return DateTime|null
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("first_login_time")
     * @Serializer\Type("DateTime")
     * @throws Exception
     */
    public function getFirstLoginObject()
    {
        return $this->getFirstLogin() ? new DateTime(Utils::absTime($this->getFirstLogin())) : null;
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
     * Set the plain password, used to create the encoded password
     * @param string|null $plainPassword
     *
     * @return User
     */
    public function setPlainPassword($plainPassword): User
    {
        $this->plainPassword = $plainPassword;
        // Make sure we let Doctrine know the password changed when we set a plain password by modifying the field
        $this->password      = $this->password === null ? '' : null;
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
     * Get the plain password, used to create the encoded password
     *
     * @return string|null
     */
    public function getPlainPassword()
    {
        return $this->plainPassword;
    }

    /**
     * Set ipaddress
     *
     * @param string $ipAddress
     *
     * @return User
     */
    public function setIpAddress($ipAddress)
    {
        $this->ipAddress = $ipAddress;

        return $this;
    }

    /**
     * Get ipaddress
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
     * @param Team $team
     *
     * @return User
     */
    public function setTeam(Team $team = null)
    {
        $this->team = $team;

        return $this;
    }

    /**
     * Get team
     *
     * @return Team
     */
    public function getTeam()
    {
        return $this->team;
    }

    /**
     * Get the team name of this user
     * @return string|null
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("team")
     * @Serializer\Type("string")
     * @Serializer\Groups({"Nonstrict"})
     */
    public function getTeamName()
    {
        return $this->getTeam() ? $this->getTeam()->getEffectiveName() : null;
    }

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->user_roles = new ArrayCollection();
    }

    /**
     * Add role
     *
     * @param Role $role
     *
     * @return User
     */
    public function addUserRole(Role $role)
    {
        $this->user_roles[] = $role;

        return $this;
    }

    /**
     * Remove role
     *
     * @param Role $role
     */
    public function removeUserRole(Role $role)
    {
        $this->user_roles->removeElement($role);
    }

    /**
     * Get roles
     *
     * @return Role[]
     */
    public function getUserRoles()
    {
        return $this->user_roles->toArray();
    }

    /**
     * Get the roles of this user as an array of strings
     * @return string[]
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("roles")
     * @Serializer\Type("array<string>")
     */
    public function getRoleList(): array
    {
        $result = [];
        foreach ($this->getUserRoles() as $role) {
            $result[] = $role->getDjRole();
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function getRoles(): array
    {
        $result = [];
        foreach ($this->getUserRoles() as $role) {
            $result[] = $role->getRole();
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function isEqualTo(UserInterface $user)
    {
        if (!$user instanceof self) {
            return false;
        }

        if ($this->getPassword() !== $user->getPassword()) {
            return false;
        }

        if ($this->getUsername() !== $user->getUsername()) {
            return false;
        }

        if ($this->getEnabled() !== $user->getEnabled()) {
            return false;
        }

        return true;
    }
}
