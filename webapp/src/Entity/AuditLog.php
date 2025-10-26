<?php declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Log of all actions performed.
 */
#[ORM\Entity]
#[ORM\Table(
    name: 'auditlog',
    options: [
        'collation' => 'utf8mb4_unicode_ci',
        'charset' => 'utf8mb4',
        'comment' => 'Log of all actions performed',
    ]
)]
class AuditLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['comment' => 'Audit log ID', 'unsigned' => true])]
    private ?int $logid = null;

    #[ORM\Column(
        type: 'decimal',
        precision: 32,
        scale: 9,
        options: ['comment' => 'Timestamp of the logentry', 'unsigned' => true]
    )]
    private string|float $logtime;

    #[ORM\Column(
        nullable: true,
        options: ['comment' => 'External contest ID associated to this entry', 'unsigned' => true]
    )]
    private ?string $cid = null;

    #[ORM\Column(
        nullable: true,
        options: ['comment' => 'User who performed this action']
    )]
    private ?string $user = null;

    #[ORM\Column(
        length: 32,
        options: ['comment' => 'Reference to DB table associated to this entry']
    )]
    private string $datatype;

    #[ORM\Column(
        length: 64,
        nullable: true,
        options: ['comment' => '(External) identifier in reference table']
    )]
    private ?string $dataid = null;

    #[ORM\Column(length: 128, options: ['comment' => 'Description of action performed'])]
    private string $action;

    #[ORM\Column(
        nullable: true,
        options: ['comment' => 'Optional additional description of the entry']
    )]
    private ?string $extrainfo = null;

    public function getLogid(): ?int
    {
        return $this->logid;
    }

    public function setLogtime(string|float $logtime): AuditLog
    {
        $this->logtime = $logtime;
        return $this;
    }

    public function getLogtime(): string|float
    {
        return $this->logtime;
    }

    public function setCid(?string $cid): AuditLog
    {
        $this->cid = $cid;
        return $this;
    }

    public function getCid(): ?string
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
