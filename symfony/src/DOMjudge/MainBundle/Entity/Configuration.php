<?php

namespace DOMjudge\MainBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Configuration
 *
 * @ORM\Table(name="configuration", uniqueConstraints={@ORM\UniqueConstraint(name="name", columns={"name"})})
 * @ORM\Entity
 */
class Configuration
{
	/**
	 * @var integer
	 *
	 * @ORM\Column(name="configid", type="integer")
	 * @ORM\Id
	 * @ORM\GeneratedValue(strategy="IDENTITY")
	 */
	private $configid;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="name", type="string", length=25, nullable=false)
	 */
	private $name;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="value", type="text", nullable=false)
	 */
	private $value;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="type", type="string", length=25, nullable=true)
	 */
	private $type;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="description", type="string", length=255, nullable=true)
	 */
	private $description;



	/**
	 * Get configid
	 *
	 * @return integer
	 */
	public function getConfigid()
	{
		return $this->configid;
	}

	/**
	 * Set name
	 *
	 * @param string $name
	 * @return Configuration
	 */
	public function setName($name)
	{
		$this->name = $name;

		return $this;
	}

	/**
	 * Get name
	 *
	 * @return string
	 */
	public function getName()
	{
		return $this->name;
	}

	/**
	 * Set value
	 *
	 * @param string $value
	 * @return Configuration
	 */
	public function setValue($value)
	{
		$this->value = $value;

		return $this;
	}

	/**
	 * Get value
	 *
	 * @return string
	 */
	public function getValue()
	{
		return $this->value;
	}

	/**
	 * Set type
	 *
	 * @param string $type
	 * @return Configuration
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

	/**
	 * Set description
	 *
	 * @param string $description
	 * @return Configuration
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
}
