<?php

namespace DOMjudge\MainBundle\Twig;

class ResultFormatter extends \Twig_Extension
{
	public function getFunctions()
	{
		return array(
			new \Twig_SimpleFunction('formatResult', array($this, 'formatResult'), array(
				'is_safe' => array('html'),
				'needs_environment' => true,
			)),
		);
	}

	public function formatResult(\Twig_Environment $twig, $result, $isJury = false, $valid = true)
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
		
		return $twig->render('@DOMjudgeMain/result.html.twig', array(
			'class' => implode(' ', $classes),
			'result' => $result,
		));
	}

	public function getName()
	{
		return 'result_formattter';
	}
}
