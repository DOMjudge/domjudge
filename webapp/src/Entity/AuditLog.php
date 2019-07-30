<?php declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Log of all actions performed
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
     *              "unsigned"=true,"default"="NULL"},
     *     nullable=true)
     */
    private $cid;

    /**
     * @var string
     * @ORM\Column(type="string", name="user", length=255,
     *     options={"comment"="User who performed this action","default"="NULL"},
     *     nullable=true)
     */
    private $user;

    /**
     * @var string
     * @ORM\Column(type="string", name="datatype", length=32,
     *     options={"comment"="Reference to DB table associated to this entry",
     *              "default"="NULL"},
     *     nullable=true)
     */
    private $datatype;

    /**
     * @var string
     * @ORM\Column(type="string", name="dataid", length=64,
     *     options={"comment"="Identifier in reference table","default"="NULL"},
     *     nullable=true)
     */
    private $dataid;

    /**
     * @var string
     * @ORM\Column(type="string", name="action", length=64,
     *     options={"comment"="Description of action performed","default"="NULL"},
     *     nullable=true)
     */
    private $action;

    /**
     * @var string
     * @ORM\Column(type="string", name="extrainfo", length=255,
     *     options={"comment"="Optional additional description of the entry",
     *              "default"="NULL"},
     *     nullable=true)
     */
    private $extrainfo;

    /**
     * Get logid
     *
     * @return integer
     */
    public function getLogid()
    {
        return $this->logid;
    }

    /**
     * Set logtime
     *
     * @param string $logtime
     *
     * @return AuditLog
     */
    public function setLogtime($logtime)
    {
        $this->logtime = $logtime;

        return $this;
    }

    /**
     * Get logtime
     *
     * @return string
     */
    public function getLogtime()
    {
        return $this->logtime;
    }

    /**
     * Set cid
     *
     * @param integer $cid
     *
     * @return AuditLog
     */
    public function setCid($cid)
    {
        $this->cid = $cid;

        return $this;
    }

    /**
     * Get cid
     *
     * @return integer
     */
    public function getCid()
    {
        return $this->cid;
    }

    /**
     * Set user
     *
     * @param string $user
     *
     * @return AuditLog
     */
    public function setUser($user)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get user
     *
     * @return string
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Set datatype
     *
     * @param string $datatype
     *
     * @return AuditLog
     */
    public function setDatatype($datatype)
    {
        $this->datatype = $datatype;

        return $this;
    }

    /**
     * Get datatype
     *
     * @return string
     */
    public function getDatatype()
    {
        return $this->datatype;
    }

    /**
     * Set dataid
     *
     * @param string $dataid
     *
     * @return AuditLog
     */
    public function setDataid($dataid)
    {
        $this->dataid = $dataid;

        return $this;
    }

    /**
     * Get dataid
     *
     * @return string
     */
    public function getDataid()
    {
        return $this->dataid;
    }

    /**
     * Set action
     *
     * @param string $action
     *
     * @return AuditLog
     */
    public function setAction($action)
    {
        $this->action = $action;

        return $this;
    }

    /**
     * Get action
     *
     * @return string
     */
    public function getAction()
    {
        return $this->action;
    }

    /**
     * Set extrainfo
     *
     * @param string $extrainfo
     *
     * @return AuditLog
     */
    public function setExtrainfo($extrainfo)
    {
        $this->extrainfo = $extrainfo;

        return $this;
    }

    /**
     * Get extrainfo
     *
     * @return string
     */
    public function getExtrainfo()
    {
        return $this->extrainfo;
    }
}
