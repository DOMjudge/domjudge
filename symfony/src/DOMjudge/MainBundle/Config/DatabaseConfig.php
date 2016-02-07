<?php

namespace DOMjudge\MainBundle\Config;

use Doctrine\ORM\EntityManager;

class DatabaseConfig
{
	/**
	 * @var ConfigurationValue[]
	 */
	private $config = null;

	/**
	 * @var EntityManager
	 */
	private $entityManager;

	public function __construct(EntityManager $entityManager)
	{
		$this->entityManager = $entityManager;
	}

	/**
	 * Get the full configuration
	 *
	 * @param bool $cacheOk
	 *   Whether it is OK to used cached values
	 * @return ConfigurationValue[]
	 *   The full configuration. The array keys are configuration keys
	 * @throws \Exception
	 *   When loading the configuration fails
	 */
	public function getFullConfiguration($cacheOk = true)
	{
		if ( $this->config === null || !$cacheOk ) {
			$this->init();
		}

		return $this->config;
	}

	/**
	 * Get a configuration value
	 *
	 * @param string $key
	 *   The key to get the value of
	 * @param mixed $default
	 *   If set, return this if the configuration value does not exist
	 * @param bool $cacheOk
	 *   Whether it is OK to used cached values
	 * @return ConfigurationValue
	 *   The requested configuration value
	 * @throws \Exception
	 *   When loading the configuration fails or if no default is provided and no configuration
	 *   value exists with the given key
	 */
	public function getConfigurationValue($key, $default = null, $cacheOk = true)
	{
		if ( $this->config === null || !$cacheOk ) {
			$this->init();
		}

		if ( isset($this->config[$key]) ) {
			return $this->config[$key];
		}

		if ( $default === null ) {
			throw new \Exception("Configuration variable '$key' not found.");
		}

		// Return default value. We do not know the type and description
		return new ConfigurationValue($key, $default, null, null);
	}

	/**
	 * Initialize the configuration
	 *
	 * @throws \Exception
	 *   When loading the configuration fails
	 */
	private function init()
	{
		$this->config = array();

		$configs = $this->entityManager->getRepository('DOMjudgeMainBundle:Configuration')->findAll();

		foreach ( $configs as $config ) {
			$key = $config->getName();
			$value = json_decode($config->getValue(), true);

			switch ( json_last_error() ) {
				case JSON_ERROR_NONE:
					break;
				case JSON_ERROR_DEPTH:
					throw new \Exception("JSON config '$key' decode: maximum stack depth exceeded");
				case JSON_ERROR_STATE_MISMATCH:
					throw new \Exception("JSON config '$key' decode: underflow or the modes mismatch");
				case JSON_ERROR_CTRL_CHAR:
					throw new \Exception("JSON config '$key' decode: unexpected control character found");
				case JSON_ERROR_SYNTAX:
					throw new \Exception("JSON config '$key' decode: syntax error, malformed JSON");
				case JSON_ERROR_UTF8:
					throw new \Exception("JSON config '$key' decode: malformed UTF-8 characters, possibly incorrectly encoded");
				default:
					throw new \Exception("JSON config '$key' decode: unknown error");
			}

			switch ( $type = $config->getType() ) {
				case 'bool':
				case 'int':
					if ( !is_int($value) ) {
						throw new \Exception("invalid type '$type' for config variable '$key'");
					}
					break;
				case 'string':
					if ( !is_string($value) ) {
						throw new \Exception("invalid type '$type' for config variable '$key'");
					}
					break;
				case 'array_val':
				case 'array_keyval':
					if ( !is_array($value) ) {
						throw new \Exception("invalid type '$type' for config variable '$key'");
					}
					break;
				default:
					throw new \Exception("unknown type '$type' for config variable '$key'");
			}

			$this->config[$key] = new ConfigurationValue($key, $value, $config->getType(),
			                                             $config->getDescription());
		}
	}
}
