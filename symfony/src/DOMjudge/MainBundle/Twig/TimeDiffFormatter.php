<?php

namespace DOMjudge\MainBundle\Twig;

class TimeDiffFormatter extends \Twig_Extension
{
	public function getFunctions()
	{
		return array(
			new \Twig_SimpleFunction('timediff', array($this, 'timeDiffFormatter')),
		);
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
