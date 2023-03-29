<?php declare(strict_types=1);
namespace App\Entity;

use App\Doctrine\Constants;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use RuntimeException;

/**
 * Files associated to an executable.
 */
#[ORM\Table(
    name: 'executable_file',
    options: [
        'collation' => 'utf8mb4_unicode_ci',
        'charset' => 'utf8mb4',
        'comment' => 'Files associated to an executable',
    ]
)]
#[ORM\Index(columns: ['immutable_execid'], name: 'immutable_execid')]
#[ORM\UniqueConstraint(
    name: 'rankindex',
    columns: ['immutable_execid', 'ranknumber']
)]
#[ORM\UniqueConstraint(
    name: 'filename',
    columns: ['immutable_execid', 'filename'],
    options: ['lengths' => [null, 190]]
)]
#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
class ExecutableFile
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(
        name: 'execfileid',
        type: 'integer',
        length: 4,
        nullable: false,
        options: ['comment' => 'Executable file ID', 'unsigned' => true]
    )]
    private int $execfileid;

    #[ORM\Column(
        name: 'filename',
        type: 'string',
        length: Constants::LENGTH_LIMIT_TINYTEXT,
        nullable: false,
        options: ['comment' => 'Filename as uploaded']
    )]
    private string $filename;

    #[ORM\Column(
        name: 'ranknumber',
        type: 'integer',
        nullable: false,
        options: ['comment' => 'Order of the executable files, zero-indexed', 'unsigned' => true]
    )]
    private int $rank;

    #[ORM\ManyToOne(targetEntity: ImmutableExecutable::class, inversedBy: 'files')]
    #[ORM\JoinColumn(name: 'immutable_execid', referencedColumnName: 'immutable_execid', onDelete: 'CASCADE')]
    private ImmutableExecutable $immutableExecutable;

    #[ORM\Column(
        name: 'file_content',
        type: 'blobtext',
        length: Constants::LENGTH_LIMIT_LONGTEXT,
        nullable: false,
        options: ['comment' => 'Full file content']
    )]
    private string $fileContent;

    #[ORM\Column(
        name: 'hash',
        type: 'string',
        length: 32,
        nullable: true,
        options: ['comment' => 'hash of the content']
    )]
    private string $hash;

    #[ORM\Column(
        name: 'is_executable',
        type: 'boolean',
        nullable: false,
        options: ['comment' => 'Whether this file gets an executable bit.', 'default' => 0]
    )]
    #[Serializer\Exclude]
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

    public function setImmutableExecutable(ImmutableExecutable $immutableExecutable = null): ExecutableFile
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

    #[ORM\PreRemove]
    public function disallowDelete(): never
    {
        throw new RuntimeException('An executable file cannot be deleted');
    }
}
