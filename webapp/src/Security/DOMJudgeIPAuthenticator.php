<?php declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class DOMJudgeIPAuthenticator extends AbstractGuardAuthenticator
{
    use TargetPathTrait;

    private $csrfTokenManager;
    private $security;
    private $em;
    private $config;
    private $router;
    private $requestStack;

    /**
     * DOMJudgeIPAuthenticator constructor.
     *
     * @param CsrfTokenManagerInterface $csrfTokenManager
     * @param Security                  $security
     * @param EntityManagerInterface    $em
     * @param ConfigurationService      $config
     * @param RouterInterface           $router
     * @param RequestStack              $requestStack
     */
    public function __construct(
        CsrfTokenManagerInterface $csrfTokenManager,
        Security $security,
        EntityManagerInterface $em,
        ConfigurationService $config,
        RouterInterface $router,
        RequestStack $requestStack
    ) {
        $this->csrfTokenManager = $csrfTokenManager;
        $this->security         = $security;
        $this->em               = $em;
        $this->config           = $config;
        $this->router           = $router;
        $this->requestStack     = $requestStack;
    }

    /**
     * @inheritDoc
     */
    public function supports(Request $request)
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

    /**
     * @inheritDoc
     */
    public function getCredentials(Request $request)
    {
        // Check if we're coming from the auth form.
        if ($request->attributes->get('_route') === 'login' && $request->isMethod('POST')) {
            // Check CSRF token if it's coming from the login form
            $csrfToken = $request->request->get('_csrf_token');
            if (false === $this->csrfTokenManager->isTokenValid(new CsrfToken('authenticate', $csrfToken))) {
                throw new InvalidCsrfTokenException('Invalid CSRF token, please try again.');
            }
        }

        // Get the client IP address to use
        $clientIP = $this->requestStack->getMasterRequest()->getClientIp();
        return [
            'username' => $request->request->get('_username'),
            'authbasic_username' => $request->headers->get('php-auth-user'),
            'ipaddress' => $clientIP,
        ];
    }

    /**
     * @inheritDoc
     */
    public function getUser($credentials, UserProviderInterface $userProvider)
    {
        $userRepo = $this->em->getRepository(User::class);
        $filters  = [
            'ipAddress' => $credentials['ipaddress'],
            'enabled' => 1,
        ];

        if ($credentials['authbasic_username']) {
            $filters['username'] = $credentials['authbasic_username'];
        }

        if ($credentials['username']) {
            $filters['username'] = $credentials['username'];
        }

        $user        = null;
        $users       = $userRepo->findBy($filters);
        if (count($users) === 1) {
            $user = $users[0];
        }

        // Fail if we didn't find a user with a matching ip address
        if ($user == null) {
            return null;
        }

        // if a User object, checkCredentials() is called
        return $userProvider->loadUserByUsername($user->getUsername());
    }

    /**
     * @inheritDoc
     */
    public function checkCredentials($credentials, UserInterface $user)
    {
        // check credentials - e.g. make sure the password is valid
        // no credential check is needed in this case, as if we have a user,
        // it's because their IP address matched

        // return true to cause authentication success
        return true;
    }

    /**
     * @inheritDoc
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey)
    {
        // on success, redirect to the last page or the homepage if it was a user triggered action
        if ($request->attributes->get('_route') === 'login'
            && $request->isMethod('POST')
            && $request->request->get('loginmethod') === 'ipaddress') {

            // Use target URL from session if set
            if ($providerKey !== null &&
                $targetUrl = $this->getTargetPath($request->getSession(), $providerKey)) {
                $this->removeTargetPath($request->getSession(), $providerKey);
                return new RedirectResponse($targetUrl);
            }

            return new RedirectResponse($this->router->generate('root'));
        }
        return null;
    }

    /**
     * @inheritDoc
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
    {
        // We never fail the authentication request, something else might handle it
        return null;
    }

    /**
     * @inheritDoc
     */
    public function start(Request $request, AuthenticationException $authException = null)
    {
        // If this is the guard that fails/is configured to allow access as the entry_point
        // send the user a basic auth dialog, as that's probably what they're expecting
        $resp = new Response('', Response::HTTP_UNAUTHORIZED);
        $resp->headers->set('WWW-Authenticate', sprintf('Basic realm="%s"', 'Secured Area'));
        return $resp;
    }

    /**
     * @inheritDoc
     */
    public function supportsRememberMe()
    {
        return false;
    }
}
