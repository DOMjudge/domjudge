<?php declare(strict_types=1);
namespace App\Entity;

use App\Doctrine\DBAL\Types\InternalErrorStatusType;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

/**
 * Log of judgehost internal errors.
 *
 * @ORM\Entity()
 * @ORM\Table(
 *     name="internal_error",
 *     options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4", "comment"="Log of judgehost internal errors"},
 *     indexes={
 *         @ORM\Index(name="judgingid", columns={"judgingid"}),
 *         @ORM\Index(name="cid", columns={"cid"})
 *     })
 */
class InternalError
{
    /**
     * @var int
     * @ORM\Id
     * @ORM\Column(type="integer", name="errorid", length=4,
     *     options={"comment"="Internal error ID","unsigned"=true},
     *     nullable=false)
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $errorid;

    /**
     * @var string
     * @ORM\Column(type="string", length=255, name="description",
     *     options={"comment"="Description of the error"},
     *     nullable=false)
     */
    private $description;

    /**
     * @var string
     * @ORM\Column(type="text", length=65535, name="judgehostlog",
     *     options={"comment"="Last N lines of the judgehost log"},
     *     nullable=false)
     */
    private $judgehostlog;

    /**
     * @var double
     * @ORM\Column(type="decimal", precision=32, scale=9, name="time",
     *     options={"comment"="Timestamp of the internal error", "unsigned"=true},
     *     nullable=false)
     */
    private $time;

    /**
     * @var array
     * @ORM\Column(type="json", length=65535, name="disabled",
     *     options={"comment"="Disabled stuff, JSON-encoded"},
     *     nullable=false)
     */
    private $disabled;

    /**
     * @var string
     * @ORM\Column(type="internal_error_status", name="status",
     *     options={"comment"="Status of internal error","default"="open"},
     *     nullable=false)
     */
    private $status = InternalErrorStatusType::STATUS_OPEN;

    /**
     * @ORM\ManyToOne(targetEntity="Contest", inversedBy="internal_errors")
     * @ORM\JoinColumn(name="cid", referencedColumnName="cid", onDelete="SET NULL")
     */
    private $contest;

    /**
     * @ORM\ManyToOne(targetEntity="Judging")
     * @ORM\JoinColumn(name="judgingid", referencedColumnName="judgingid", onDelete="SET NULL")
     */
    private $judging;

    /**
     * @ORM\OneToMany(targetEntity="Judging", mappedBy="internalError")
     * @Serializer\Exclude()
     */
    private $affectedJudgings;

    public function setErrorid(int $errorid): InternalError
    {
        $this->errorid = $errorid;
        return $this;
    }

    public function getErrorid(): int
    {
        return $this->errorid;
    }

    public function setDescription(string $description): InternalError
    {
        $this->description = $description;
        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setJudgehostlog(string $judgehostlog): InternalError
    {
        $this->judgehostlog = $judgehostlog;
        return $this;
    }

    public function getJudgehostlog(): string
    {
        return $this->judgehostlog;
    }

    /** @param string|float $time */
    public function setTime($time): InternalError
    {
        $this->time = $time;
        return $this;
    }

    /** @return string|float */
    public function getTime()
    {
        return $this->time;
    }

    public function setDisabled(array $disabled): InternalError
    {
        $this->disabled = $disabled;
        return $this;
    }

    public function getDisabled(): array
    {
        return $this->disabled;
    }

    public function setStatus(string $status): InternalError
    {
        $this->status = $status;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setContest(?Contest $contest = null): InternalError
    {
        $this->contest = $contest;
        return $this;
    }

    public function getContest(): ?Contest
    {
        return $this->contest;
    }

    public function setJudging(?Judging $judging = null): InternalError
    {
        $this->judging = $judging;
        return $this;
    }

    public function getJudging(): ?Judging
    {
        return $this->judging;
    }

    public function getAffectedJudgings()
    {
        return $this->affectedJudgings;
    }
}
