<?php

namespace DOMjudge\MainBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Executable
 *
 * @ORM\Table(name="executable")
 * @ORM\Entity
 */
class Executable
{
	/**
	 * @var string
	 *
	 * @ORM\Column(name="execid", type="string", length=32)
	 * @ORM\Id
	 * @ORM\GeneratedValue(strategy="IDENTITY")
	 */
	private $execid;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="md5sum", type="string", length=32, nullable=true)
	 */
	private $md5sum;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="zipfile", type="blob", nullable=true)
	 */
	private $zipfile;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="description", type="string", length=255, nullable=true)
	 */
	private $description;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="type", type="string", length=8, nullable=false)
	 */
	private $type;



	/**
	 * Get execid
	 *
	 * @return string
	 */
	public function getExecid()
	{
		return $this->execid;
	}

	/**
	 * Set md5sum
	 *
	 * @param string $md5sum
	 * @return Executable
	 */
	public function setMd5sum($md5sum)
	{
		$this->md5sum = $md5sum;

		return $this;
	}

	/**
	 * Get md5sum
	 *
	 * @return string
	 */
	public function getMd5sum()
	{
		return $this->md5sum;
	}

	/**
	 * Set zipfile
	 *
	 * @param string $zipfile
	 * @return Executable
	 */
	public function setZipfile($zipfile)
	{
		$this->zipfile = $zipfile;

		return $this;
	}

	/**
	 * Get zipfile
	 *
	 * @return string
	 */
	public function getZipfile()
	{
		return $this->zipfile;
	}

	/**
	 * Set description
	 *
	 * @param string $description
	 * @return Executable
	 */
	public function setDescription($description)
	{
		$this->description = $description;

		return $this;
	}

	/**
	 * Get description
	 *
	 * @return string
	 */
	public function getDescription()
	{
		return $this->description;
	}

	/**
	 * Set type
	 *
	 * @param string $type
	 * @return Executable
	 */
	public function setType($type)
	{
		$this->type = $type;

		return $this;
	}

	/**
	 * Get type
	 *
	 * @return string
	 */
	public function getType()
	{
		return $this->type;
	}
}
