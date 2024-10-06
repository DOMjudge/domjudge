<?php declare(strict_types=1);
namespace App\Entity;

use App\Controller\API\AbstractRestController as ARC;
use App\Utils\Utils;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Users that have access to DOMjudge.
 */
#[ORM\Entity]
#[ORM\Table(options: [
    'collation' => 'utf8mb4_unicode_ci',
    'charset' => 'utf8mb4',
    'comment' => 'Users that have access to DOMjudge',
])]
#[ORM\Index(columns: ['teamid'], name: 'teamid')]
#[ORM\UniqueConstraint(name: 'username', columns: ['username'], options: ['lengths' => [190]])]
#[ORM\UniqueConstraint(name: 'externalid', columns: ['externalid'], options: ['lengths' => [190]])]
#[UniqueEntity(fields: 'username', message: "The username '{{ value }}' is already in use.")]
class User extends BaseApiEntity implements
    UserInterface,
    PasswordAuthenticatedUserInterface,
    EquatableInterface,
    HasExternalIdInterface,
    CalculatedExternalIdBasedOnRelatedFieldInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['comment' => 'User ID', 'unsigned' => true])]
    #[Serializer\SerializedName('userid')]
    #[Serializer\Groups([ARC::GROUP_NONSTRICT])]
    private ?int $userid = null;

    #[ORM\Column(
        nullable: true,
        options: ['comment' => 'User ID in an external system', 'collation' => 'utf8mb4_bin']
    )]
    #[Serializer\SerializedName('id')]
    protected ?string $externalid = null;

    #[ORM\Column(options: ['comment' => 'User login name'])]
    // See: https://symfony.com/doc/current/reference/constraints/Regex.html, the regex is considered valid when empty
    #[Assert\NotBlank]
    #[Assert\Regex('/^[a-z0-9@._-]+$/i', message: 'Only alphanumeric characters and _-@. are allowed')]
    private string $username = '';

    #[ORM\Column(options: ['comment' => 'Name'])]
    private string $name = '';

    #[ORM\Column(nullable: true, options: ['comment' => 'Email address'])]
    #[Assert\Email]
    #[OA\Property(nullable: true)]
    #[Serializer\Groups([ARC::GROUP_NONSTRICT])]
    private ?string $email = null;

    #[ORM\Column(
        type: 'decimal',
        precision: 32,
        scale: 9,
        nullable: true,
        options: ['comment' => 'Time of last successful login', 'unsigned' => true]
    )]
    #[OA\Property(nullable: true)]
    #[Serializer\Exclude]
    private string|float|null $last_login = null;

    #[ORM\Column(
        type: 'decimal',
        precision: 32,
        scale: 9,
        nullable: true,
        options: ['comment' => 'Time of last successful login on the API', 'unsigned' => true]
    )]
    #[OA\Property(nullable: true)]
    #[Serializer\Exclude]
    private string|float|null $last_api_login = null;

    #[ORM\Column(
        type: 'decimal',
        precision: 32,
        scale: 9,
        nullable: true,
        options: ['comment' => 'Time of first login', 'unsigned' => true]
    )]
    #[OA\Property(nullable: true)]
    #[Serializer\Exclude]
    private string|float|null $first_login = null;

    #[ORM\Column(nullable: true, options: ['comment' => 'Last IP address of successful login'])]
    #[OA\Property(nullable: true)]
    #[Serializer\SerializedName('last_ip')]
    #[Serializer\Groups([ARC::GROUP_NONSTRICT])]
    private ?string $last_ip_address = null;

    #[ORM\Column(nullable: true, options: ['comment' => 'Password hash'])]
    #[Serializer\Exclude]
    private ?string $password = null;

    #[Serializer\Exclude]
    private ?string $plainPassword = null;

    #[ORM\Column(nullable: true, options: ['comment' => 'IP Address used to autologin'])]
    #[Assert\Ip(version: 'all')]
    #[OA\Property(nullable: true)]
    #[Serializer\SerializedName('ip')]
    private ?string $ipAddress = null;

    #[ORM\Column(options: ['comment' => 'Whether the user is able to log in', 'default' => 1])]
    #[Serializer\Groups([ARC::GROUP_NONSTRICT])]
    private bool $enabled = true;

    #[ORM\ManyToOne(inversedBy: 'users')]
    #[ORM\JoinColumn(name: 'teamid', referencedColumnName: 'teamid', onDelete: 'SET NULL')]
    #[Serializer\Exclude]
    private ?Team $team = null;

    /**
     * @var Collection<int, Role>
     */
    #[ORM\ManyToMany(targetEntity: Role::class, inversedBy: 'users')]
    #[ORM\JoinTable(name: 'userrole')]
    #[ORM\JoinColumn(name: 'userid', referencedColumnName: 'userid', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'roleid', referencedColumnName: 'roleid', onDelete: 'CASCADE')]
    #[Assert\Count(min: 1)]
    #[Serializer\Exclude]
    private Collection $user_roles;

    /**
     * @var Collection<int, Submission>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Submission::class)]
    #[Serializer\Exclude]
    private Collection $submissions;

    public function getSalt(): ?string
    {
        return null;
    }

    public function eraseCredentials(): void
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

    /**
     * @param array{int|null, string|null, string|null} $data
     */
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

    public function setLastLogin(string|float|null $lastLogin): User
    {
        $this->last_login = $lastLogin;
        return $this;
    }

    public function getLastLogin(): string|float|null
    {
        return $this->last_login;
    }

    #[OA\Property(nullable: true)]
    #[Serializer\VirtualProperty]
    #[Serializer\SerializedName('last_login_time')]
    #[Serializer\Type('DateTime')]
    #[Serializer\Groups([ARC::GROUP_NONSTRICT])]
    public function getLastLoginAsDateTime(): ?DateTime
    {
        return $this->getLastLogin() ? new DateTime(Utils::absTime($this->getLastLogin())) : null;
    }

    public function setLastApiLogin(string|float|null $lastApiLogin): User
    {
        $this->last_api_login = $lastApiLogin;
        return $this;
    }

    public function getLastApiLogin(): string|float|null
    {
        return $this->last_api_login;
    }

    #[OA\Property(nullable: true)]
    #[Serializer\VirtualProperty]
    #[Serializer\SerializedName('last_api_login_time')]
    #[Serializer\Type('DateTime')]
    #[Serializer\Groups([ARC::GROUP_NONSTRICT])]
    public function getLastApiLoginAsDateTime(): ?DateTime
    {
        return $this->getLastApiLogin() ? new DateTime(Utils::absTime($this->getLastApiLogin())) : null;
    }

    public function setFirstLogin(string|float|null $firstLogin): User
    {
        $this->first_login = $firstLogin;
        return $this;
    }

    public function getFirstLogin(): string|float|null
    {
        return $this->first_login;
    }

    #[OA\Property(nullable: true)]
    #[Serializer\VirtualProperty]
    #[Serializer\SerializedName('first_login_time')]
    #[Serializer\Type('DateTime')]
    #[Serializer\Groups([ARC::GROUP_NONSTRICT])]
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

    #[OA\Property(nullable: true)]
    #[Serializer\VirtualProperty]
    #[Serializer\SerializedName('team')]
    #[Serializer\Type('string')]
    #[Serializer\Groups([ARC::GROUP_NONSTRICT])]
    public function getTeamName(): ?string
    {
        return $this->getTeam()?->getEffectiveName();
    }

    public function getTeamId(): ?int
    {
        return $this->getTeam()?->getTeamid();
    }

    #[OA\Property(nullable: true)]
    #[Serializer\VirtualProperty]
    #[Serializer\SerializedName('team_id')]
    #[Serializer\Type('string')]
    public function getApiTeamId(): ?string
    {
        return $this->getTeam()?->getExternalid();
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

    /**
     * @return Role[]
     */
    public function getUserRoles(): array
    {
        return $this->user_roles->toArray();
    }

    /**
     * Get the roles of this user as an array of strings
     *
     * @return array<string>
     */
    #[Serializer\VirtualProperty]
    #[Serializer\SerializedName('roles')]
    #[Serializer\Type('array<string>')]
    #[Serializer\Groups([ARC::GROUP_NONSTRICT])]
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
     */
    #[OA\Property(nullable: true)]
    #[Serializer\VirtualProperty]
    #[Serializer\SerializedName('type')]
    #[Serializer\Type('string')]
    public function getType(): ?string
    {
        // Types allowed by the CCS Specs Contest API in order of most permissions to least.
        // Either key=>value where key is the DOMjudge role and value is the API account type or
        // only value, where both the DOMjudge role and API type are the same.
        $allowedTypes = ['admin', 'api_writer' => 'admin', 'api_reader' => 'admin',
                         'jury' => 'judge', 'api_source_reader' => 'judge',
                         'team'];
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

    /**
     * @return Collection<int, Submission>
     */
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

    public function getCalculatedExternalId(): string
    {
        return $this->getUsername();
    }
}
