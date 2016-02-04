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
		return $this->redirectToRoute('public_home');
	}
}
