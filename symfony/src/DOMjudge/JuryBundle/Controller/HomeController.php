<?php

namespace DOMjudge\JuryBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends Controller
{
	/**
	 * @Route("/", name="jury_home")
	 * @Template()
	 */
	public function homeAction()
	{
		return array();
	}
}

