<?php declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use RuntimeException;

/**
 * Immutable wrapper for a collection of files for executable bundles.
 *
 * Note: this class should have no setters, since its data is immutable.
 */
#[ORM\Entity]
#[ORM\Table(options: [
    'collation' => 'utf8mb4_unicode_ci',
    'charset' => 'utf8mb4',
    'comment' => 'Immutable wrapper for a collection of files for executable bundles.',
])]
#[ORM\HasLifecycleCallbacks]
class ImmutableExecutable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['comment' => 'ID', 'unsigned' => true])]
    private int $immutable_execid;

    // TODO: Add more metadata like a link to parent and timestamp
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'userid', referencedColumnName: 'userid', onDelete: 'SET NULL')]
    #[Serializer\Exclude]
    private ?User $user = null;

    /**
     * @var Collection<int, ExecutableFile>|null
     */
    #[ORM\OneToMany(mappedBy: 'immutableExecutable', targetEntity: ExecutableFile::class)]
    #[ORM\OrderBy(['filename' => 'ASC'])]
    #[Serializer\Exclude]
    private ?Collection $files;

    #[ORM\Column(length: 32, nullable: true, options: ['comment' => 'hash of the files'])]
    private ?string $hash;

    /**
     * @param ExecutableFile[] $files
     */
    public function __construct(array $files)
    {
        $this->files = new ArrayCollection();
        foreach ($files as $file) {
            $this->files->add($file);
            $file->setImmutableExecutable($this);
        }
        $this->updateHash();
    }

    public function getImmutableExecId(): int
    {
        return $this->immutable_execid;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    protected function updateHash(): void
    {
        if ($this->files === null) {
            $this->hash = null;
            return;
        }
        $filesArray = $this->files->toArray();
        uasort($filesArray, fn(ExecutableFile $a, ExecutableFile $b) => strcmp($a->getFilename(), $b->getFilename()));
        $this->hash = md5(
            implode(
                array_map(
                    fn(ExecutableFile $file) => $file->getHash() . $file->getFilename() . $file->isExecutable(),
                    $filesArray
                )
            )
        );
    }

    /**
     * @return Collection<int, ExecutableFile>
     */
    public function getFiles(): Collection
    {
        return $this->files;
    }

    public function getHash(): ?string
    {
        return $this->hash;
    }

    #[ORM\PreRemove]
    public function disallowDelete(): never
    {
        throw new RuntimeException('An immutable executable cannot be deleted');
    }
}
