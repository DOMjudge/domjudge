<?php declare(strict_types=1);
namespace App\Entity;

use App\Controller\API\AbstractRestController as ARC;
use App\DataTransferObject\ImageFile;
use App\Validator\Constraints\Country;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Affilitations for teams (e.g.: university, company).
 */
#[ORM\Entity]
#[ORM\Table(options: [
    'collation' => 'utf8mb4_unicode_ci',
    'charset' => 'utf8mb4',
    'comment' => 'Affilitations for teams (e.g.: university, company)',
])]
#[ORM\UniqueConstraint(name: 'externalid', columns: ['externalid'], options: ['lengths' => [190]])]
#[Serializer\VirtualProperty(
    name: 'shortName',
    exp: 'object.getShortname()',
    options: [
        new Serializer\Type('string'),
        new Serializer\SerializedName('shortname'),
        new Serializer\Groups(['Nonstrict']),
    ]
)]
#[UniqueEntity(fields: 'externalid')]
class TeamAffiliation extends BaseApiEntity implements
    HasExternalIdInterface,
    AssetEntityInterface,
    ExternalIdFromInternalIdInterface,
    PrefixedExternalIdInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['comment' => 'Team affiliation ID', 'unsigned' => true])]
    #[Serializer\SerializedName('affilid')]
    #[Serializer\Groups([ARC::GROUP_NONSTRICT])]
    protected ?int $affilid = null;

    #[ORM\Column(
        nullable: true,
        options: ['comment' => 'Team affiliation ID in an external system', 'collation' => 'utf8mb4_bin']
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

    #[ORM\Column(length: 32, options: ['comment' => 'Short descriptive name'])]
    #[Assert\NotBlank]
    #[Serializer\SerializedName('name')]
    private string $shortname;

    #[ORM\Column(options: ['comment' => 'Descriptive name'])]
    #[Assert\NotBlank]
    #[Serializer\SerializedName('formal_name')]
    private string $name;

    #[ORM\Column(
        length: 3,
        nullable: true,
        options: ['comment' => 'ISO 3166-1 alpha-3 country code', 'fixed' => true]
    )]
    #[Country]
    #[OA\Property(nullable: true)]
    #[Serializer\Expose(if: "context.getAttribute('config_service').get('show_flags')")]
    private ?string $country = null;

    #[Assert\File(mimeTypes: ['image/png', 'image/jpeg', 'image/svg+xml'], mimeTypesMessage: "Only PNG's, JPG's and SVG's are allowed")]
    #[Serializer\Exclude]
    private ?UploadedFile $logoFile = null;

    #[Serializer\Exclude]
    private bool $clearLogo = false;

    #[ORM\Column(
        name: 'internalcomments',
        type: 'text',
        nullable: true,
        options: ['comment' => 'Internal comments (jury only)']
    )]
    #[Serializer\Exclude]
    private ?string $internalComments = null;

    /**
     * @var Collection<int, Team>
     */
    #[ORM\OneToMany(mappedBy: 'affiliation', targetEntity: Team::class)]
    #[Serializer\Exclude]
    private Collection $teams;

    /**
     * This field gets filled by the team affiliation visitor with a data transfer
     * object that represents the country flag
     *
     * @var ImageFile[]
     */
    #[Serializer\SerializedName('country_flag')]
    #[Serializer\Type('array<App\DataTransferObject\ImageFile>')]
    #[Serializer\Exclude(if: 'object.getCountryFlagForApi() === []')]
    private array $countryFlagsForApi = [];

    // This field gets filled by the team affiliation visitor with a data transfer
    // object that represents the logo
    #[Serializer\Exclude]
    private ?ImageFile $logoForApi = null;

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

    /**
     * @return Collection<int, Team>
     */
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
        return match ($property) {
            'logo' => $this->getLogoFile(),
            default => null,
        };
    }

    public function isClearAsset(string $property): ?bool
    {
        return match ($property) {
            'logo' => $this->isClearLogo(),
            default => null,
        };
    }

    /**
     * @param ImageFile[] $countryFlagsForApi
     *
     * @return $this
     */
    public function setCountryFlagForApi(array $countryFlagsForApi = []): TeamAffiliation
    {
        $this->countryFlagsForApi = $countryFlagsForApi;
        return $this;
    }

    /**
     * @return ImageFile[]
     */
    public function getCountryFlagForApi(): array
    {
        return $this->countryFlagsForApi;
    }

    public function setLogoForApi(?ImageFile $logoForApi = null): TeamAffiliation
    {
        $this->logoForApi = $logoForApi;
        return $this;
    }

    /**
     * @return ImageFile[]
     */
    #[Serializer\VirtualProperty]
    #[Serializer\SerializedName('logo')]
    #[Serializer\Type('array<App\DataTransferObject\ImageFile>')]
    #[Serializer\Exclude(if: 'object.getLogoForApi() === []')]
    public function getLogoForApi(): array
    {
        return array_filter([$this->logoForApi]);
    }

    public function __toString(): string
    {
        return $this->getName() ?? $this->getShortname();
    }
}
