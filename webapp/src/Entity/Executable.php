<?php declare(strict_types=1);
namespace App\Entity;

use App\Validator\Constraints\Identifier;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\Validator\Constraints as Assert;
use ZipArchive;

/**
 * Compile, compare, and run script executable bundles.
 */
#[ORM\Entity]
#[ORM\Table(options: [
    'collation' => 'utf8mb4_unicode_ci',
    'charset' => 'utf8mb4',
    'comment' => 'Compile, compare, and run script executable bundles',
])]
class Executable
{
    #[ORM\Id]
    #[ORM\Column(length: 32, options: ['comment' => 'Executable ID (string)'])]
    #[Assert\NotBlank]
    #[Identifier]
    private string $execid;

    #[ORM\Column(nullable: true, options: ['comment' => 'Description of this executable'])]
    #[Assert\NotBlank]
    private ?string $description = null;

    #[ORM\Column(length: 32, options: ['comment' => 'Type of executable'])]
    #[Assert\Choice(['compare', 'compile', 'debug', 'run', 'generic_task'])]
    private string $type;

    #[ORM\OneToOne(targetEntity: ImmutableExecutable::class)]
    #[ORM\JoinColumn(name: 'immutable_execid', referencedColumnName: 'immutable_execid')]
    private ImmutableExecutable $immutableExecutable;

    /**
     * @var Collection<int, Language>
     */
    #[ORM\OneToMany(mappedBy: 'compile_executable', targetEntity: Language::class)]
    private Collection $languages;

    /**
     * @var Collection<int, Problem>
     */
    #[ORM\OneToMany(mappedBy: 'compare_executable', targetEntity: Problem::class)]
    private Collection $problems_compare;

    /**
     * @var Collection<int, Problem>
     */
    #[ORM\OneToMany(mappedBy: 'run_executable', targetEntity: Problem::class)]
    private Collection $problems_run;

    public function __construct()
    {
        $this->languages        = new ArrayCollection();
        $this->problems_compare = new ArrayCollection();
        $this->problems_run     = new ArrayCollection();
    }

    public function setExecid(string $execid): Executable
    {
        $this->execid = $execid;
        return $this;
    }

    public function getExecid(): string
    {
        return $this->execid;
    }

    public function setDescription(string $description): Executable
    {
        $this->description = $description;
        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getShortDescription(): string
    {
        return $this->getDescription();
    }

    public function setType(string $type): Executable
    {
        $this->type = $type;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function addLanguage(Language $language): Executable
    {
        $this->languages[] = $language;
        return $this;
    }

    /**
     * @return Collection<int, Language>
     */
    public function getLanguages(): Collection
    {
        return $this->languages;
    }

    public function addProblemsCompare(Problem $problemsCompare): Executable
    {
        $this->problems_compare[] = $problemsCompare;
        return $this;
    }

    /**
     * @return Collection<int, Problem>
     */
    public function getProblemsCompare(): Collection
    {
        return $this->problems_compare;
    }

    public function addProblemsRun(Problem $problemsRun): Executable
    {
        $this->problems_run[] = $problemsRun;
        return $this;
    }

    /**
     * @return Collection<int, Problem>
     */
    public function getProblemsRun(): Collection
    {
        return $this->problems_run;
    }

    public function setImmutableExecutable(ImmutableExecutable $immutableExecutable): Executable
    {
        $this->immutableExecutable = $immutableExecutable;
        return $this;
    }

    public function getImmutableExecutable(): ImmutableExecutable
    {
        return $this->immutableExecutable;
    }

    public function getZipfileContent(string $tempdir): string
    {
        $zipArchive = new ZipArchive();
        if (!($tempzipFile = tempnam($tempdir, "/executable-"))) {
            throw new ServiceUnavailableHttpException(null, 'Failed to create temporary file');
        }
        $zipArchive->open($tempzipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        /** @var ExecutableFile[] $files */
        $files = array_values($this->getImmutableExecutable()->getFiles()->toArray());
        usort($files, fn($a, $b) => $a->getRank() <=> $b->getRank());
        foreach ($files as $file) {
            $zipArchive->addFromString($file->getFilename(), $file->getFileContent());
            if ($file->isExecutable()) {
                // 100755 = regular file, executable
                $zipArchive->setExternalAttributesName(
                    $file->getFilename(),
                    ZipArchive::OPSYS_UNIX,
                    octdec('100755') << 16
                );
            }
        }
        $zipArchive->close();
        $zipFileContents = file_get_contents($tempzipFile);
        unlink($tempzipFile);
        return $zipFileContents;
    }

    /**
     * @param string[] $configScripts
     */
    public function checkUsed(array $configScripts): bool
    {
        if ($this->getType() === 'generic_task') {
            return true;
        }
        if (in_array($this->execid, $configScripts, true)) {
            return true;
        }
        if (count($this->problems_compare) || count($this->problems_run)) {
            return true;
        }
        foreach ($this->languages as $lang) {
            if ($lang->getAllowSubmit()) {
                return true;
            }
        }
        return false;
    }
}
