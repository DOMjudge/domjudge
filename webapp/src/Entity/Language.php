<?php declare(strict_types=1);
namespace App\Entity;

use App\Controller\API\AbstractRestController as ARC;
use App\DataTransferObject\Command;
use App\Validator\Constraints\Identifier;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Programming languages in which teams can submit solutions.
 */
#[ORM\Entity]
#[ORM\Table(options: [
    'collation' => 'utf8mb4_unicode_ci',
    'charset' => 'utf8mb4',
    'comment' => 'Programming languages in which teams can submit solutions',
])]
#[ORM\Index(columns: ['compile_script'], name: 'compile_script')]
#[ORM\UniqueConstraint(name: 'externalid', columns: ['externalid'], options: ['lengths' => [190]])]
#[UniqueEntity(fields: 'langid')]
#[UniqueEntity(fields: 'externalid')]
class Language extends BaseApiEntity implements
    HasExternalIdInterface,
    ExternalIdFromInternalIdInterface
{
    #[ORM\Id]
    #[ORM\Column(length: 32, options: ['comment' => 'Language ID (string)'])]
    #[Assert\NotBlank]
    #[Assert\NotEqualTo('add')]
    #[Identifier]
    #[Serializer\Exclude]
    protected ?string $langid = null;

    #[ORM\Column(nullable: true, options: ['comment' => 'Language ID to expose in the REST API'])]
    #[Serializer\SerializedName('id')]
    #[Serializer\Groups([ARC::GROUP_DEFAULT, ARC::GROUP_NONSTRICT])]
    protected ?string $externalid = null;

    #[ORM\Column(options: ['comment' => 'Descriptive language name'])]
    #[Assert\NotBlank]
    #[Serializer\Groups([ARC::GROUP_DEFAULT, ARC::GROUP_NONSTRICT])]
    private string $name = '';

    /**
     * @var string[]
     */
    #[ORM\Column(
        type: 'json',
        nullable: true,
        options: ['comment' => 'List of recognized extensions (JSON encoded)']
    )]
    #[Assert\NotBlank]
    #[Assert\All([
        new Assert\Regex([
            'pattern' => '/^[^.]/',
            'message' => 'The extension should not start with a dot.'
        ])
    ])]
    #[Serializer\Type('array<string>')]
    private array $extensions = [];

    #[ORM\Column(options: [
        'comment' => 'Whether to filter the files passed to the compiler by the extension list.',
        'default' => 1,
    ]
    )]
    #[Serializer\Groups([ARC::GROUP_NONSTRICT])]
    private bool $filterCompilerFiles = true;

    #[ORM\Column(options: [
        'comment' => 'Are submissions accepted in this language?',
        'default' => 1,
    ]
    )]
    #[Serializer\Exclude]
    private bool $allowSubmit = true;

    #[ORM\Column(options: [
        'comment' => 'Are submissions in this language judged?',
        'default' => 1,
    ])]
    #[Serializer\Groups([ARC::GROUP_NONSTRICT])]
    private bool $allowJudge = true;

    #[ORM\Column(options: [
        'comment' => 'Language-specific factor multiplied by problem run times',
        'default' => 1,
    ]
    )]
    #[Assert\Positive]
    #[Assert\NotBlank]
    #[Serializer\Type('double')]
    #[Serializer\Groups([ARC::GROUP_NONSTRICT])]
    private float $timeFactor = 1;

    #[ORM\Column(options: [
        'comment' => 'Whether submissions require a code entry point to be specified.',
        'default' => 0,
    ])]
    #[Serializer\SerializedName('entry_point_required')]
    private bool $require_entry_point = false;

    #[ORM\Column(
        nullable: true,
        options: ['comment' => 'The description used in the UI for the entry point field.']
    )]
    #[OA\Property(nullable: true)]
    #[Serializer\SerializedName('entry_point_name')]
    private ?string $entry_point_description = null;

    #[ORM\ManyToOne(inversedBy: 'languages')]
    #[ORM\JoinColumn(name: 'compile_script', referencedColumnName: 'execid', onDelete: 'SET NULL')]
    #[Serializer\Exclude]
    private ?Executable $compile_executable = null;

    /**
     * @var Collection<int, Submission>
     */
    #[ORM\OneToMany(mappedBy: 'language', targetEntity: Submission::class)]
    #[Serializer\Exclude]
    private Collection $submissions;

    /**
     * @var Collection<int, Version>
     */
    #[ORM\OneToMany(mappedBy: 'language', targetEntity: Version::class)]
    #[Serializer\Exclude]
    private Collection $versions;

    #[ORM\Column(type: 'blobtext', nullable: true, options: ['comment' => 'Compiler version'])]
    #[Serializer\Exclude]
    private ?string $compilerVersion = null;

    #[ORM\Column(type: 'blobtext', nullable: true, options: ['comment' => 'Runner version'])]
    #[Serializer\Exclude]
    private ?string $runnerVersion = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true, options: ['comment' => 'Compiler version command'])]
    #[Serializer\Exclude]
    private ?string $compilerVersionCommand = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true, options: ['comment' => 'Runner version command'])]
    #[Serializer\Exclude]
    private ?string $runnerVersionCommand = null;

    /**
     * @param Collection<int, Version> $versions
     */
    public function setVersions(Collection $versions): Language
    {
        $this->versions = $versions;
        return $this;
    }

    /**
     * @return Collection<int, Version>
     */
    public function getVersions(): Collection
    {
        return $this->versions;
    }

    public function getCompilerVersion(): ?string
    {
        return $this->compilerVersion;
    }

    public function setCompilerVersion(?string $compilerVersion): Language
    {
        $this->compilerVersion = $compilerVersion;
        return $this;
    }

    public function getRunnerVersion(): ?string
    {
        return $this->runnerVersion;
    }

    public function setRunnerVersion(?string $runnerVersion): Language
    {
        $this->runnerVersion = $runnerVersion;
        return $this;
    }

    public function getCompilerVersionCommand(): ?string
    {
        return $this->compilerVersionCommand;
    }

    public function setCompilerVersionCommand(?string $compilerVersionCommand): Language
    {
        $this->compilerVersionCommand = $compilerVersionCommand;
        return $this;
    }

    public function getRunnerVersionCommand(): ?string
    {
        return $this->runnerVersionCommand;
    }

    public function setRunnerVersionCommand(?string $runnerVersionCommand): Language
    {
        $this->runnerVersionCommand = $runnerVersionCommand;
        return $this;
    }

    #[OA\Property(nullable: true)]
    #[Serializer\VirtualProperty]
    #[Serializer\SerializedName('compile_executable_hash')]
    #[Serializer\Type('string')]
    #[Serializer\Groups([ARC::GROUP_NONSTRICT])]
    public function getCompileExecutableHash(): ?string
    {
        return $this->compile_executable?->getImmutableExecutable()->getHash();
    }

    #[Serializer\VirtualProperty]
    #[Serializer\SerializedName('compiler')]
    #[Serializer\Exclude(if:'object.getCompilerVersionCommand() == ""')]
    public function getCompilerData(): Command
    {
        $ret = new Command();
        if (!empty($this->getCompilerVersionCommand())) {
            $ret->versionCommand = $this->getCompilerVersionCommand();
            if (!empty($this->getCompilerVersion())) {
                $ret->version = $this->getCompilerVersion();
            }
        }
        return $ret;
    }

    #[Serializer\VirtualProperty]
    #[Serializer\SerializedName('runner')]
    #[Serializer\Exclude(if:'object.getRunnerVersionCommand() == ""')]
    public function getRunnerData(): Command
    {
        $ret = new Command();
        if (!empty($this->getRunnerVersionCommand())) {
            $ret->versionCommand = $this->getRunnerVersionCommand();
            if (!empty($this->getRunnerVersion())) {
                $ret->version = $this->getRunnerVersion();
            }
        }
        return $ret;
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

    public function setExternalid(?string $externalid): Language
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

    /**
     * @param string[] $extensions
     */
    public function setExtensions(array $extensions): Language
    {
        $this->extensions = $extensions;
        return $this;
    }

    /**
     * @return string[]
     */
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
        $this->versions = new ArrayCollection();
    }

    public function addSubmission(Submission $submission): Language
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

    public function getAceLanguage(): string
    {
        return match ($this->getLangid()) {
            'adb' => 'ada',
            'bash' => 'sh',
            'c', 'cpp', 'cxx' => 'c_cpp',
            'hs' => 'haskell',
            'kt' => 'kotlin',
            'pas' => 'pascal',
            'pl' => 'perl',
            'plg' => 'prolog',
            'py2', 'py3' => 'python',
            'rb' => 'ruby',
            'rs' => 'rust',
            default => $this->getLangid(),
        };
    }
}
