<?php

namespace DOMjudge\PublicBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends Controller
{
	/**
	 * @Route("/", name="public_home")
	 * @Template()
	 */
	public function homeAction()
	{
		return array();
	}
}
