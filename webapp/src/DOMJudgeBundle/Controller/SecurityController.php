<?php
namespace DOMJudgeBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

use DOMJudgeBundle\Utils\Utils;
use DOMJudgeBundle\Form\UserRegistrationType;
use DOMJudgeBundle\Entity\User;
use DOMJudgeBundle\Entity\Team;

class SecurityController extends Controller
{
    /**
     * @Route("/login", name="login")
     */
    public function loginAction(Request $request)
    {
        if ($this->get('security.authorization_checker')->isGranted('IS_AUTHENTICATED_FULLY')) {
            $user = $this->get('security.token_storage')->getToken()->getUser();
            $user->setLastLogin(Utils::now());
            $user->setLastIpAddress(@$_SERVER['REMOTE_ADDR']);
            $this->getDoctrine()->getManager()->flush();

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
                'allow_registration' => $this->get('domjudge.domjudge')->dbconfig_get('allow_registration', false)
        ));
    }

    /**
     * @Route("/register", name="register")
     */
    public function registerAction(Request $request)
    {
        // Redirect if already logged in
        if ($this->get('security.authorization_checker')->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->redirect($this->generateUrl('legacy.index'));
        }
        if (!$this->get('domjudge.domjudge')->dbconfig_get('allow_registration', false)) {
            throw new \Symfony\Component\HttpKernel\Exception\HttpException(400, "Registration not enabled");
        }

        $user = new User();
        $registration_form = $this->createForm(UserRegistrationType::class, $user);
        $registration_form->handleRequest($request);
        if ($registration_form->isSubmitted() && $registration_form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $self_registered_category = $em->getRepository('DOMJudgeBundle:TeamCategory')->findOneByName('Self-Registered');
            $team_role = $em->getRepository('DOMJudgeBundle:Role')->findOneBy(['dj_role' => 'team']);

            $plainPass = $registration_form->get('plainPassword')->getData();
            $password = $this->get('security.password_encoder')->encodePassword($user, $plainPass);
            $user->setPassword($password);
            $user->setName($user->getUsername());
            $user->addRole($team_role);


            // Create a team to go with the user, then set some team attributes
            $team = new Team();
            $user->setTeam($team);
            $team->addUser($user);
            $team->setName($user->getUsername());
            $team->setCategory($self_registered_category);
            $team->setComments('Registered by ' . @$_SERVER['REMOTE_ADDR'] . ' on ' . date('r'));

            $em->persist($user);
            $em->persist($team);
            $em->flush();

            $this->addFlash('notice', 'Account registered successfully. Please log in.');

            return $this->redirect($this->generateUrl('login'));
        }

        return $this->render('DOMJudgeBundle:security:register.html.twig', array(
                'registration_form' => $registration_form->createView(),
        ));
    }
}
