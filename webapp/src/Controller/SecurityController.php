<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\Team;
use App\Entity\TeamAffiliation;
use App\Entity\TeamCategory;
use App\Entity\User;
use App\Form\Type\UserRegistrationType;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    private DOMJudgeService $dj;
    private ConfigurationService $config;
    private EntityManagerInterface $em;

    public function __construct(
        DOMJudgeService $dj,
        ConfigurationService $config,
        EntityManagerInterface $em
    ) {
        $this->dj = $dj;
        $this->config = $config;
        $this->em = $em;
    }

    /**
     * @Route("/login", name="login")
     */
    public function loginAction(
        Request $request,
        AuthorizationCheckerInterface $authorizationChecker,
        AuthenticationUtils $authUtils
    ): Response {
        $allowIPAuth = false;
        $authmethods = $this->config->get('auth_methods');

        if (in_array('ipaddress', $authmethods)) {
            $allowIPAuth = true;
        }

        $ipAutologin = $this->config->get('ip_autologin');
        if (!$ipAutologin && $authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->redirect($this->generateUrl('root'));
        }

        // Get the login error if there is one.
        $error = $authUtils->getLastAuthenticationError();

        // Last username entered by the user.
        $lastUsername = $authUtils->getLastUsername();

        $em = $this->em;

        $clientIP             = $this->dj->getClientIp();
        $auth_ipaddress_users = [];
        if ($allowIPAuth) {
            $auth_ipaddress_users = $em->getRepository(User::class)->findBy(['ipAddress' => $clientIP, 'enabled' => 1]);
        }

        // Add a header so we can detect that this is the login page.
        $response = new Response();
        $response->headers->set('X-Login-Page', $this->generateUrl('login'));
        if (!empty($error)) {
            $response->setStatusCode(401);
        }

        $selfRegistrationCategoriesCount = $em->getRepository(TeamCategory::class)->count(['allow_self_registration' => 1]);

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'allow_registration' => $selfRegistrationCategoriesCount !== 0,
            'allowed_authmethods' => $authmethods,
            'auth_xheaders_present' => $request->headers->get('X-DOMjudge-Login'),
            'auth_ipaddress_users' => $auth_ipaddress_users,
        ], $response);
    }

    /**
     * @Route("/register", name="register")
     */
    public function registerAction(
        Request $request,
        AuthorizationCheckerInterface $authorizationChecker,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        // Redirect if already logged in
        if ($authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->redirect($this->generateUrl('root'));
        }

        $em                              = $this->em;
        $selfRegistrationCategoriesCount = $em->getRepository(TeamCategory::class)->count(['allow_self_registration' => 1]);

        if ($selfRegistrationCategoriesCount === 0) {
            throw new HttpException(403, "Registration not enabled");
        }

        $user              = new User();
        $user->setExternalid(Uuid::uuid4()->toString());
        $registration_form = $this->createForm(UserRegistrationType::class, $user);
        $registration_form->handleRequest($request);
        if ($registration_form->isSubmitted() && $registration_form->isValid()) {
            $plainPass = $registration_form->get('plainPassword')->getData();
            $password  = $passwordHasher->hashPassword($user, $plainPass);
            $user->setPassword($password);
            if ($user->getName() === null) {
                $user->setName($user->getUsername());
            }

            $teamName = $registration_form->get('teamName')->getData();

            if ($selfRegistrationCategoriesCount === 1) {
                $teamCategory = $em->getRepository(TeamCategory::class)->findOneBy(['allow_self_registration' => 1]);
            } else {
                // $selfRegistrationCategoriesCount > 1, 'teamCategory' field exists
                $teamCategory = $registration_form->get('teamCategory')->getData();
            }

            // Create a team to go with the user, then set some team attributes.
            $team = new Team();
            $user->setTeam($team);
            $team
                ->setExternalid(Uuid::uuid4()->toString())
                ->addUser($user)
                ->setName($teamName)
                ->setCategory($teamCategory)
                ->setInternalComments('Registered by ' . $this->dj->getClientIp() . ' on ' . date('r'));

            if ($this->config->get('show_affiliations')) {
                switch ($registration_form->get('affiliation')->getData()) {
                    case 'new':
                        $affiliation = new TeamAffiliation();
                        $affiliation
                            ->setExternalid(Uuid::uuid4()->toString())
                            ->setName($registration_form->get('affiliationName')->getData())
                            ->setShortname($registration_form->get('affiliationShortName')->getData());
                        if ($registration_form->has('affiliationCountry')) {
                            $affiliation->setCountry($registration_form->get('affiliationCountry')->getData());
                        }
                        $team->setAffiliation($affiliation);
                        $em->persist($affiliation);
                        break;
                    case 'existing':
                        $team->setAffiliation($registration_form->get('existingAffiliation')->getData());
                        break;
                }
            }

            $em->persist($user);
            $em->persist($team);
            $em->flush();

            $this->addFlash('success', 'Account registered successfully. Please log in.');

            return $this->redirect($this->generateUrl('login'));
        }

        return $this->render('security/register.html.twig', ['registration_form' => $registration_form->createView()]);
    }
}
