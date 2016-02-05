<?php

namespace DOMjudge\MainBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends Controller
{
	/**
	 * @Route("/", name="home")
	 */
	public function homeAction()
	{
		if ( $this->isGranted('ROLE_JURY') || $this->isGranted('ROLE_BALLOON') ) {
			return $this->redirectToRoute('jury_home');
		} elseif ( $this->isGranted('ROLE_TEAM') ) {
			return $this->redirectToRoute('team_home');
		} else {
			return $this->redirectToRoute('public_home');
		}
	}
}
