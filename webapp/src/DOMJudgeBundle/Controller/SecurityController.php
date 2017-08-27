<?php
namespace DOMJudgeBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;


class SecurityController extends Controller {
	/**
	 * @Route("/login", name="login")
	 */
	public function loginAction(Request $request) {
		if ($this->get('security.authorization_checker')->isGranted('IS_AUTHENTICATED_FULLY')) {
			return $this->redirect($this->generateUrl('legacy.index'));
		}

		$authUtils = $this->get('security.authentication_utils');

		// get the login error if there is one
		$error = $authUtils->getLastAuthenticationError();

		// last username entered by the user
		$lastUsername = $authUtils->getLastUsername();

		return $this->render('DOMJudgeBundle:security:login.html.twig', array(
		    'last_username' => $lastUsername,
		    'error'         => $error,
		));
	}
}
