<?php

namespace DOMjudge\TeamBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends Controller
{
	/**
	 * @Route("/", name="team_home")
	 */
	public function homeAction()
	{
		return new Response("Team home");
	}
}
