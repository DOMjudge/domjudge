<?php

namespace DOMjudge\JuryBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends Controller
{
	/**
	 * @Route("/", name="jury_home")
	 */
	public function homeAction()
	{
		return new Response("Jury home");
	}
}

