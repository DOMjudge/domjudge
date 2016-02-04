<?php

namespace DOMjudge\MainBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use DOMjudge\MainBundle\Form\Type\LoginType;

class SecurityController extends Controller {

	/**
	 * @Route("/login", name="login")
	 * @Template()
	 */
	public function loginAction(Request $request)
	{
		$authenticationUtils = $this->get('security.authentication_utils');

		// get the login error if there is one
		$error = $authenticationUtils->getLastAuthenticationError();

		// last username entered by the user
		$lastUsername = $authenticationUtils->getLastUsername();

		$form = $this->createForm(LoginType::class, array(), array(
			'action' => $this->generateUrl('login_check')
		));

		$form->get('username')->setData($lastUsername);

		if ( $error !== null ) {
			if ( $error instanceof BadCredentialsException ) {
				$error = new FormError('Bad credentials');
			} else {
				dump($error);
				$error = new FormError(
					$this->get('translator')->trans($error->getMessageKey(),
					                                $error->getMessageData()));
			}
			$form->addError($error);
		}

		return array('form' => $form->createView());
	}

	/**
	 * @Route("/login_check", name="login_check")
	 */
	public function loginCheckAction()
	{
		// this controller will not be executed,
		// as the route is handled by the Security system
	}
}
