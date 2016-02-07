<?php

namespace DOMjudge\MainBundle\Config;

class ConfigurationValue
{
	private $key;
	private $value;
	private $type;
	private $description;

	public function __construct($key, $value, $type, $description)
	{
		$this->key = $key;
		$this->value = $value;
		$this->type = $type;
		$this->description = $description;
	}

	/**
	 * @return mixed
	 */
	public function getKey()
	{
		return $this->key;
	}

	/**
	 * @return mixed
	 */
	public function getValue()
	{
		return $this->value;
	}

	/**
	 * @param mixed $value
	 */
	public function setValue($value)
	{
		$this->value = $value;
	}

	/**
	 * @return mixed
	 */
	public function getType()
	{
		return $this->type;
	}

	/**
	 * @return mixed
	 */
	public function getDescription()
	{
		return $this->description;
	}
}
