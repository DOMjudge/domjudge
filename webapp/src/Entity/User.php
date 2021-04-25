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
 * Users that have access to DOMjudge.
 *
 * @ORM\Entity()
 * @ORM\Table(
 *     name="user",
 *     options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4", "comment"="Users that have access to DOMjudge"},
 *     indexes={@ORM\Index(name="teamid", columns={"teamid"})},
 *     uniqueConstraints={@ORM\UniqueConstraint(name="username", columns={"username"}, options={"lengths":{190}})})
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
    private $username = '';

    /**
     * @var string
     * @ORM\Column(type="string", name="name", length=255,
     *     options={"comment"="Name"}, nullable=false)
     */
    private $name = '';

    /**
     * @var string
     * @ORM\Column(type="string", name="email", length=255,
     *     options={"comment"="Email address"}, nullable=true)
     * @Assert\Email()
     */
    private $email = null;

    /**
     * @var double
     * @ORM\Column(type="decimal", precision=32, scale=9, name="last_login",
     *     options={"comment"="Time of last successful login", "unsigned"=true},
     *     nullable=true)
     * @Serializer\Exclude()
     */
    private $last_login;

    /**
     * @var double
     * @ORM\Column(type="decimal", precision=32, scale=9, name="first_login",
     *     options={"comment"="Time of first login", "unsigned"=true},
     *     nullable=true)
     * @Serializer\Exclude()
     */
    private $first_login;

    /**
     * @var string
     * @ORM\Column(type="string", name="last_ip_address", length=255,
     *     options={"comment"="Last IP address of successful login"},
     *     nullable=true)
     * @Serializer\SerializedName("last_ip")
     */
    private $last_ip_address;

    /**
     * @var string
     * @ORM\Column(type="string", name="password", length=255,
     *     options={"comment"="Password hash"}, nullable=true)
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
     *     options={"comment"="IP Address used to autologin"},
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

    public function getSalt(): ?string
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

    public function getUserid(): ?int
    {
        return $this->userid;
    }

    public function setUsername(?string $username): User
    {
        $this->username = $username;
        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setName(string $name): User
    {
        $this->name = $name;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getShortDesc(): string
    {
        return $this->getName();
    }

    public function setEmail(?string $email): User
    {
        $this->email = $email;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    /** @param string|float $lastLogin */
    public function setLastLogin($lastLogin): User
    {
        $this->last_login = $lastLogin;
        return $this;
    }

    /** @return string|float */
    public function getLastLogin()
    {
        return $this->last_login;
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("last_login_time")
     * @Serializer\Type("DateTime")
     * @throws Exception
     */
    public function getLastLoginAsDateTime(): ?DateTime
    {
        return $this->getLastLogin() ? new DateTime(Utils::absTime($this->getLastLogin())) : null;
    }

    /** @param string|float $firstLogin */
    public function setFirstLogin($firstLogin): User
    {
        $this->first_login = $firstLogin;
        return $this;
    }

    /** @return string|float */
    public function getFirstLogin()
    {
        return $this->first_login;
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("first_login_time")
     * @Serializer\Type("DateTime")
     * @throws Exception
     */
    public function getFirstLoginAsDateTime(): ?DateTime
    {
        return $this->getFirstLogin() ? new DateTime(Utils::absTime($this->getFirstLogin())) : null;
    }

    public function setLastIpAddress(string $lastIpAddress): User
    {
        $this->last_ip_address = $lastIpAddress;
        return $this;
    }

    public function getLastIpAddress(): ?string
    {
        return $this->last_ip_address;
    }

    public function setPassword(string $password): User
    {
        $this->password = $password;
        return $this;
    }

    public function setPlainPassword(?string $plainPassword): User
    {
        $this->plainPassword = $plainPassword;
        // Make sure we let Doctrine know the password changed when we set a plain password by modifying the field.
        $this->password      = $this->password === null ? '' : null;
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    public function setIpAddress(?string $ipAddress): User
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setEnabled(bool $enabled): User
    {
        $this->enabled = $enabled;
        return $this;
    }

    public function getEnabled(): bool
    {
        return $this->enabled;
    }

    public function setTeam(?Team $team = null): User
    {
        $this->team = $team;
        return $this;
    }

    public function getTeam(): ?Team
    {
        return $this->team;
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("team")
     * @Serializer\Type("string")
     * @Serializer\Groups({"Nonstrict"})
     */
    public function getTeamName(): ?string
    {
        return $this->getTeam() ? $this->getTeam()->getEffectiveName() : null;
    }

    public function __construct()
    {
        $this->user_roles = new ArrayCollection();
    }

    public function addUserRole(Role $role): User
    {
        $this->user_roles[] = $role;
        return $this;
    }

    public function removeUserRole(Role $role)
    {
        $this->user_roles->removeElement($role);
    }

    public function getUserRoles(): array
    {
        return $this->user_roles->toArray();
    }

    /**
     * Get the roles of this user as an array of strings
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
    public function isEqualTo(UserInterface $user): bool
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
