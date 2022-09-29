<?php declare(strict_types=1);
namespace App\Entity;

use App\Validator\Constraints\Country;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use OpenApi\Annotations as OA;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Affilitations for teams (e.g.: university, company).
 *
 * @ORM\Entity()
 * @ORM\Table(
 *     name="team_affiliation",
 *     options={"collation"="utf8mb4_unicode_ci", "charset"="utf8mb4", "comment"="Affilitations for teams (e.g.: university, company)"},
 *     uniqueConstraints={
 *         @ORM\UniqueConstraint(name="externalid", columns={"externalid"}, options={"lengths": {190}}),
 *     })
 * @Serializer\VirtualProperty(
 *     "shortName",
 *     exp="object.getShortname()",
 *     options={@Serializer\Type("string"), @Serializer\SerializedName("shortname"), @Serializer\Groups({"Nonstrict"})}
 * )
 * @UniqueEntity("externalid")
 */
class TeamAffiliation extends BaseApiEntity implements AssetEntityInterface
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="affilid", length=4,
     *             options={"comment"="Team affiliation ID","unsigned"=true},
     *     nullable=false)
     * @Serializer\SerializedName("id")
     * @Serializer\Type("string")
     */
    protected ?int $affilid = null;

    /**
     * @ORM\Column(type="string", name="externalid", length=255,
     *     options={"comment"="Team affiliation ID in an external system",
     *              "collation"="utf8mb4_bin"},
     *     nullable=true)
     * @Serializer\Exclude()
     */
    protected ?string $externalid = null;

    /**
     * @ORM\Column(type="string", name="icpcid", length=255,
     *     options={"comment"="External identifier from ICPC CMS",
     *              "collation"="utf8mb4_bin"},
     *     nullable=true)
     * @Serializer\SerializedName("icpc_id")
     * @OA\Property(nullable=true)
     */
    protected ?string $icpcid = null;

    /**
     * @ORM\Column(type="string", name="shortname", length=32,
     *     options={"comment"="Short descriptive name"}, nullable=false)
     * @Serializer\SerializedName("name")
     */
    private string $shortname;

    /**
     * @ORM\Column(type="string", name="name", length=255,
     *     options={"comment"="Descriptive name"}, nullable=false)
     * @Serializer\SerializedName("formal_name")
     */
    private string $name;

    /**
     * @ORM\Column(type="string", length=3, name="country",
     *     options={"comment"="ISO 3166-1 alpha-3 country code","fixed"=true},
     *     nullable=true)
     * @Serializer\Expose(if="context.getAttribute('config_service').get('show_flags')")
     * @Country()
     */
    private ?string $country = null;

    /**
     * @Assert\File(mimeTypes={"image/png","image/jpeg","image/svg+xml"}, mimeTypesMessage="Only PNG's, JPG's and SVG's are allowed")
     * @Serializer\Exclude()
     */
    private ?UploadedFile $logoFile = null;

    /**
     * @Serializer\Exclude()
     */
    private bool $clearLogo = false;

    /**
     * @ORM\Column(type="text", length=4294967295, name="internalcomments",
     *     options={"comment"="Internal comments (jury only)"},
     *     nullable=true)
     * @Serializer\Exclude()
     */
    private ?string $internalComments;

    /**
     * @ORM\OneToMany(targetEntity="Team", mappedBy="affiliation")
     * @Serializer\Exclude()
     */
    private Collection $teams;

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

    public function setIcpcid(?string $icpcid): TeamAffiliation
    {
        $this->icpcid = $icpcid;
        return $this;
    }

    public function getIcpcid(): ?string
    {
        return $this->icpcid;
    }

    public function setShortname(string $shortname): TeamAffiliation
    {
        // Truncate shortname here to make the import more robust. TODO: is this the right place/behavior?
        $this->shortname = mb_substr($shortname, 0, 32);
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

    public function setCountry(?string $country): TeamAffiliation
    {
        $this->country = $country;
        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setInternalComments(?string $comments): TeamAffiliation
    {
        $this->internalComments = $comments;
        return $this;
    }

    public function getInternalComments(): ?string
    {
        return $this->internalComments;
    }

    public function getLogoFile(): ?UploadedFile
    {
        return $this->logoFile;
    }

    public function setLogoFile(?UploadedFile $logoFile): TeamAffiliation
    {
        $this->logoFile = $logoFile;
        return $this;
    }

    public function isClearLogo(): bool
    {
        return $this->clearLogo;
    }

    public function setClearLogo(bool $clearLogo): TeamAffiliation
    {
        $this->clearLogo = $clearLogo;
        return $this;
    }

    public function addTeam(Team $team): TeamAffiliation
    {
        $this->teams[] = $team;
        return $this;
    }

    public function removeTeam(Team $team): void
    {
        $this->teams->removeElement($team);
    }

    public function getTeams(): Collection
    {
        return $this->teams;
    }

    public function getAssetProperties(): array
    {
        return ['logo'];
    }

    public function getAssetFile(string $property): ?UploadedFile
    {
        switch ($property) {
            case 'logo':
                return $this->getLogoFile();
        }

        return null;
    }

    public function isClearAsset(string $property): ?bool
    {
        switch ($property) {
            case 'logo':
                return $this->isClearLogo();
        }

        return null;
    }
}
