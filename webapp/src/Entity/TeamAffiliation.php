<?php declare(strict_types=1);
namespace App\Entity;

use App\Validator\Constraints\Country;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Intl\Countries;


/**
 * Affilitations for teams (e.g.: university, company).
 *
 * @ORM\Entity()
 * @ORM\Table(
 *     name="team_affiliation",
 *     options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4", "comment"="Affilitations for teams (e.g.: university, company)"},
 *     uniqueConstraints={
 *         @ORM\UniqueConstraint(name="externalid", columns={"externalid"}, options={"lengths": {190}}),
 *     })
 * @Serializer\VirtualProperty(
 *     "icpcId",
 *     exp="object.getAffilid()",
 *     options={@Serializer\Type("string")}
 * )
 * @Serializer\VirtualProperty(
 *     "shortName",
 *     exp="object.getShortname()",
 *     options={@Serializer\Type("string"), @Serializer\SerializedName("shortname"), @Serializer\Groups({"Nonstrict"})}
 * )
 * @UniqueEntity("externalid")
 */
class TeamAffiliation extends BaseApiEntity
{
    /**
     * @var int
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="affilid", length=4,
     *             options={"comment"="Team affiliation ID","unsigned"=true},
     *     nullable=false)
     * @Serializer\SerializedName("id")
     * @Serializer\Type("string")
     */
    protected $affilid;

    /**
     * @var string
     * @ORM\Column(type="string", name="externalid", length=255,
     *     options={"comment"="Team affiliation ID in an external system",
     *              "collation"="utf8mb4_bin"},
     *     nullable=true)
     * @Serializer\Exclude()
     */
    protected $externalid;

    /**
     * @var string
     * @ORM\Column(type="string", name="shortname", length=32,
     *     options={"comment"="Short descriptive name"}, nullable=false)
     * @Serializer\SerializedName("name")
     */
    private $shortname;

    /**
     * @var string
     * @ORM\Column(type="string", name="name", length=255,
     *     options={"comment"="Descriptive name"}, nullable=false)
     * @Serializer\SerializedName("formal_name")
     */
    private $name;

    /**
     * @var string
     * @ORM\Column(type="string", length=3, name="country",
     *     options={"comment"="ISO 3166-1 alpha-3 country code","fixed"=true},
     *     nullable=true)
     * @Serializer\Expose(if="context.getAttribute('config_service').get('show_flags')")
     * @Country()
     */
    private $country;

    /**
     * @var string
     * @ORM\Column(type="text", length=4294967295, name="comments",
     *     options={"comment"="Comments"},
     *     nullable=true)
     * @Serializer\Exclude()
     */
    private $comments;

    /**
     * @ORM\OneToMany(targetEntity="Team", mappedBy="affiliation")
     * @Serializer\Exclude()
     */
    private $teams;

    public function __construct()
    {
        $this->teams = new ArrayCollection();
    }

    public function setAffilid(int $affilid): TeamAffiliation
    {
        $this->affilid = $affilid;
        return $this;
    }


    public function getAffilid(): ?int
    {
        return $this->affilid;
    }

    public function setExternalid(?string $externalid): TeamAffiliation
    {
        $this->externalid = $externalid;
        return $this;
    }

    public function getExternalid(): ?string
    {
        return $this->externalid;
    }

    public function setShortname(string $shortname): TeamAffiliation
    {
        // Truncate shortname here to make the import more robust. TODO: is this the right place/behavior?
        $this->shortname = substr($shortname, 0, 32);
        return $this;
    }

    public function getShortname(): ?string
    {
        return $this->shortname;
    }

    public function setName(string $name): TeamAffiliation
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

    public function setCountry(string $country): TeamAffiliation
    {
        $this->country = $country;
        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function getCountryAlpha2(): ?string
    {
        return $this->country ? strtolower(Countries::getAlpha2Code($this->country)) : null;
    }

    public function getCountryName(): ?string
    {
        return $this->country ? Countries::getAlpha3Name($this->country) : null;
    }

    public function setComments(string $comments): TeamAffiliation
    {
        $this->comments = $comments;
        return $this;
    }

    public function getComments(): ?string
    {
        return $this->comments;
    }

    public function addTeam(Team $team): TeamAffiliation
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
}
