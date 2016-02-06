<?php

namespace DOMjudge\MainBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class MainController extends Controller
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

	/**
	 * @Route("/changecontest/{contest}", name="change_contest", options={"expose"=true},
	 *     requirements={"contest": "(-1)|([1-9]\d*)"})
	 */
	public function changeContest(Request $request, $contest)
	{
		$repository = $this->getDoctrine()->getRepository('DOMjudgeMainBundle:Contest');
		if ( $contest != -1 ) {
			$contest = $repository->find($contest);
		} else {
			$contest = null;
		}
		
		$this->get('domjudge.contest')->setCurrentContest($contest);
		
		// Redirect back from where we came
		$referer = $request->headers->get('referer');
		return $this->redirect($referer);
	}
}
