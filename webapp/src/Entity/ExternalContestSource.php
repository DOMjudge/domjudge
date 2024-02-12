<?php declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity]
#[ORM\Table(options: [
    'collation' => 'utf8mb4_unicode_ci',
    'charset' => 'utf8mb4',
    'comment' => 'Sources for external contests',
])]
#[ORM\UniqueConstraint(name: 'cid', columns: ['cid'])]
class ExternalContestSource
{
    final public const TYPE_CCS_API         = 'ccs-api';
    final public const TYPE_CONTEST_PACKAGE = 'contest-archive';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['comment' => 'External contest source ID', 'unsigned' => true])]
    private ?int $extsourceid = null;

    #[ORM\Column(options: ['comment' => 'Type of this contest source'])]
    private string $type;

    #[ORM\Column(options: ['comment' => 'Source for this contest'])]
    private string $source;

    #[ORM\Column(nullable: true, options: ['comment' => 'Username for this source, if any'])]
    private ?string $username = null;

    #[ORM\Column(nullable: true, options: ['comment' => 'Password for this source, if any'])]
    private ?string $password = null;

    #[ORM\Column(nullable: true, options: ['comment' => 'Last encountered event ID, if any'])]
    private ?string $lastEventId = null;

    #[ORM\Column(
        type: 'decimal',
        precision: 32,
        scale: 9,
        nullable: true,
        options: ['comment' => 'Time of last poll by event feed reader', 'unsigned' => true]
    )]
    private string|float|null $lastPollTime = null;

    #[ORM\Column(
        type: 'smallint',
        nullable: true,
        options: ['comment' => 'Last HTTP code received by event feed reader', 'unsigned' => true]
    )]
    public ?int $lastHTTPCode = null;

    #[ORM\ManyToOne(inversedBy: 'externalContestSources')]
    #[ORM\JoinColumn(name: 'cid', referencedColumnName: 'cid', onDelete: 'CASCADE')]
    private Contest $contest;

    /**
     * @var Collection<int, ExternalSourceWarning>
     */
    #[ORM\OneToMany(mappedBy: 'externalContestSource', targetEntity: ExternalSourceWarning::class)]
    private Collection $warnings;

    public function __construct()
    {
        $this->warnings = new ArrayCollection();
    }

    public function getExtsourceid(): ?int
    {
        return $this->extsourceid;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getReadableType(): string
    {
        return static::readableType($this->getType());
    }

    public function setType(string $type): ExternalContestSource
    {
        $this->type = $type;
        return $this;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): ExternalContestSource
    {
        while (str_ends_with($source, '/')) {
            $source = substr($source, 0, -1);
        }
        $this->source = $source;
        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(?string $username): ExternalContestSource
    {
        $this->username = $username;
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): ExternalContestSource
    {
        $this->password = $password;
        return $this;
    }

    public function getLastEventId(): ?string
    {
        return $this->lastEventId;
    }

    public function setLastEventId(?string $lastEventId): ExternalContestSource
    {
        $this->lastEventId = $lastEventId;
        return $this;
    }

    public function getLastPollTime(): string|float|null
    {
        return $this->lastPollTime;
    }

    public function setLastPollTime(string|float|null $lastPollTime): ExternalContestSource
    {
        $this->lastPollTime = $lastPollTime;
        return $this;
    }

    public function setContest(?Contest $contest = null): ExternalContestSource
    {
        $this->contest = $contest;
        return $this;
    }

    public function getContest(): Contest
    {
        return $this->contest;
    }

    /**
     * @return Collection<int, ExternalSourceWarning>
     */
    public function getExternalSourceWarnings(): Collection
    {
        return $this->warnings;
    }

    public function addExternalSourceWarning(ExternalSourceWarning $warning): self
    {
        if (!$this->warnings->contains($warning)) {
            $this->warnings[] = $warning;
        }

        return $this;
    }

    public function getShortDescription(): string
    {
        return $this->getSource();
    }

    public function setLastHTTPCode(?int $lastHTTPCode): ExternalContestSource
    {
        $this->lastHTTPCode = $lastHTTPCode;
        return $this;
    }

    public function getLastHTTPCode(): ?int
    {
        return $this->lastHTTPCode;
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        switch ($this->getType()) {
            case static::TYPE_CCS_API:
                if (!filter_var($this->getSource(), FILTER_VALIDATE_URL)) {
                    $context
                        ->buildViolation('This is not a valid URL')
                        ->atPath('source')
                        ->addViolation();
                }
                // Note: we could validate we have a valid CCS endpoint by checking the actual URL,
                // but that seems overkill
                break;
            case static::TYPE_CONTEST_PACKAGE:
                // Clear username and password
                $this
                    ->setUsername(null)
                    ->setPassword(null);

                // Check if directory exists

                if (!is_dir($this->getSource())) {
                    $context
                        ->buildViolation('This directory does not exist')
                        ->atPath('source')
                        ->addViolation();
                }
                break;
        }
    }

    public static function readableType(string $type): string
    {
        $mapping = [
            ExternalContestSource::TYPE_CCS_API         => 'CCS API (URL)',
            ExternalContestSource::TYPE_CONTEST_PACKAGE => 'Contest package (directory)',
        ];
        return $mapping[$type];
    }
}
