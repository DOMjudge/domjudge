<?php declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Log of all actions performed.
 *
 * @ORM\Entity()
 * @ORM\Table(
 *     name="auditlog",
 *     options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4", "comment"="Log of all actions performed"})
 */
class AuditLog
{
    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="logid", length=4,
     *     options={"comment"="Audit log ID","unsigned"=true}, nullable=false)
     */
    private $logid;

    /**
     * @var double
     * @ORM\Column(type="decimal", precision=32, scale=9, name="logtime", options={"comment"="Timestamp of the logentry", "unsigned"=true}, nullable=false)
     */
    private $logtime;

    /**
     * @var int
     * @ORM\Column(type="integer", name="cid", length=4,
     *     options={"comment"="Contest ID associated to this entry",
     *              "unsigned"=true},
     *     nullable=true)
     */
    private $cid;

    /**
     * @var string
     * @ORM\Column(type="string", name="user", length=255,
     *     options={"comment"="User who performed this action"},
     *     nullable=true)
     */
    private $user;

    /**
     * @var string
     * @ORM\Column(type="string", name="datatype", length=32,
     *     options={"comment"="Reference to DB table associated to this entry"},
     *     nullable=true)
     */
    private $datatype;

    /**
     * @var string
     * @ORM\Column(type="string", name="dataid", length=64,
     *     options={"comment"="Identifier in reference table"},
     *     nullable=true)
     */
    private $dataid;

    /**
     * @var string
     * @ORM\Column(type="string", name="action", length=64,
     *     options={"comment"="Description of action performed"},
     *     nullable=true)
     */
    private $action;

    /**
     * @var string
     * @ORM\Column(type="string", name="extrainfo", length=255,
     *     options={"comment"="Optional additional description of the entry"},
     *     nullable=true)
     */
    private $extrainfo;

    public function getLogid(): int
    {
        return $this->logid;
    }

    /** @param string|float $logtime */
    public function setLogtime($logtime): AuditLog
    {
        $this->logtime = $logtime;
        return $this;
    }

    /** @return string|float */
    public function getLogtime()
    {
        return $this->logtime;
    }

    public function setCid(?int $cid): AuditLog
    {
        $this->cid = $cid;
        return $this;
    }

    public function getCid(): ?int
    {
        return $this->cid;
    }

    public function setUser(?string $user): AuditLog
    {
        $this->user = $user;
        return $this;
    }

    public function getUser(): ?string
    {
        return $this->user;
    }

    public function setDatatype(string $datatype): AuditLog
    {
        $this->datatype = $datatype;
        return $this;
    }

    public function getDatatype(): string
    {
        return $this->datatype;
    }

    public function setDataid(?string $dataid): AuditLog
    {
        $this->dataid = $dataid;
        return $this;
    }

    public function getDataid(): ?string
    {
        return $this->dataid;
    }

    public function setAction(string $action): AuditLog
    {
        $this->action = $action;
        return $this;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function setExtrainfo(?string $extrainfo): AuditLog
    {
        $this->extrainfo = $extrainfo;
        return $this;
    }

    public function getExtrainfo(): ?string
    {
        return $this->extrainfo;
    }
}
