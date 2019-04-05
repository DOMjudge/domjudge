<?php

namespace DOMJudgeBundle\Security;

use Doctrine\ORM\EntityManagerInterface;
use DOMJudgeBundle\Service\DOMJudgeService;
use Symfony\Component\DependencyInjection\ContainerInterface as Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;

class DOMJudgeIPAuthenticator extends AbstractGuardAuthenticator
{
    private $csrfTokenManager;
    private $security;
    private $container;
    private $em;
    private $dj;

    public function __construct(CsrfTokenManagerInterface $csrfTokenManager, Container $container, Security $security, EntityManagerInterface $em, DOMJudgeService $dj) {
        $this->csrfTokenManager = $csrfTokenManager;
        $this->container = $container;
        $this->security = $security;
        $this->em = $em;
        $this->dj = $dj;
    }

    /**
     * Called on every request to decide if this authenticator should be
     * used for the request. Returning false will cause this authenticator
     * to be skipped.
     */
    public function supports(Request $request)
    {
        // Make sure ipaddress auth is enabled?
        $authmethods = $this->container->getParameter('domjudge.authmethods');
        $auth_allow_ipaddress = in_array('ipaddress', $authmethods);
        if (!$auth_allow_ipaddress) {
            return false;
        }

        // if there is already an authenticated user (likely due to the session)
        // then return null and skip authentication: there is no need.
        if ($this->security->getUser()) {
            return false;
        }

        // If it's stateless, we provide auth support every time
        $stateless_fw_contexts = [
            'security.firewall.map.context.api',
            'security.firewall.map.context.feed',
        ];
        $fwcontext = $request->attributes->get('_firewall_context', '');
        $ipAutologin = $this->dj->dbconfig_get('ip_autologin', false);
        if (in_array($fwcontext, $stateless_fw_contexts) || $ipAutologin) {
            return true;
        }

        // We also support authenticating if it's a POST to the login route and loginmethod is set correctly
        return $request->attributes->get('_route') === 'login'
            && $request->isMethod('POST')
            && $request->request->get('loginmethod') === 'ipaddress';
    }

    /**
     * Called on every request. Return whatever credentials you want to
     * be passed to getUser() as $credentials.
     */
    public function getCredentials(Request $request)
    {
        // Check if we're coming from the auth form
        if ($request->attributes->get('_route') === 'login' && $request->isMethod('POST')) {
            // Check CSRF token if it's coming from the login form
            $csrfToken = $request->request->get('_csrf_token');
            if (false === $this->csrfTokenManager->isTokenValid(new CsrfToken('authenticate', $csrfToken))) {
                throw new InvalidCsrfTokenException('Invalid CSRF token.');
            }
        }

        // Get the client IP address to use
        $clientIP = $this->container->get('request_stack')->getMasterRequest()->getClientIp();
        return [
            'username' => $request->request->get('_username'),
            'authbasic_username' => $request->headers->get('php-auth-user'),
            'ipaddress' => $clientIP,
        ];
    }

    public function getUser($credentials, UserProviderInterface $userProvider)
    {
        $userRepo = $this->em->getRepository('DOMJudgeBundle:User');
        $filters = [
            'ipAddress' => $credentials['ipaddress']
        ];

        if ($credentials['authbasic_username']) {
            $filters['username'] = $credentials['authbasic_username'];
        }

        if ($credentials['username']) {
            $filters['username'] = $credentials['username'];
        }

        $user = $userRepo->findOneBy($filters);

        // Fail if we didn't find a user with a matching ip address
        if ($user == null) {
            return null;
        }

        // if a User object, checkCredentials() is called
        return $userProvider->loadUserByUsername($user->getUsername());
    }

    public function checkCredentials($credentials, UserInterface $user)
    {
        // check credentials - e.g. make sure the password is valid
        // no credential check is needed in this case, as if we have a user,
        // it's because their IP address matched

        // return true to cause authentication success
        return true;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey)
    {
        // on success, let the request continue
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
    {
        // We never fail the authentication request, something else might handle it
        return null;
    }

    /**
     * Called when authentication is needed, but it's not sent
     */
    public function start(Request $request, AuthenticationException $authException = null)
    {
        // If this is the guard that fails/is configured to allow access as the entry_point
        // send the user a basic auth dialog, as that's probably what they're expecting
        $resp = new Response('', Response::HTTP_UNAUTHORIZED);
        $resp->headers->set('WWW-Authenticate', sprintf('Basic realm="%s"', 'Secured Area'));
        return $resp;
    }

    public function supportsRememberMe()
    {
        return false;
    }
}
