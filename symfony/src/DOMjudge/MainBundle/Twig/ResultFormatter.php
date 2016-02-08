<?php

namespace DOMjudge\MainBundle\Twig;

class ResultFormatter extends \Twig_Extension
{
	public function getFilters()
	{
		return array(
			new \Twig_SimpleFilter('formatResult', array($this, 'formatResult'), array(
				'is_safe' => array('html'),
			)),
		);
	}

	public function formatResult($result, $isJury = false, $valid = true)
	{
		$classes = array('sol');

		if (!$valid) {
			$classes[] = 'disabled';
		} else {
			switch ( $result ) {
				case 'too-late':
					$classes[] = 'sol_queued';
					break;
				case '':
					$classes[] = 'judging';
					break;
				case 'judging':
				case 'queued':
					if ( !$isJury ) {
						$result = 'pending';
					}
					$classes[] = 'sol_queued';
					break;
				case 'correct':
					$classes[] = 'sol_correct';
					break;
				default:
					$classes[] = 'sol_incorrect';
			}
		}
		
		$class = implode(' ', $classes);
		return sprintf('<span class="%s">%s</span>', $class, $result);
	}

	public function getName()
	{
		return 'result_formattter';
	}
}
