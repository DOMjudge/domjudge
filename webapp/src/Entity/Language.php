<?php declare(strict_types=1);
namespace App\Entity;

use App\Validator\Constraints\Identifier;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use OpenApi\Annotations as OA;
use JMS\Serializer\Annotation as Serializer;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Programming languages in which teams can submit solutions.
 *
 * @ORM\Entity()
 * @ORM\Table(
 *     name="language",
 *     options={"collation"="utf8mb4_unicode_ci", "charset"="utf8mb4", "comment"="Programming languages in which teams can submit solutions"},
 *     indexes={@ORM\Index(name="compile_script", columns={"compile_script"})},
 *     uniqueConstraints={
 *         @ORM\UniqueConstraint(name="externalid", columns={"externalid"}, options={"lengths": {190}}),
 *     })
 * @UniqueEntity("langid")
 * @UniqueEntity("externalid")
 */
class Language extends BaseApiEntity
{
    /**
     * @ORM\Id
     * @ORM\Column(type="string", name="langid", length=32, options={"comment"="Language ID (string)"}, nullable=false)
     * @Serializer\Exclude()
     * @Assert\NotBlank()
     * @Assert\NotEqualTo("add")
     * @Identifier()
     */
    protected ?string $langid = null;

    /**
     * @ORM\Column(type="string", name="externalid", length=255, nullable=true,
     *     options={"comment"="Language ID to expose in the REST API"})
     * @Serializer\SerializedName("id")
     * @Serializer\Groups({"Default", "Nonstrict"})
     */
    protected ?string $externalid = null;

    /**
     * @ORM\Column(type="string", name="name", length=255, options={"comment"="Descriptive language name"}, nullable=false)
     * @Serializer\Groups({"Default", "Nonstrict"})
     * @Assert\NotBlank()
     */
    private string $name = '';

    /**
     * @var string[]
     * @ORM\Column(type="json", length=4294967295, name="extensions",
     *     options={"comment"="List of recognized extensions (JSON encoded)"},
     *     nullable=true)
     * @Serializer\Type("array<string>")
     * @Assert\NotBlank()
     */
    private array $extensions = [];

    /**
     * @ORM\Column(type="boolean", name="filter_compiler_files",
     *     options={"comment"="Whether to filter the files passed to the compiler by the extension list.",
     *              "default"="1"},
     *     nullable=false)
     * @Serializer\Groups({"Nonstrict"})
     */
    private bool $filterCompilerFiles = true;

    /**
     * @ORM\Column(type="boolean", name="allow_submit",
     *     options={"comment"="Are submissions accepted in this language?","default"="1"},
     *     nullable=false)
     * @Serializer\Exclude()
     */
    private bool $allowSubmit = true;

    /**
     * @ORM\Column(type="boolean", name="allow_judge",
     *     options={"comment"="Are submissions in this language judged?","default"="1"},
     *     nullable=false)
     * @Serializer\Groups({"Nonstrict"})
     */
    private bool $allowJudge = true;

    /**
     * @ORM\Column(type="float", name="time_factor",
     *     options={"comment"="Language-specific factor multiplied by problem run times","default"="1"},
     *     nullable=false)
     * @Serializer\Type("double")
     * @Serializer\Groups({"Nonstrict"})
     * @Assert\GreaterThan(0)
     * @Assert\NotBlank()
     */
    private float $timeFactor = 1;

    /**
     * @ORM\Column(type="boolean", name="require_entry_point",
     *     options={"comment"="Whether submissions require a code entry point to be specified.","default":"0"},
     *     nullable=false)
     * @Serializer\SerializedName("entry_point_required")
     */
    private bool $require_entry_point = false;

    /**
     * @ORM\Column(type="string", name="entry_point_description",
     *     options={"comment"="The description used in the UI for the entry point field."},
     *     nullable=true)
     * @Serializer\SerializedName("entry_point_name")
     * @OA\Property(nullable=true)
     */
    private ?string $entry_point_description = null;

    /**
     * @ORM\ManyToOne(targetEntity="Executable", inversedBy="languages")
     * @ORM\JoinColumn(name="compile_script", referencedColumnName="execid", onDelete="SET NULL")
     * @Serializer\Exclude()
     */
    private ?Executable $compile_executable;

    /**
     * @ORM\OneToMany(targetEntity="Submission", mappedBy="language")
     * @Serializer\Exclude()
     */
    private Collection $submissions;

    /**
     * @Serializer\VirtualProperty
     * @Serializer\Type("string")
     * @Serializer\SerializedName("compile_executable_hash")
     * @OA\Property(nullable=true)
     */
    public function getCompileExecutableHash(): ?string
    {
        if ($this->compile_executable !== null) {
            return $this->compile_executable->getImmutableExecutable()->getHash();
        } else {
            return null;
        }
    }

    public function setLangid(string $langid): Language
    {
        $this->langid = $langid;
        return $this;
    }

    public function getLangid(): ?string
    {
        return $this->langid;
    }

    public function setExternalid(string $externalid): Language
    {
        $this->externalid = $externalid;
        return $this;
    }

    public function getExternalid(): ?string
    {
        return $this->externalid;
    }

    public function setName(string $name): Language
    {
        $this->name = $name;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getShortDescription(): string
    {
        return $this->getName();
    }

    public function setExtensions(array $extensions): Language
    {
        $this->extensions = $extensions;
        return $this;
    }

    public function getExtensions(): array
    {
        return $this->extensions;
    }

    public function setFilterCompilerFiles(bool $filterCompilerFiles): Language
    {
        $this->filterCompilerFiles = $filterCompilerFiles;
        return $this;
    }

    public function getFilterCompilerFiles(): bool
    {
        return $this->filterCompilerFiles;
    }

    public function setAllowSubmit(bool $allowSubmit): Language
    {
        $this->allowSubmit = $allowSubmit;
        return $this;
    }

    public function getAllowSubmit(): bool
    {
        return $this->allowSubmit;
    }

    public function setAllowJudge(bool $allowJudge): Language
    {
        $this->allowJudge = $allowJudge;
        return $this;
    }

    public function getAllowJudge(): bool
    {
        return $this->allowJudge;
    }

    public function setTimeFactor(float $timeFactor): Language
    {
        $this->timeFactor = $timeFactor;
        return $this;
    }

    public function getTimeFactor(): float
    {
        return $this->timeFactor;
    }

    public function setRequireEntryPoint(bool $requireEntryPoint): Language
    {
        $this->require_entry_point = $requireEntryPoint;
        return $this;
    }

    public function getRequireEntryPoint(): bool
    {
        return $this->require_entry_point;
    }

    public function setEntryPointDescription(?string $entryPointDescription): Language
    {
        $this->entry_point_description = $entryPointDescription;
        return $this;
    }

    public function getEntryPointDescription(): ?string
    {
        return $this->entry_point_description;
    }

    public function setCompileExecutable(?Executable $compileExecutable = null): Language
    {
        $this->compile_executable = $compileExecutable;
        return $this;
    }

    public function getCompileExecutable(): ?Executable
    {
        return $this->compile_executable;
    }

    public function __construct()
    {
        $this->submissions = new ArrayCollection();
    }

    public function addSubmission(Submission $submission): Language
    {
        $this->submissions[] = $submission;
        return $this;
    }

    public function removeSubmission(Submission $submission)
    {
        $this->submissions->removeElement($submission);
    }

    public function getSubmissions(): Collection
    {
        return $this->submissions;
    }

    public function getAceLanguage(): string
    {
        switch ($this->getLangid()) {
            case 'c':
            case 'cpp':
            case 'cxx':
                return 'c_cpp';
            case 'pas':
                return 'pascal';
            case 'hs':
                return 'haskell';
            case 'pl':
                return 'perl';
            case 'bash':
                return 'sh';
            case 'py2':
            case 'py3':
                return 'python';
            case 'adb':
                return 'ada';
            case 'plg':
                return 'prolog';
            case 'rb':
                return 'ruby';
            case 'rs':
                return 'rust';
        }
        return $this->getLangid();
    }
}
