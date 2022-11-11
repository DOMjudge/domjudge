<?php declare(strict_types=1);
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

/**
 * Files associated to an executable.
 *
 * @ORM\Entity()
 * @ORM\Table(
 *     name="executable_file",
 *     options={"collation"="utf8mb4_unicode_ci", "charset"="utf8mb4", "comment"="Files associated to an executable"},
 *     indexes={@ORM\Index(name="immutable_execid", columns={"immutable_execid"})},
 *     uniqueConstraints={
 *         @ORM\UniqueConstraint(name="rankindex", columns={"immutable_execid", "ranknumber"}),
 *         @ORM\UniqueConstraint(name="filename", columns={"immutable_execid", "filename"}, options={"lengths": {NULL, 190}})
 *     })
 * @ORM\HasLifecycleCallbacks()
 */
class ExecutableFile
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="execfileid", length=4,
     *     options={"comment"="Executable file ID","unsigned"=true},
     *     nullable=false)
     */
    private int $execfileid;

    /**
     * @ORM\Column(type="string", name="filename", length=255, options={"comment"="Filename as uploaded"}, nullable=false)
     */
    private string $filename;

    /**
     * @ORM\Column(type="integer", name="ranknumber",
     *     options={"comment"="Order of the executable files, zero-indexed", "unsigned"=true},
     *     nullable=false)
     */
    private int $rank;

    /**
     * @ORM\ManyToOne(targetEntity="ImmutableExecutable", inversedBy="files")
     * @ORM\JoinColumn(name="immutable_execid", referencedColumnName="immutable_execid", onDelete="CASCADE")
     */
    private ImmutableExecutable $immutableExecutable;

    /**
     * @ORM\Column(type="blobtext", name="file_content", length=4294967295,
     *     options={"comment"="Full file content"}, nullable=false)
     */
    private string $fileContent;

    /**
     * @ORM\Column(type="string", name="hash", length=32, options={"comment"="hash of the content"}, nullable=true)
     */
    private string $hash;

    /**
     * @ORM\Column(type="boolean", name="is_executable",
     *     options={"comment"="Whether this file gets an executable bit.",
     *              "default"="0"},
     *     nullable=false)
     * @Serializer\Exclude()
     */
    private bool $isExecutable = false;

    public function getExecFileId(): int
    {
        return $this->execfileid;
    }

    public function setExecFileId(int $execfileid): ExecutableFile
    {
        $this->execfileid = $execfileid;

        return $this;
    }

    public function setFilename(string $filename): ExecutableFile
    {
        $this->filename = $filename;
        return $this;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function setRank(int $rank): ExecutableFile
    {
        $this->rank = $rank;
        return $this;
    }

    public function getRank(): int
    {
        return $this->rank;
    }

    public function setImmutableExecutable(ImmutableExecutable $immutableExecutable = null)
    {
        $this->immutableExecutable = $immutableExecutable;
        return $this;
    }

    public function getImmutableExecutable(): ImmutableExecutable
    {
        return $this->immutableExecutable;
    }

    public function setFileContent(string $fileContent): ExecutableFile
    {
        $this->fileContent = $fileContent;
        $this->hash = md5($fileContent);
        return $this;
    }

    public function getHash(): string
    {
        return $this->hash;
    }

    public function getFileContent(): string
    {
        return $this->fileContent;
    }

    public function setIsExecutable(bool $isExecutable): ExecutableFile
    {
        $this->isExecutable = $isExecutable;
        return $this;
    }

    public function isExecutable(): bool
    {
        return $this->isExecutable;
    }

    /**
     * @ORM\PreRemove()
     */
    public function disallowDelete(): void
    {
        throw new \RuntimeException('An executable file cannot be deleted');
    }
}
