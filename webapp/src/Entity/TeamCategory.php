<?php declare(strict_types=1);
namespace App\Entity;

use App\Controller\API\AbstractRestController as ARC;
use App\Repository\TeamCategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use OpenApi\Attributes as OA;
use Stringable;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Categories for teams (e.g.: participants, observers, ...).
 */
#[ORM\Entity(repositoryClass: TeamCategoryRepository::class)]
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
class TeamCategory extends BaseApiEntity implements
    Stringable,
    HasExternalIdInterface,
    ExternalIdFromInternalIdInterface,
    PrefixedExternalIdInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['comment' => 'Team category ID', 'unsigned' => true])]
    #[Serializer\Exclude]
    protected ?int $categoryid = null;

    #[ORM\Column(
        nullable: true,
        options: ['comment' => 'Team category ID in an external system', 'collation' => 'utf8mb4_bin']
    )]
    #[Serializer\SerializedName('id')]
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

    // These types are encoded as bitset - if you add a new type, use the next power of 2.
    public const TYPE_SCORING = 1;
    public const TYPE_BACKGROUND = 2;
    public const TYPE_BADGE_TOP = 4;
    public const TYPE_BADGE_ALL = 8;
    public const TYPE_CSS_CLASS = 16;

    /**
     * @var array<int, string>
     */
    public const TYPES_TO_STRING = [
        self::TYPE_SCORING => 'scoring',
        self::TYPE_BACKGROUND => 'background',
        self::TYPE_BADGE_TOP => 'badge-top',
        self::TYPE_BADGE_ALL => 'badge-all',
        self::TYPE_CSS_CLASS => 'css-class',
    ];

    /**
     * @var array<int, string>
     */
    public const TYPES_TO_HUMAN_STRING = [
        self::TYPE_SCORING => 'Scoring',
        self::TYPE_BACKGROUND => 'Background color',
        self::TYPE_BADGE_TOP => 'Badge for top team',
        self::TYPE_BADGE_ALL => 'Badge for all teams',
        self::TYPE_CSS_CLASS => 'CSS class',
    ];

    #[ORM\Column(options: ['comment' => 'Bitmask of category types, default is scoring.'])]
    #[Serializer\Exclude]
    private int $types = self::TYPE_SCORING;

    #[ORM\Column(
        type: 'tinyint',
        nullable: true,
        options: ['comment' => 'Where to sort this category on the scoreboard', 'unsigned' => true]
    )]
    #[Assert\GreaterThanOrEqual(0, message: 'Only non-negative sortorders are supported')]
    #[Serializer\Groups([ARC::GROUP_NONSTRICT])]
    #[Serializer\Exclude(if: 'object.getSortorder() === null')]
    private ?int $sortorder = 0;

    #[ORM\Column(
        length: 32,
        nullable: true,
        options: ['comment' => 'Background colour on the scoreboard']
    )]
    #[OA\Property(nullable: true)]
    #[Serializer\Groups([ARC::GROUP_NONSTRICT])]
    #[Serializer\Exclude(if: 'object.getColor() === null')]
    private ?string $color = null;

    #[ORM\Column(options: ['comment' => 'Are teams in this category visible?', 'default' => 1])]
    #[Serializer\Exclude]
    private bool $visible = true;

    #[ORM\Column(options: [
        'comment' => 'Are self-registered teams allowed to choose this category?',
        'default' => 0,
    ])]
    #[Serializer\Groups([ARC::GROUP_NONSTRICT])]
    private bool $allow_self_registration = false;

    #[ORM\Column(
        nullable: true,
        options: ['comment' => 'CSS class to apply to scoreboard rows (only for TYPE_CSS_CLASS)']
    )]
    #[Serializer\Groups([ARC::GROUP_NONSTRICT])]
    #[Serializer\Exclude(if: 'object.getCssClass() === null')]
    private ?string $css_class = null;

    /**
     * @var Collection<int, Team>
     */
    #[ORM\ManyToMany(targetEntity: Team::class, inversedBy: 'categories', cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'categoryid', referencedColumnName: 'categoryid', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'teamid', referencedColumnName: 'teamid', onDelete: 'CASCADE')]
    #[Assert\Valid]
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

    public function setName(string $name): TeamCategory
    {
        $this->name = $name;
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

    public function setSortorder(?int $sortorder): TeamCategory
    {
        $this->sortorder = $sortorder;
        return $this;
    }

    public function getSortorder(): ?int
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


    public function hasType(int $type): bool
    {
        return ($this->types & $type) !== 0;
    }

    public function addType(int $type): TeamCategory
    {
        $this->types |= $type;
        return $this;
    }

    public function removeType(int $type): TeamCategory
    {
        $this->types &= ~$type;
        return $this;
    }

    /**
     * @return list<int>
     */
    public function getTypes(): array
    {
        $ret = [];
        foreach (array_keys(self::TYPES_TO_STRING) as $type) {
            if ($this->types & $type) {
                $ret[] = $type;
            }
        }
        return $ret;
    }

    /**
     * @param array<int> $types
     */
    public function setTypes(array $types): TeamCategory
    {
        $types = array_unique($types);
        $this->types = 0;
        foreach ($types as $type) {
            $this->types |= $type;
        }
        return $this;
    }

    /**
     * @return string[]
     */
    #[Serializer\VirtualProperty]
    #[Serializer\SerializedName('types')]
    #[Serializer\Type('array<string>')]
    #[Serializer\Groups([ARC::GROUP_NONSTRICT])]
    public function getTypeNames(): array
    {
        $names = [];
        foreach (self::TYPES_TO_STRING as $typeValue => $typeName) {
            if ($this->hasType($typeValue)) {
                $names[] = $typeName;
            }
        }
        return $names;
    }

    /**
     * @return string[]
     */
    public function getTypeHumanNames(): array
    {
        $names = [];
        foreach (self::TYPES_TO_HUMAN_STRING as $typeValue => $typeName) {
            if ($this->hasType($typeValue)) {
                $names[] = $typeName;
            }
        }
        return $names;
    }

    public function setCssClass(?string $cssClass): TeamCategory
    {
        $this->css_class = $cssClass;
        return $this;
    }

    public function getCssClass(): ?string
    {
        return $this->css_class;
    }

    public function addTeam(Team $team): TeamCategory
    {
        $this->teams[] = $team;
        return $this;
    }

    public function removeTeam(Team $team): void
    {
        $this->teams->removeElement($team);
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

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        // Types field must only contain valid type bits
        $validTypes = array_reduce(array_keys(self::TYPES_TO_STRING), fn($carry, $type) => $carry | $type, 0);
        if (($this->types & ~$validTypes) !== 0) {
            $context
                ->buildViolation('Invalid category type combination.')
                ->atPath('types')
                ->addViolation();
        }

        // Badge types are mutually exclusive
        if ($this->hasType(self::TYPE_BADGE_TOP) && $this->hasType(self::TYPE_BADGE_ALL)) {
            $message = sprintf(
                'A category cannot be both "%s" and "%s".',
                self::TYPES_TO_HUMAN_STRING[self::TYPE_BADGE_TOP],
                self::TYPES_TO_HUMAN_STRING[self::TYPE_BADGE_ALL]
            );
            $context
                ->buildViolation($message)
                ->atPath('types')
                ->addViolation();
        }

        // Validate type-specific field requirements
        $typeFieldRequirements = [
            self::TYPE_SCORING => ['field' => 'sortorder', 'value' => $this->sortorder, 'name' => 'Sort order'],
            self::TYPE_BACKGROUND => ['field' => 'color', 'value' => $this->color, 'name' => 'Color'],
            self::TYPE_CSS_CLASS => ['field' => 'css_class', 'value' => $this->css_class, 'name' => 'CSS class'],
        ];

        foreach ($typeFieldRequirements as $type => $fieldInfo) {
            $hasType = $this->hasType($type);
            $hasValue = $fieldInfo['value'] !== null;

            if ($hasType && !$hasValue) {
                $context
                    ->buildViolation(sprintf('%s is required for %s categories.',
                        $fieldInfo['name'],
                        strtolower(self::TYPES_TO_HUMAN_STRING[$type])
                    ))
                    ->atPath($fieldInfo['field'])
                    ->addViolation();
            }

            if (!$hasType && $hasValue) {
                $context
                    ->buildViolation(sprintf('%s should only be set for %s categories.',
                        $fieldInfo['name'],
                        strtolower(self::TYPES_TO_HUMAN_STRING[$type])
                    ))
                    ->atPath($fieldInfo['field'])
                    ->addViolation();
            }
        }
    }
}
