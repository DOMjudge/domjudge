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
#[ORM\Table(options: [
    'collation' => 'utf8mb4_unicode_ci',
    'charset' => 'utf8mb4',
    'comment' => 'Compile, compare, and run script executable bundles',
])]
#[ORM\Entity]
class Executable
{
    /**
     * @Assert\NotBlank()
     * @Identifier()
     */
    #[ORM\Id]
    #[ORM\Column(length: 32, options: ['comment' => 'Executable ID (string)'])]
    private string $execid;

    /**
     * @Assert\NotBlank()
     */
    #[ORM\Column(nullable: true, options: ['comment' => 'Description of this executable'])]
    private ?string $description = null;

    /**
     * @Assert\Choice({"compare", "compile", "debug", "run"})
     */
    #[ORM\Column(length: 32, options: ['comment' => 'Type of executable'])]
    private string $type;

    #[ORM\OneToOne(targetEntity: ImmutableExecutable::class)]
    #[ORM\JoinColumn(name: 'immutable_execid', referencedColumnName: 'immutable_execid')]
    private ImmutableExecutable $immutableExecutable;

    #[ORM\OneToMany(mappedBy: 'compile_executable', targetEntity: Language::class)]
    private Collection $languages;

    #[ORM\OneToMany(mappedBy: 'compare_executable', targetEntity: Problem::class)]
    private Collection $problems_compare;

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

    public function removeLanguage(Language $language): void
    {
        $this->languages->removeElement($language);
    }

    public function getLanguages(): Collection
    {
        return $this->languages;
    }

    public function addProblemsCompare(Problem $problemsCompare): Executable
    {
        $this->problems_compare[] = $problemsCompare;
        return $this;
    }

    public function removeProblemsCompare(Problem $problemsCompare): void
    {
        $this->problems_compare->removeElement($problemsCompare);
    }

    public function getProblemsCompare(): Collection
    {
        return $this->problems_compare;
    }

    public function addProblemsRun(Problem $problemsRun): Executable
    {
        $this->problems_run[] = $problemsRun;
        return $this;
    }

    public function removeProblemsRun(Problem $problemsRun): void
    {
        $this->problems_run->removeElement($problemsRun);
    }

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
}
