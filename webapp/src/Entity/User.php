<?php declare(strict_types=1);
namespace App\Entity;

use App\Utils\Utils;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use OpenApi\Annotations as OA;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Users that have access to DOMjudge.
 *
 * @ORM\Entity()
 * @ORM\Table(
 *     name="user",
 *     options={"collation"="utf8mb4_unicode_ci", "charset"="utf8mb4", "comment"="Users that have access to DOMjudge"},
 *     indexes={@ORM\Index(name="teamid", columns={"teamid"})},
 *     uniqueConstraints={
 *         @ORM\UniqueConstraint(name="username", columns={"username"}, options={"lengths":{190}}),
 *         @ORM\UniqueConstraint(name="externalid", columns={"externalid"}, options={"lengths":{190}}),
 *     })
 * @UniqueEntity("username", message="The username '{{ value }}' is already in use.")
 */
class User extends BaseApiEntity implements UserInterface, PasswordAuthenticatedUserInterface, EquatableInterface, ExternalRelationshipEntityInterface
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="userid", length=4,
     *     options={"comment"="User ID","unsigned"=true}, nullable=false)
     * @Serializer\SerializedName("id")
     * @Serializer\Type("string")
     */
    private ?int $userid = null;

    /**
     * @ORM\Column(type="string", name="externalid", length=255,
     *     options={"comment"="User ID in an external system",
     *              "collation"="utf8mb4_bin"},
     *     nullable=true)
     * @Serializer\Exclude()
     */
    protected ?string $externalid = null;

    /**
     * @ORM\Column(type="string", name="username", length=255,
     *     options={"comment"="User login name"}, nullable=false)
     * @Assert\Regex("/^[a-z0-9@._-]+$/i", message="Only alphanumeric characters and _-@. are allowed")
     */
    private string $username = '';

    /**
     * @ORM\Column(type="string", name="name", length=255,
     *     options={"comment"="Name"}, nullable=false)
     * @Serializer\Groups({"Nonstrict"})
     */
    private string $name = '';

    /**
     * @ORM\Column(type="string", name="email", length=255,
     *     options={"comment"="Email address"}, nullable=true)
     * @Assert\Email()
     * @Serializer\Groups({"Nonstrict"})
     * @OA\Property(nullable=true)
     */
    private ?string $email = null;

    /**
     * @var double|string|null
     * @ORM\Column(type="decimal", precision=32, scale=9, name="last_login",
     *     options={"comment"="Time of last successful login", "unsigned"=true},
     *     nullable=true)
     * @Serializer\Exclude()
     * @OA\Property(nullable=true)
     */
    private $last_login;

    /**
     * @var double|string|null
     * @ORM\Column(type="decimal", precision=32, scale=9, name="last_api_login",
     *     options={"comment"="Time of last successful login on the API", "unsigned"=true},
     *     nullable=true)
     * @Serializer\Exclude()
     * @OA\Property(nullable=true)
     */
    private $last_api_login;

    /**
     * @var double|string|null
     * @ORM\Column(type="decimal", precision=32, scale=9, name="first_login",
     *     options={"comment"="Time of first login", "unsigned"=true},
     *     nullable=true)
     * @Serializer\Exclude()
     * @OA\Property(nullable=true)
     */
    private $first_login;

    /**
     * @ORM\Column(type="string", name="last_ip_address", length=255,
     *     options={"comment"="Last IP address of successful login"},
     *     nullable=true)
     * @Serializer\SerializedName("last_ip")
     * @Serializer\Groups({"Nonstrict"})
     * @OA\Property(nullable=true)
     */
    private ?string $last_ip_address = null;

    /**
     * @ORM\Column(type="string", name="password", length=255,
     *     options={"comment"="Password hash"}, nullable=true)
     * @Serializer\Exclude()
     */
    private ?string $password = null;

    /**
     * @Serializer\Exclude()
     */
    private ?string $plainPassword = null;

    /**
     * @ORM\Column(type="string", name="ip_address", length=255,
     *     options={"comment"="IP Address used to autologin"},
     *     nullable=true)
     * @Serializer\SerializedName("ip")
     * @Assert\Ip(version="all")
     * @OA\Property(nullable=true)
     */
    private ?string $ipAddress;

    /**
     * @ORM\Column(type="boolean", name="enabled",
     *     options={"comment"="Whether the user is able to log in",
     *              "default"="1"},
     *     nullable=false)
     * @Serializer\Groups({"Nonstrict"})
     */
    private bool $enabled = true;

    /**
     * @ORM\ManyToOne(targetEntity="Team", inversedBy="users")
     * @ORM\JoinColumn(name="teamid", referencedColumnName="teamid", onDelete="SET NULL")
     * @Serializer\Exclude()
     */
    private ?Team $team = null;

    /**
     * @ORM\ManyToMany(targetEntity="Role", inversedBy="users")
     * @ORM\JoinTable(name="userrole",
     *                joinColumns={@ORM\JoinColumn(name="userid", referencedColumnName="userid", onDelete="CASCADE")},
     *                inverseJoinColumns={@ORM\JoinColumn(name="roleid", referencedColumnName="roleid", onDelete="CASCADE")}
     *               )
     * @Serializer\Exclude()
     * @Assert\Count(min="1")
     *
     * Note that this property is called `user_roles` and not `roles` because the
     * UserInterface expects roles/getRoles to return a string list of roles, not objects.
     */
    private Collection $user_roles;

    /**
     * @ORM\OneToMany(targetEntity="Submission", mappedBy="user")
     * @Serializer\Exclude()
     */
    private Collection $submissions;

    public function getSalt(): ?string
    {
        return null;
    }

    public function eraseCredentials()
    {
        $this->plainPassword = null;
    }

    public function __serialize(): array
    {
        return [
            $this->userid,
            $this->username,
            $this->password,
        ];
    }

    public function __unserialize(array $data): void
    {
        [
            $this->userid,
            $this->username,
            $this->password,
        ] = $data;
    }

    public function getUserid(): ?int
    {
        return $this->userid;
    }

    public function setExternalid(?string $externalid): User
    {
        $this->externalid = $externalid;
        return $this;
    }

    public function getExternalid(): ?string
    {
        return $this->externalid;
    }

    public function setUsername(?string $username): User
    {
        $this->username = (string)$username;
        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setName(?string $name): User
    {
        $this->name = (string)$name;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getShortDescription(): string
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
     * @Serializer\Groups({"Nonstrict"})
     * @Serializer\Type("DateTime")
     * @OA\Property(nullable=true)
     */
    public function getLastLoginAsDateTime(): ?DateTime
    {
        return $this->getLastLogin() ? new DateTime(Utils::absTime($this->getLastLogin())) : null;
    }

    /** @param string|float $lastApiLogin */
    public function setLastApiLogin($lastApiLogin): User
    {
        $this->last_api_login = $lastApiLogin;
        return $this;
    }

    /** @return string|float */
    public function getLastApiLogin()
    {
        return $this->last_api_login;
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("last_api_login_time")
     * @Serializer\Groups({"Nonstrict"})
     * @Serializer\Type("DateTime")
     * @OA\Property(nullable=true)
     */
    public function getLastApiLoginAsDateTime(): ?DateTime
    {
        return $this->getLastApiLogin() ? new DateTime(Utils::absTime($this->getLastApiLogin())) : null;
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
     * @Serializer\Groups({"Nonstrict"})
     * @Serializer\Type("DateTime")
     * @OA\Property(nullable=true)
     */
    public function getFirstLoginAsDateTime(): ?DateTime
    {
        return $this->getFirstLogin() ? new DateTime(Utils::absTime($this->getFirstLogin())) : null;
    }

    public function setLastIpAddress(?string $lastIpAddress): User
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
     * @OA\Property(nullable=true)
     */
    public function getTeamName(): ?string
    {
        return $this->getTeam() ? $this->getTeam()->getEffectiveName() : null;
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("team_id")
     * @Serializer\Type("string")
     * @OA\Property(nullable=true)
     */
    public function getTeamId(): ?int
    {
        return $this->getTeam() ? $this->getTeam()->getTeamid() : null;
    }

    public function __construct()
    {
        $this->user_roles = new ArrayCollection();
        $this->submissions = new ArrayCollection();
    }

    public function addUserRole(Role $role): User
    {
        $this->user_roles[] = $role;
        return $this;
    }

    public function removeUserRole(Role $role): void
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
     * @Serializer\Groups({"Nonstrict"})
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
     * Get the type of this user for the CCS Specs Contest API
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("type")
     * @Serializer\Type("string")
     * @OA\Property(nullable=true)
     */
    public function getType(): ?string
    {
        // Types allowed by the CCS Specs Contest API in order of most permissions to least
        // Either key=>value where key is the DOMjudge role and value is the API type or
        // only value, where both the DOMjudge role and API type are the same
        $allowedTypes = ['admin', 'jury' => 'judge', 'api_reader' => 'admin', 'team'];
        foreach ($allowedTypes as $role => $allowedType) {
            if (is_numeric($role)) {
                $role = $allowedType;
            }
            if (in_array($role, $this->getRoleList())) {
                return $allowedType;
            }
        }

        return null;
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

    public function addSubmission(Submission $submission): User
    {
        $this->submissions[] = $submission;
        return $this;
    }

    public function removeSubmission(Submission $submission): void
    {
        $this->submissions->removeElement($submission);
    }

    public function getSubmissions(): Collection
    {
        return $this->submissions;
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

    public function getUserIdentifier(): string
    {
        return $this->getUsername();
    }

    public function getExternalRelationships(): array
    {
        return [
            'team_id' => $this->getTeam(),
        ];
    }
}
