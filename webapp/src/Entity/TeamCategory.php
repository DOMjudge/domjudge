<?php declare(strict_types=1);
namespace App\Entity;

use App\Controller\API\AbstractRestController as ARC;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use OpenApi\Attributes as OA;
use Stringable;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Categories for teams (e.g.: participants, observers, ...).
 */
#[ORM\Entity]
#[ORM\Table(options: [
    'collation' => 'utf8mb4_unicode_ci',
    'charset' => 'utf8mb4',
    'comment' => 'Categories for teams (e.g.: participants, observers, ...)',
])]
#[ORM\Index(columns: ['sortorder'], name: 'sortorder')]
#[ORM\UniqueConstraint(name: 'externalid', columns: ['externalid'], options: ['lengths' => [190]])]
#[Serializer\VirtualProperty(
    name: 'hidden',
    exp: '!object.getVisible()',
    options: [new Serializer\Type('boolean'), new Serializer\Groups(['Nonstrict'])]
)]
#[UniqueEntity(fields: 'externalid')]
class TeamCategory extends BaseApiEntity implements Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['comment' => 'Team category ID', 'unsigned' => true])]
    #[Serializer\SerializedName('id')]
    #[Serializer\Type('string')]
    protected ?int $categoryid = null;

    #[ORM\Column(
        nullable: true,
        options: ['comment' => 'Team category ID in an external system', 'collation' => 'utf8mb4_bin']
    )]
    #[Serializer\Exclude]
    protected ?string $externalid = null;

    #[ORM\Column(
        nullable: true,
        options: ['comment' => 'External identifier from ICPC CMS', 'collation' => 'utf8mb4_bin']
    )]
    #[OA\Property(nullable: true)]
    #[Serializer\SerializedName('icpc_id')]
    protected ?string $icpcid = null;

    #[ORM\Column(options: ['comment' => 'Descriptive name'])]
    #[Assert\NotBlank]
    private string $name;

    #[ORM\Column(
        type: 'tinyint',
        options: ['comment' => 'Where to sort this category on the scoreboard', 'unsigned' => true, 'default' => 0]
    )]
    #[Assert\GreaterThanOrEqual(0, message: 'Only non-negative sortorders are supported')]
    #[Serializer\Groups([ARC::GROUP_NONSTRICT])]
    private int $sortorder = 0;

    #[ORM\Column(
        length: 32,
        nullable: true,
        options: ['comment' => 'Background colour on the scoreboard']
    )]
    #[OA\Property(nullable: true)]
    #[Serializer\Groups([ARC::GROUP_NONSTRICT])]
    private ?string $color = null;

    #[ORM\Column(options: ['comment' => 'Are teams in this category visible?', 'default' => 1])]
    #[Serializer\Exclude]
    private bool $visible = true;

    #[ORM\Column(options: [
        'comment' => 'Are self-registered teams allowed to choose this category?',
        'default' => 0,
    ])]
    #[Serializer\Exclude]
    private bool $allow_self_registration = false;

    /**
     * @var Collection<int, Team>
     */
    #[ORM\OneToMany(mappedBy: 'category', targetEntity: Team::class)]
    #[Serializer\Exclude]
    private Collection $teams;

    /**
     * @var Collection<int, Contest>
     */
    #[ORM\ManyToMany(targetEntity: Contest::class, mappedBy: 'team_categories')]
    #[Serializer\Exclude]
    private Collection $contests;

    /**
     * @var Collection<int, Contest>
     */
    #[ORM\ManyToMany(targetEntity: Contest::class, mappedBy: 'medal_categories')]
    #[Serializer\Exclude]
    private Collection $contests_for_medals;

    public function __construct()
    {
        $this->teams               = new ArrayCollection();
        $this->contests            = new ArrayCollection();
        $this->contests_for_medals = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->name;
    }

    public function setExternalid(?string $externalid): TeamCategory
    {
        $this->externalid = $externalid;
        return $this;
    }

    public function getExternalid(): ?string
    {
        return $this->externalid;
    }

    public function setIcpcid(?string $icpcid): TeamCategory
    {
        $this->icpcid = $icpcid;
        return $this;
    }

    public function getIcpcid(): ?string
    {
        return $this->icpcid;
    }

    public function setCategoryid(int $categoryid): TeamCategory
    {
        $this->categoryid = $categoryid;
        return $this;
    }

    public function getCategoryid(): ?int
    {
        return $this->categoryid;
    }

    public function setName(?string $name): TeamCategory
    {
        $this->name = (string)$name;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getShortDescription(): ?string
    {
        return $this->getName();
    }

    public function setSortorder(int $sortorder): TeamCategory
    {
        $this->sortorder = $sortorder;
        return $this;
    }

    public function getSortorder(): int
    {
        return $this->sortorder;
    }

    public function setColor(?string $color): TeamCategory
    {
        $this->color = $color;
        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setVisible(bool $visible): TeamCategory
    {
        $this->visible = $visible;
        return $this;
    }

    public function getVisible(): bool
    {
        return $this->visible;
    }

    public function setAllowSelfRegistration(bool $allowSelfRegistration): TeamCategory
    {
        $this->allow_self_registration = $allowSelfRegistration;
        return $this;
    }

    public function getAllowSelfRegistration(): bool
    {
        return $this->allow_self_registration;
    }

    public function addTeam(Team $team): TeamCategory
    {
        $this->teams[] = $team;
        return $this;
    }

    /**
     * @return Collection<int, Team>
     */
    public function getTeams(): Collection
    {
        return $this->teams;
    }

    /**
     * @return Collection<int, Contest>
     */
    public function getContests(): Collection
    {
        return $this->contests;
    }

    public function addContest(Contest $contest): self
    {
        if (!$this->contests->contains($contest)) {
            $this->contests[] = $contest;
            $contest->addTeamCategory($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, Contest>
     */
    public function getContestsForMedals(): Collection
    {
        return $this->contests_for_medals;
    }

    public function addContestForMedals(Contest $contest): self
    {
        if (!$this->contests_for_medals->contains($contest)) {
            $this->contests_for_medals[] = $contest;
            $contest->addMedalCategory($this);
        }

        return $this;
    }

    public function inContest(Contest $contest): bool
    {
        return $contest->isOpenToAllTeams() || $this->getContests()->contains($contest);
    }
}
