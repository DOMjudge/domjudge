<?php

namespace DOMjudge\MainBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * AuditLog
 *
 * @ORM\Table(name="auditlog", indexes={@ORM\Index(name="cid", columns={"cid"})})
 * @ORM\Entity
 */
class AuditLog
{
    /**
     * @var integer
     *
     * @ORM\Column(name="logid", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $logid;

    /**
     * @var string
     *
     * @ORM\Column(name="logtime", type="decimal", precision=32, scale=9, nullable=false)
     */
    private $logTime;

    /**
     * @var string
     *
     * @ORM\Column(name="user", type="string", length=255, nullable=true)
     */
    private $user;

    /**
     * @var string
     *
     * @ORM\Column(name="datatype", type="string", length=25, nullable=true)
     */
    private $dataType;

    /**
     * @var string
     *
     * @ORM\Column(name="dataid", type="string", length=50, nullable=true)
     */
    private $dataId;

    /**
     * @var string
     *
     * @ORM\Column(name="action", type="string", length=30, nullable=true)
     */
    private $action;

    /**
     * @var string
     *
     * @ORM\Column(name="extrainfo", type="string", length=255, nullable=true)
     */
    private $extraInfo;

    /**
     * @var \DOMjudge\MainBundle\Entity\Contest
     *
     * @ORM\ManyToOne(targetEntity="DOMjudge\MainBundle\Entity\Contest")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="cid", referencedColumnName="cid")
     * })
     */
    private $contest;


}
