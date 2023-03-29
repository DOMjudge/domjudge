<?php declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(
    name: 'external_source_warning',
    options: [
        'collation' => 'utf8mb4_unicode_ci',
        'charset' => 'utf8mb4',
        'comment' => 'Warnings for external sources',
    ]
)]
#[ORM\UniqueConstraint(
    name: 'hash',
    columns: ['extsourceid', 'hash'],
    options: ['lengths' => [null, 190]]
)]
#[ORM\Entity]
class ExternalSourceWarning
{
    final public const TYPE_UNSUPORTED_ACTION = 'unsupported-action';
    final public const TYPE_DATA_MISMATCH = 'data-mismatch';
    final public const TYPE_DEPENDENCY_MISSING = 'dependency-missing';
    final public const TYPE_ENTITY_NOT_FOUND = 'entity-not-found';
    final public const TYPE_ENTITY_SHOULD_NOT_EXIST = 'entity-should-not-exist';
    final public const TYPE_SUBMISSION_ERROR = 'submission-error';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(
        name: 'extwarningid',
        type: 'integer',
        length: 4,
        nullable: false,
        options: ['comment' => 'External source warning ID', 'unsigned' => true]
    )]
    private ?int $extwarningid = null;

    #[ORM\Column(
        name: 'last_event_id',
        type: 'string',
        length: 255,
        nullable: true,
        options: ['comment' => 'Last event ID this warning happened at']
    )]
    private ?string $lastEventId = null;

    #[ORM\Column(
        name: 'time',
        type: 'decimal',
        precision: 32,
        scale: 9,
        nullable: false,
        options: ['comment' => 'Time this warning happened last', 'unsigned' => true]
    )]
    private float $lastTime;

    #[ORM\Column(
        name: 'entity_type',
        type: 'string',
        length: 255,
        nullable: false,
        options: ['comment' => 'Type of the entity for this warning']
    )]
    private string $entityType;

    #[ORM\Column(
        name: 'entity_id',
        type: 'string',
        length: 255,
        nullable: false,
        options: ['comment' => 'ID of the entity for this warning']
    )]
    private string $entityId;

    #[ORM\Column(
        name: 'type',
        type: 'string',
        length: 255,
        nullable: false,
        options: ['comment' => 'Type of this warning']
    )]
    private string $type;

    #[ORM\Column(
        name: 'hash',
        type: 'string',
        length: 255,
        nullable: false,
        options: ['comment' => 'Hash of this warning. Unique within the source.']
    )]
    private string $hash;

    #[ORM\Column(
        name: 'content',
        type: 'json',
        options: ['comment' => 'JSON encoded content of the warning. Type-specific.']
    )]
    private array $content;

    #[ORM\ManyToOne(targetEntity: ExternalContestSource::class, inversedBy: 'warnings')]
    #[ORM\JoinColumn(name: 'extsourceid', referencedColumnName: 'extsourceid', onDelete: 'CASCADE')]
    private ExternalContestSource $externalContestSource;

    public function getExtwarningid(): ?int
    {
        return $this->extwarningid;
    }

    public function getLastEventId(): ?string
    {
        return $this->lastEventId;
    }

    public function setLastEventId(?string $lastEventId): ExternalSourceWarning
    {
        $this->lastEventId = $lastEventId;
        return $this;
    }

    public function getLastTime(): float
    {
        return $this->lastTime;
    }

    public function setLastTime(float $lastTime): ExternalSourceWarning
    {
        $this->lastTime = $lastTime;
        return $this;
    }

    public function getEntityType(): string
    {
        return $this->entityType;
    }

    public function setEntityType(string $entityType): ExternalSourceWarning
    {
        $this->entityType = $entityType;
        return $this;
    }

    public function getEntityId(): string
    {
        return $this->entityId;
    }

    public function setEntityId(string $entityId): ExternalSourceWarning
    {
        $this->entityId = $entityId;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): ExternalSourceWarning
    {
        $this->type = $type;
        return $this;
    }

    public function getHash(): string
    {
        return $this->hash;
    }

    public function setHash(string $hash): ExternalSourceWarning
    {
        $this->hash = $hash;
        return $this;
    }

    public function getContent(): array
    {
        return $this->content;
    }

    public function setContent(array $content): ExternalSourceWarning
    {
        $this->content = $content;
        return $this;
    }

    public function getExternalContestSource(): ExternalContestSource
    {
        return $this->externalContestSource;
    }

    public function setExternalContestSource(ExternalContestSource $externalContestSource): ExternalSourceWarning
    {
        $this->externalContestSource = $externalContestSource;
        return $this;
    }

    #[ORM\PrePersist]
    public function fillhash(): void
    {
        $this->setHash(static::calculateHash($this->getType(), $this->getEntityType(), $this->getEntityId()));
    }

    public static function calculateHash(string $type, string $entityType, string $enttiyId): string
    {
        return "$entityType-$enttiyId-$type";
    }

    public static function readableType(string $type): string
    {
        $mapping = [
            static::TYPE_UNSUPORTED_ACTION       => 'Unsupported action',
            static::TYPE_DATA_MISMATCH           => 'Data mismatch',
            static::TYPE_DEPENDENCY_MISSING      => 'Dependency missing',
            static::TYPE_ENTITY_NOT_FOUND        => 'Entity not found locally',
            static::TYPE_ENTITY_SHOULD_NOT_EXIST => 'Entity should not exist locally',
            static::TYPE_SUBMISSION_ERROR        => 'Submission error',
        ];
        return $mapping[$type];
    }
}
