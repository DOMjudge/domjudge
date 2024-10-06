<?php declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Service\ConfigurationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class DOMJudgeIPAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    use TargetPathTrait;

    public function __construct(
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly Security $security,
        private readonly EntityManagerInterface $em,
        private readonly ConfigurationService $config,
        private readonly RouterInterface $router,
        private readonly RequestStack $requestStack
    ) {}

    public function supports(Request $request): bool
    {
        // Make sure ipaddress auth is enabled.
        $authmethods          = $this->config->get('auth_methods');
        $auth_allow_ipaddress = in_array('ipaddress', $authmethods);
        if (!$auth_allow_ipaddress) {
            return false;
        }

        // If there is already an authenticated user (likely due to the session)
        // then return null and skip authentication: there is no need.
        // However, on the login page we might need it when IP auto login is enabled.
        if ($this->security->getUser() && $request->attributes->get('_route') !== 'login') {
            return false;
        }

        // If it's stateless, we provide auth support every time
        $stateless_fw_contexts = [
            'security.firewall.map.context.api',
            'security.firewall.map.context.metrics',
        ];
        $fwcontext             = $request->attributes->get('_firewall_context', '');
        $ipAutologin           = $this->config->get('ip_autologin');
        if (in_array($fwcontext, $stateless_fw_contexts) || $ipAutologin) {
            return true;
        }

        // We also support authenticating if this is a POST to the login route
        // and loginmethod is set correctly.
        return $request->attributes->get('_route') === 'login'
            && $request->isMethod('POST')
            && $request->request->get('loginmethod') === 'ipaddress';
    }

    public function authenticate(Request $request): Passport
    {
        // Check if we're coming from the auth form.
        if ($request->attributes->get('_route') === 'login' && $request->isMethod('POST')) {
            // Check CSRF token if it's coming from the login form
            $csrfToken = $request->request->get('_csrf_token');
            if (false === $this->csrfTokenManager->isTokenValid(new CsrfToken('authenticate', $csrfToken))) {
                throw new InvalidCsrfTokenException('Invalid CSRF token, please try again.');
            }
        }

        // Get the client IP address to use.
        $clientIP = $this->requestStack->getMainRequest()->getClientIp();
        $username = $request->request->get('_username');
        $authbasicUsername = $request->headers->get('php-auth-user');

        $userRepo = $this->em->getRepository(User::class);
        $filters  = [
            'ipAddress' => $clientIP,
            'enabled' => 1,
        ];

        if ($authbasicUsername) {
            $filters['username'] = $authbasicUsername;
        }

        if ($username) {
            $filters['username'] = $username;
        }

        $user        = null;
        $users       = $userRepo->findBy($filters);
        if (count($users) === 1) {
            $user = $users[0];
        }

        // Fail if we didn't find a user with a matching IP address.
        if ($user == null) {
            throw new UserNotFoundException();
        }

        return new SelfValidatingPassport(new UserBadge($user->getUsername()));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // On success, redirect to the last page or the homepage if it was a user triggered action.
        if ($request->attributes->get('_route') === 'login'
            && $request->isMethod('POST')
            && $request->request->get('loginmethod') === 'ipaddress') {
            // Use target URL from session if set.
            if ($firewallName !== null &&
                $targetUrl = $this->getTargetPath($request->getSession(), $firewallName)) {
                $this->removeTargetPath($request->getSession(), $firewallName);
                return new RedirectResponse($targetUrl);
            }

            return new RedirectResponse($this->router->generate('root'));
        }
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        // We never fail the authentication request, something else might handle it.
        return null;
    }

    public function start(Request $request, AuthenticationException $authException = null): Response
    {
        // If this is the guard that fails/is configured to allow access as the entry_point
        // send the user a basic auth dialog, as that's probably what they're expecting.
        $resp = new Response('', Response::HTTP_UNAUTHORIZED);
        $resp->headers->set('WWW-Authenticate', sprintf('Basic realm="%s"', 'Secured Area'));
        return $resp;
    }
}
