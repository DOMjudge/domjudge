<?php declare(strict_types=1);

namespace DOMJudgeBundle\Controller;

use DOMJudgeBundle\Entity\Team;
use DOMJudgeBundle\Entity\TeamCategory;
use DOMJudgeBundle\Entity\User;
use DOMJudgeBundle\Form\Type\UserRegistrationType;
use DOMJudgeBundle\Service\DOMJudgeService;
use DOMJudgeBundle\Utils\Utils;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SecurityController extends Controller
{
    /**
     * @var DOMJudgeService
     */
    private $dj;

    public function __construct(DOMJudgeService $dj)
    {
        $this->dj = $dj;
    }

    /**
     * @Route("/login", name="login")
     */
    public function loginAction(Request $request)
    {
        $allowIPAuth = false;
        $authmethods = $this->dj->dbconfig_get('auth_methods', []);

        if (in_array('ipaddress', $authmethods)) {
            $allowIPAuth = true;
        }

        $ipAutologin = $this->dj->dbconfig_get('ip_autologin', false);
        if ($this->get('security.authorization_checker')->isGranted('IS_AUTHENTICATED_FULLY') && !$ipAutologin) {
            return $this->redirect($this->generateUrl('root'));
        }

        $authUtils = $this->get('security.authentication_utils');

        // get the login error if there is one
        $error = $authUtils->getLastAuthenticationError();

        // last username entered by the user
        $lastUsername = $authUtils->getLastUsername();

        $em = $this->getDoctrine()->getManager();

        $clientIP = $this->dj->getClientIp();
        $auth_ipaddress_users = [];
        if ($allowIPAuth) {
            $auth_ipaddress_users = $em->getRepository('DOMJudgeBundle:User')->findBy(['ipAddress' => $clientIP]);
        }

        // Add a header so we can detect that this is the login page
        $response = new Response();
        $response->headers->set('X-Login-Page', $this->generateUrl('login'));

        $registrationCategoryName = $this->dj->dbconfig_get('registration_category_name', '');
        $registrationCategory     = $em->getRepository(TeamCategory::class)->findOneBy(['name' => $registrationCategoryName]);

        return $this->render('DOMJudgeBundle:security:login.html.twig', array(
            'last_username' => $lastUsername,
            'error' => $error,
            'allow_registration' => $registrationCategory !== null,
            'allowed_authmethods' => $authmethods,
            'auth_xheaders_present' => $request->headers->get('X-DOMjudge-Login'),
            'auth_ipaddress_users' => $auth_ipaddress_users,
        ), $response);
    }

    /**
     * @Route("/register", name="register")
     */
    public function registerAction(Request $request)
    {
        // Redirect if already logged in
        if ($this->get('security.authorization_checker')->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->redirect($this->generateUrl('root'));
        }

        $em                       = $this->getDoctrine()->getManager();
        $registrationCategoryName = $this->dj->dbconfig_get('registration_category_name', '');
        $registrationCategory     = $em->getRepository(TeamCategory::class)->findOneBy(['name' => $registrationCategoryName]);

        if ($registrationCategory === null) {
            throw new \Symfony\Component\HttpKernel\Exception\HttpException(400, "Registration not enabled");
        }

        $user              = new User();
        $registration_form = $this->createForm(UserRegistrationType::class, $user);
        $registration_form->handleRequest($request);
        if ($registration_form->isSubmitted() && $registration_form->isValid()) {
            $team_role = $em->getRepository('DOMJudgeBundle:Role')->findOneBy(['dj_role' => 'team']);

            $plainPass = $registration_form->get('plainPassword')->getData();
            $password  = $this->get('security.password_encoder')->encodePassword($user, $plainPass);
            $user->setPassword($password);
            $user->setName($user->getUsername());
            $user->addRole($team_role);


            // Create a team to go with the user, then set some team attributes
            $team = new Team();
            $user->setTeam($team);
            $team->addUser($user);
            $team->setName($user->getUsername());
            $team->setCategory($registrationCategory);
            $team->setComments('Registered by ' . $this->dj->getClientIp() . ' on ' . date('r'));

            $em->persist($user);
            $em->persist($team);
            $em->flush();

            $this->addFlash('success', 'Account registered successfully. Please log in.');

            return $this->redirect($this->generateUrl('login'));
        }

        return $this->render('DOMJudgeBundle:security:register.html.twig', array(
            'registration_form' => $registration_form->createView(),
        ));
    }
}
