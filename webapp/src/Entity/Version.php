<?php declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

/**
 * Runner and compiler versions per language.
 */
#[ORM\Entity]
#[ORM\Table(options: [
    'collation' => 'utf8mb4_unicode_ci',
    'charset' => 'utf8mb4',
    'comment' => 'Runner and compiler versions per language.',
])]
class Version
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['comment' => 'Version ID', 'unsigned' => true])]
    #[Serializer\Exclude]
    protected ?int $versionid = null;

    #[ORM\Column(type: 'blobtext', nullable: true, options: ['comment' => 'Compiler version'])]
    private ?string $compilerVersion = null;

    #[ORM\Column(type: 'blobtext', nullable: true, options: ['comment' => 'Runner version'])]
    private ?string $runnerVersion = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true, options: ['comment' => 'Runner version command'])]
    private ?string $runnerVersionCommand = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true, options: ['comment' => 'Compiler version command'])]
    private ?string $compilerVersionCommand = null;

    #[ORM\ManyToOne(targetEntity: Language::class, inversedBy: "versions")]
    #[ORM\JoinColumn(name: 'langid', referencedColumnName: 'langid', onDelete: 'CASCADE')]
    private Language $language;

    #[ORM\ManyToOne(targetEntity: Judgehost::class)]
    #[ORM\JoinColumn(name: 'judgehostid', referencedColumnName: 'judgehostid', onDelete: 'SET NULL')]
    private Judgehost $judgehost;

    #[ORM\Column(
        type: 'decimal',
        precision: 32,
        scale: 9,
        nullable: true,
        options: ['comment' => 'Time this version command output was last updated', 'unsigned' => true]
    )]
    #[Serializer\Exclude]
    private string|float|null $lastChangedTime = null;

    #[ORM\Column(options: [
        'comment' => 'True if this version is active for this judgehost/language combination.',
        'default' => 1,
    ])]
    #[Serializer\Exclude]
    private bool $active = true;

    /**
     * @var Collection<int, JudgeTask>
     */
    #[ORM\OneToMany(mappedBy: 'version', targetEntity: JudgeTask::class)]
    #[Serializer\Exclude]
    private Collection $judgeTasks;

    public function __construct()
    {
        $this->judgeTasks = new ArrayCollection();
    }

    public function getVersionid(): ?int
    {
        return $this->versionid;
    }

    public function getCompilerVersionCommand(): ?string
    {
        return $this->compilerVersionCommand;
    }

    public function setCompilerVersionCommand(?string $compilerVersionCommand): Version
    {
        $this->compilerVersionCommand = $compilerVersionCommand;
        return $this;
    }

    public function getCompilerVersion(): ?string
    {
        return $this->compilerVersion;
    }

    public function setCompilerVersion(?string $compilerVersion): Version
    {
        $this->compilerVersion = $compilerVersion;
        return $this;
    }

    public function getRunnerVersion(): ?string
    {
        return $this->runnerVersion;
    }

    public function setRunnerVersion(?string $runnerVersion): Version
    {
        $this->runnerVersion = $runnerVersion;
        return $this;
    }

    public function getRunnerVersionCommand(): ?string
    {
        return $this->runnerVersionCommand;
    }

    public function setRunnerVersionCommand(?string $runnerVersionCommand): Version
    {
        $this->runnerVersionCommand = $runnerVersionCommand;
        return $this;
    }

    public function getLanguage(): Language
    {
        return $this->language;
    }

    public function setLanguage(Language $language): Version
    {
        $this->language = $language;
        return $this;
    }

    public function getJudgehost(): Judgehost
    {
        return $this->judgehost;
    }

    public function setJudgehost(Judgehost $judgehost): Version
    {
        $this->judgehost = $judgehost;
        return $this;
    }

    public function getLastChangedTime(): float|string|null
    {
        return $this->lastChangedTime;
    }

    public function setLastChangedTime(float|string|null $lastChangedTime): Version
    {
        $this->lastChangedTime = $lastChangedTime;
        return $this;
    }

    public function getActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): Version
    {
        $this->active = $active;
        return $this;
    }
}
