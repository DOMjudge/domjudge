<?php declare(strict_types=1);
namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

/**
 * Immutable wrapper for a collection of files for executable bundles.
 *
 * @ORM\Entity()
 * @ORM\Table(
 *     name="immutable_executable",
 *     options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4",
 *              "comment"="Immutable wrapper for a collection of files for executable bundles."}
 *     )
 */
class ImmutableExecutable
{
    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="immutable_execid", length=4,
     *     options={"comment"="ID","unsigned"=true}, nullable=false)
     */
    private $immutable_execid;

    // TODO: Add more metadata like a link to parent and timestamp

    /**
     * @ORM\ManyToOne(targetEntity="User")
     * @ORM\JoinColumn(name="userid", referencedColumnName="userid", onDelete="SET NULL")
     * @Serializer\Exclude()
     */
    private $user;

    /**
     * @var ArrayCollection
     * @ORM\OneToMany(targetEntity="ExecutableFile", mappedBy="immutableExecutable")
     * @Serializer\Exclude()
     */
    private $files;

    /**
     * @var string
     * @ORM\Column(type="string", name="hash", length=32, options={"comment"="hash of the files"}, nullable=true)
     */
    private $hash;

    public function getImmutableExecId(): int
    {
        return $this->immutable_execid;
    }

    public function setUser(User $user): ImmutableExecutable
    {
        $this->user = $user;
        return $this;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function addFile(ExecutableFile $file): ImmutableExecutable
    {
        if ($this->files === null) {
            $this->files = new ArrayCollection();
        }
        $this->files->add($file);
        $this->updateHash();
        return $this;
    }

    public function updateHash()
    {
        if ($this->files === null) {
            $this->hash = null;
            return;
        }
        $this->hash = md5(
            join(array_map(
                    function (ExecutableFile $file) {
                        return $file->getHash();
                    },
                    $this->files->toArray())
            )
        );
    }

    public function getFiles(): Collection
    {
        return $this->files;
    }

    public function getHash() {
        return $this->hash;
    }
}
