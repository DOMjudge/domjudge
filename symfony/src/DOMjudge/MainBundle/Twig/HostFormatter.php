<?php

namespace DOMjudge\MainBundle\Twig;

class HostFormatter extends \Twig_Extension
{
	public function getFilters()
	{
		return array(
			new \Twig_SimpleFilter('formatHost', array($this, 'formatHost'), array(
				'is_safe' => array('html'),
			))
		);
	}
	
	public function formatHost($hostname, $full = false)
	{
		// Shorten the hostname to first label, but not if it's an IP address.
		if ( !$full && !preg_match('/^\d{1,3}(\.\d{1,3}){3}$/', $hostname) ) {
			$expl = explode('.', $hostname);
			$hostname = array_shift($expl);
		}

		return "<span class=\"hostname\">" . $hostname . "</span>";
	}

	public function getName()
	{
		return 'host_formatter';
	}
}
