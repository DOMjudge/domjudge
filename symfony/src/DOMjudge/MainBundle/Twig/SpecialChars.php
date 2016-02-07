<?php

namespace DOMjudge\MainBundle\Twig;

class SpecialChars extends \Twig_Extension
{
	public function getFunctions()
	{
		return array(
			new \Twig_SimpleFunction('specialChars', array($this, 'specialChars')),
		);
	}

	public function specialChars($string)
	{
		// TODO: load domjudge character set?
		return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8');
	}
	
	public function getName()
	{
		return 'special_chars';
	}
}
