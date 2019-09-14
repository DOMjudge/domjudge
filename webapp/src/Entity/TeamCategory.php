<?php declare(strict_types=1);
namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Categories for teams (e.g.: participants, observers, ...)
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
     *     options={"comment"="Background colour on the scoreboard",
     *              "default"="NULL"},
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
     * @ORM\OneToMany(targetEntity="Team", mappedBy="category")
     * @Serializer\Exclude()
     */
    private $teams;

    /**
     * @ORM\ManyToMany(targetEntity="Contest", mappedBy="team_categories")
     * @Serializer\Exclude()
     */
    private $contests;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->teams = new \Doctrine\Common\Collections\ArrayCollection();
        $this->contests = new ArrayCollection();
    }

    /**
    * To String
    */
    public function __toString()
    {
        return $this->name;
    }

    /**
     * Set categoryid
     *
     * @param int $categoryid
     *
     * @return TeamCategory
     */
    public function setCategoryid(int $categoryid)
    {
        $this->categoryid = $categoryid;
        return $this;
    }

    /**
     * Get categoryid
     *
     * @return integer
     */
    public function getCategoryid()
    {
        return $this->categoryid;
    }

    /**
     * Set name
     *
     * @param string $name
     *
     * @return TeamCategory
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
     * Set sortorder
     *
     * @param integer $sortorder
     *
     * @return TeamCategory
     */
    public function setSortorder($sortorder)
    {
        $this->sortorder = $sortorder;

        return $this;
    }

    /**
     * Get sortorder
     *
     * @return integer
     */
    public function getSortorder()
    {
        return $this->sortorder;
    }

    /**
     * Set color
     *
     * @param string $color
     *
     * @return TeamCategory
     */
    public function setColor($color)
    {
        $this->color = $color;

        return $this;
    }

    /**
     * Get color
     *
     * @return string
     */
    public function getColor()
    {
        return $this->color;
    }

    /**
     * Set visible
     *
     * @param boolean $visible
     *
     * @return TeamCategory
     */
    public function setVisible($visible)
    {
        $this->visible = $visible;

        return $this;
    }

    /**
     * Get visible
     *
     * @return boolean
     */
    public function getVisible()
    {
        return $this->visible;
    }

    /**
     * Add team
     *
     * @param \App\Entity\Team $team
     *
     * @return TeamCategory
     */
    public function addTeam(\App\Entity\Team $team)
    {
        $this->teams[] = $team;

        return $this;
    }

    /**
     * Remove team
     *
     * @param \App\Entity\Team $team
     */
    public function removeTeam(\App\Entity\Team $team)
    {
        $this->teams->removeElement($team);
    }

    /**
     * Get teams
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getTeams()
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
}
