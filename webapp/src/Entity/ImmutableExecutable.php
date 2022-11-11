<?php declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

/**
 * Immutable wrapper for a collection of files for executable bundles.
 *
 * Note: this class should have no setters, since its data is immutable.
 *
 * @ORM\Entity()
 * @ORM\Table(
 *     name="immutable_executable",
 *     options={"collation"="utf8mb4_unicode_ci", "charset"="utf8mb4",
 *              "comment"="Immutable wrapper for a collection of files for executable bundles."}
 *     )
 * @ORM\HasLifecycleCallbacks()
 */
class ImmutableExecutable
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="immutable_execid", length=4,
     *     options={"comment"="ID","unsigned"=true}, nullable=false)
     */
    private int $immutable_execid;

    // TODO: Add more metadata like a link to parent and timestamp

    /**
     * @ORM\ManyToOne(targetEntity="User")
     * @ORM\JoinColumn(name="userid", referencedColumnName="userid", onDelete="SET NULL")
     * @Serializer\Exclude()
     */
    private ?User $user = null;

    /**
     * @ORM\OneToMany(targetEntity="ExecutableFile", mappedBy="immutableExecutable")
     * @ORM\OrderBy({"filename"="ASC"})
     * @Serializer\Exclude()
     */
    private ?Collection $files;

    /**
     * @ORM\Column(type="string", name="hash", length=32, options={"comment"="hash of the files"}, nullable=true)
     */
    private ?string $hash;

    /**
     * @param ExecutableFile[] $files
     */
    public function __construct(array $files, ?User $user = null)
    {
        $this->files = new ArrayCollection();
        foreach ($files as $file) {
            $this->files->add($file);
            $file->setImmutableExecutable($this);
        }
        $this->user = $user;
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

    protected function updateHash()
    {
        if ($this->files === null) {
            $this->hash = null;
            return;
        }
        $filesArray = $this->files->toArray();
        uasort($filesArray, fn(ExecutableFile $a, ExecutableFile $b) => strcmp($a->getFilename(), $b->getFilename()));
        $this->hash = md5(
            join(
                array_map(
                    fn(ExecutableFile $file) => $file->getHash() . $file->getFilename() . $file->isExecutable(),
                    $filesArray
                )
            )
        );
    }

    public function getFiles(): Collection
    {
        return $this->files;
    }

    public function getHash(): ?string
    {
        return $this->hash;
    }

    /**
     * @ORM\PreRemove()
     */
    public function disallowDelete(): void
    {
        throw new \RuntimeException('An immutable executable cannot be deleted');
    }
}
