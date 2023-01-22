<?php declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * @ORM\Entity()
 * @ORM\Table(
 *     name="external_contest_source",
 *     options={"collation"="utf8mb4_unicode_ci", "charset"="utf8mb4",
 *              "comment"="Sources for external contests"},
 *     uniqueConstraints={
 *         @ORM\UniqueConstraint(name="cid", columns={"cid"})
 *     })
 */
class ExternalContestSource
{
    public const TYPE_CCS_API         = 'ccs-api';
    public const TYPE_CONTEST_PACKAGE = 'contest-archive';

    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="extsourceid",
     *     options={"comment"="External contest source ID", "unsigned"=true},
     *     nullable=false, length=4)
     */
    private ?int $extsourceid = null;

    /**
     * @ORM\Column(type="string", name="type", length=255,
     *     options={"comment"="Type of this contest source"},
     *     nullable=false)
     */
    private string $type;

    /**
     * @ORM\Column(type="string", name="source", length=255,
     *     options={"comment"="Source for this contest"},
     *     nullable=false)
     */
    private string $source;

    /**
     * @ORM\Column(type="string", name="username", length=255,
     *     options={"comment"="Username for this source, if any"},
     *     nullable=true)
     */
    private ?string $username = null;

    /**
     * @ORM\Column(type="string", name="password", length=255,
     *     options={"comment"="Password for this source, if any"},
     *     nullable=true)
     */
    private ?string $password = null;

    /**
     * @ORM\Column(type="string", name="last_event_id", length=255,
     *     options={"comment"="Last encountered event ID, if any"},
     *     nullable=true)
     */
    private ?string $lastEventId = null;

    /**
     * @ORM\Column(type="decimal", precision=32, scale=9, name="last_poll_time",
     *     options={"comment"="Time of last poll by event feed reader",
     *              "unsigned"=true},
     *     nullable=true)
     */
    private ?float $lastPollTime = null;

    /**
     * @ORM\ManyToOne(targetEntity="Contest", inversedBy="externalContestSources")
     * @ORM\JoinColumn(name="cid", referencedColumnName="cid", onDelete="CASCADE")
     */
    private Contest $contest;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\ExternalSourceWarning", mappedBy="externalContestSource")
     */
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

    public function getLastPollTime(): ?float
    {
        return $this->lastPollTime;
    }

    public function setLastPollTime(?float $lastPollTime): ExternalContestSource
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
     * @return Collection|ExternalSourceWarning[]
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

    public function removeExternalSourceWarning(ExternalSourceWarning $warning): self
    {
        if ($this->warnings->contains($warning)) {
            $this->warnings->removeElement($warning);
        }

        return $this;
    }

    public function getShortDescription(): string
    {
        return $this->getSource();
    }

    /**
     * @Assert\Callback
     */
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
