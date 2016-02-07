<?php

namespace DOMjudge\ApiBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ApiController extends Controller
{
	/**
	 * @Route("/", name="api_home")
	 */
	public function homeAction()
	{
		return new Response("Not yet implemented");
	}
}
