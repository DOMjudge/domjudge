<?php

namespace DOMjudge\MainBundle\Twig;

use DOMjudge\MainBundle\Config\DatabaseConfig;

class TimeFormatter extends \Twig_Extension
{
	/**
	 * @var DatabaseConfig
	 */
	private $databaseConfig;

	public function __construct(DatabaseConfig $databaseConfig)
	{
		$this->databaseConfig = $databaseConfig;
	}

	public function getFunctions()
	{
		return array(
			new \Twig_SimpleFunction('formatTime', array($this, 'timeFormatter')),
			new \Twig_SimpleFunction('formatTimeDiff', array($this, 'timeDiffFormatter')),
		);
	}
	
	public function timeFormatter($time, $format = null)
	{
		if ( empty($time) ) return '';
		if ( is_null($format) ) {
			$format = $this->databaseConfig->getConfigurationValue('time_format', '%H:%M')->getValue();
		}
		return strftime($format, floor($time));
	}

	public function timeDiffFormatter($start = null, $end = NULL)
	{
		if ( is_null($start) ) $start = microtime(TRUE);
		if ( is_null($end) ) $end = microtime(TRUE);
		$ret = '';
		$diff = floor($end - $start);

		if ( $diff >= 24 * 60 * 60 ) {
			$d = floor($diff / (24 * 60 * 60));
			$ret .= $d . "d ";
			$diff -= $d * 24 * 60 * 60;
		}
		if ( $diff >= 60 * 60 || isset($d) ) {
			$h = floor($diff / (60 * 60));
			$ret .= $h . ":";
			$diff -= $h * 60 * 60;
		}
		$m = floor($diff / 60);
		$ret .= sprintf('%02d:', $m);
		$diff -= $m * 60;
		$ret .= sprintf('%02d', $diff);

		return $ret;
	}

	public function getName()
	{
		return 'time_diff_formatter';
	}
}
