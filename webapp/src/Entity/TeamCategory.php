<?php declare(strict_types=1);
namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Categories for teams (e.g.: participants, observers, ...).
 *
 * @ORM\Entity()
 * @ORM\Table(
 *     name="team_category",
 *     options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4", "comment"="Categories for teams (e.g.: participants, observers, ...)"},
 *     indexes={@ORM\Index(name="sortorder", columns={"sortorder"})})
 * @Serializer\VirtualProperty(
 *     "hidden",
 *     exp="!object.getVisible()",
 *     options={@Serializer\Type("boolean")}
 * )
 * @Serializer\VirtualProperty(
 *     "icpc_id",
 *     exp="object.getCategoryid()",
 *     options={@Serializer\Type("string")}
 * )
 */
class TeamCategory extends BaseApiEntity
{
    /**
     * @var int
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="categoryid", length=4,
     *     options={"comment"="Team category ID","unsigned"=true}, nullable=false)
     * @Serializer\SerializedName("id")
     * @Serializer\Type("string")
     */
    protected $categoryid;

    /**
     * @var string
     * @ORM\Column(type="string", name="name", length=255,
     *     options={"comment"="Descriptive name"}, nullable=false)
     * @Assert\NotBlank()
     */
    private $name;

    /**
     * @var int
     * @ORM\Column(type="tinyint", name="sortorder", length=1,
     *     options={"comment"="Where to sort this category on the scoreboard",
     *              "unsigned"=true,"default"="0"},
     *     nullable=false)
     * @Serializer\Groups({"Nonstrict"})
     * @Assert\GreaterThanOrEqual(0, message="Only non-negative sortorders are supported")
     */
    private $sortorder = 0;

    /**
     * @var string
     * @ORM\Column(type="string", length=32, name="color",
     *     options={"comment"="Background colour on the scoreboard"},
     *     nullable=true)
     * @Serializer\Groups({"Nonstrict"})
     */
    private $color;

    /**
     * @var boolean
     * @ORM\Column(type="boolean", name="visible",
     *     options={"comment"="Are teams in this category visible?",
     *              "default"="1"},
     *     nullable=false)
     * @Serializer\Exclude()
     */
    private $visible = true;

    /**
     * @var boolean
     * @ORM\Column(type="boolean", name="allow_self_registration",
     *     options={"comment"="Are self-registered teams allowed to choose this category?",
     *              "default"="0"},
     *     nullable=false)
     * @Serializer\Exclude()
     */
    private $allow_self_registration = false;

    /**
     * @ORM\OneToMany(targetEntity="Team", mappedBy="category")
     * @Serializer\Exclude()
     */
    private $teams;

    /**
     * @ORM\ManyToMany(targetEntity="Contest", mappedBy="team_categories")
     * @Serializer\Exclude()
     */
    private $contests;

    public function __construct()
    {
        $this->teams    = new ArrayCollection();
        $this->contests = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->name;
    }

    public function setCategoryid(int $categoryid): TeamCategory
    {
        $this->categoryid = $categoryid;
        return $this;
    }

    public function getCategoryid(): int
    {
        return $this->categoryid;
    }

    public function setName(string $name): TeamCategory
    {
        $this->name = $name;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
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

    public function setColor(string $color): TeamCategory
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

    public function removeTeam(Team $team)
    {
        $this->teams->removeElement($team);
    }

    public function getTeams(): Collection
    {
        return $this->teams;
    }

    /**
     * @return Collection|Contest[]
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

    public function removeContest(Contest $contest): self
    {
        if ($this->contests->contains($contest)) {
            $this->contests->removeElement($contest);
            $contest->removeTeamCategory($this);
        }

        return $this;
    }

    public function inContest(Contest $contest): bool
    {
        return $contest->isOpenToAllTeams() || $this->getContests()->contains($contest);
    }
}
