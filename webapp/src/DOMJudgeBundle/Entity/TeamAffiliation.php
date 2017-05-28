<?php
namespace DOMJudgeBundle\Entity;
use Doctrine\ORM\Mapping as ORM;
/**
 * Affilitations for teams (e.g.: university, company)
 * @ORM\Entity()
 * @ORM\Table(name="team_affiliation", options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4"})
 */
class TeamAffiliation
{
    /**
     * @var int
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="SEQUENCE")
     * @ORM\Column(type="integer", name="affilid", options={"comment"="Unique ID"}, nullable=false)
     */
    private $affilid;

    /**
     * @var string
     * @ORM\Column(type="string", name="shortname", length=30, options={"comment"="Short descriptive name"}, nullable=false)
     */
    private $shortname;

    /**
     * @var string
     * @ORM\Column(type="string", name="name", length=255, options={"comment"="Descriptive name"}, nullable=false)
     */
    private $name;

    /**
     * @var string
     * @ORM\Column(type="string", length=3, name="country", options={"comment"="ISO 3166-1 alpha-3 country code"}, nullable=true)
     */
    private $country;

    /**
     * @var string
     * @ORM\Column(type="text", length=4294967295, name="comments", options={"comment"="Comments about this team"}, nullable=true)
     */
    private $comments;

    /**
     * @ORM\OneToMany(targetEntity="Team", mappedBy="affiliation")
     */
    private $teams;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->teams = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * Get affilid
     *
     * @return integer
     */
    public function getAffilid()
    {
        return $this->affilid;
    }

    /**
     * Set shortname
     *
     * @param string $shortname
     *
     * @return TeamAffiliation
     */
    public function setShortname($shortname)
    {
        $this->shortname = $shortname;

        return $this;
    }

    /**
     * Get shortname
     *
     * @return string
     */
    public function getShortname()
    {
        return $this->shortname;
    }

    /**
     * Set name
     *
     * @param string $name
     *
     * @return TeamAffiliation
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
     * Set country
     *
     * @param string $country
     *
     * @return TeamAffiliation
     */
    public function setCountry($country)
    {
        $this->country = $country;

        return $this;
    }

    /**
     * Get country
     *
     * @return string
     */
    public function getCountry()
    {
        return $this->country;
    }

    /**
     * Set comments
     *
     * @param string $comments
     *
     * @return TeamAffiliation
     */
    public function setComments($comments)
    {
        $this->comments = $comments;

        return $this;
    }

    /**
     * Get comments
     *
     * @return string
     */
    public function getComments()
    {
        return $this->comments;
    }

    /**
     * Add team
     *
     * @param \DOMJudgeBundle\Entity\Team $team
     *
     * @return TeamAffiliation
     */
    public function addTeam(\DOMJudgeBundle\Entity\Team $team)
    {
        $this->teams[] = $team;

        return $this;
    }

    /**
     * Remove team
     *
     * @param \DOMJudgeBundle\Entity\Team $team
     */
    public function removeTeam(\DOMJudgeBundle\Entity\Team $team)
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
}
