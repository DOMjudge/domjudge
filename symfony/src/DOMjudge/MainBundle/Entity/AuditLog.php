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
	 * @ORM\ManyToOne(targetEntity="DOMjudge\MainBundle\Entity\Contest", inversedBy="auditlogs")
	 * @ORM\JoinColumns({
	 *   @ORM\JoinColumn(name="cid", referencedColumnName="cid")
	 * })
	 */
	private $contest;



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
	 * Set logTime
	 *
	 * @param string $logTime
	 * @return AuditLog
	 */
	public function setLogTime($logTime)
	{
		$this->logTime = $logTime;

		return $this;
	}

	/**
	 * Get logTime
	 *
	 * @return string
	 */
	public function getLogTime()
	{
		return $this->logTime;
	}

	/**
	 * Set user
	 *
	 * @param string $user
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
	 * Set dataType
	 *
	 * @param string $dataType
	 * @return AuditLog
	 */
	public function setDataType($dataType)
	{
		$this->dataType = $dataType;

		return $this;
	}

	/**
	 * Get dataType
	 *
	 * @return string
	 */
	public function getDataType()
	{
		return $this->dataType;
	}

	/**
	 * Set dataId
	 *
	 * @param string $dataId
	 * @return AuditLog
	 */
	public function setDataId($dataId)
	{
		$this->dataId = $dataId;

		return $this;
	}

	/**
	 * Get dataId
	 *
	 * @return string
	 */
	public function getDataId()
	{
		return $this->dataId;
	}

	/**
	 * Set action
	 *
	 * @param string $action
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
	 * Set extraInfo
	 *
	 * @param string $extraInfo
	 * @return AuditLog
	 */
	public function setExtraInfo($extraInfo)
	{
		$this->extraInfo = $extraInfo;

		return $this;
	}

	/**
	 * Get extraInfo
	 *
	 * @return string
	 */
	public function getExtraInfo()
	{
		return $this->extraInfo;
	}

	/**
	 * Set contest
	 *
	 * @param \DOMjudge\MainBundle\Entity\Contest $contest
	 * @return AuditLog
	 */
	public function setContest(\DOMjudge\MainBundle\Entity\Contest $contest = null)
	{
		$this->contest = $contest;

		return $this;
	}

	/**
	 * Get contest
	 *
	 * @return \DOMjudge\MainBundle\Entity\Contest
	 */
	public function getContest()
	{
		return $this->contest;
	}
}
