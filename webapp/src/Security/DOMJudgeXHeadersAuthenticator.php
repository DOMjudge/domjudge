<?php declare(strict_types=1);

namespace App\Security;

use App\Service\ConfigurationService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class DOMJudgeXHeadersAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    use TargetPathTrait;

    private Security $security;
    private UserProviderInterface $userProvider;
    private UserPasswordHasherInterface $hasher;
    private ConfigurationService $config;
    private RouterInterface $router;

    public function __construct(
        Security $security,
        UserProviderInterface $userProvider,
        UserPasswordHasherInterface $hasher,
        ConfigurationService $config,
        RouterInterface $router
    ) {
        $this->security     = $security;
        $this->userProvider = $userProvider;
        $this->hasher       = $hasher;
        $this->config       = $config;
        $this->router       = $router;
    }

    /**
     * Called on every request to decide if this authenticator should be
     * used for the request. Returning false will cause this authenticator
     * to be skipped.
     */
    public function supports(Request $request): bool
    {
        $authmethods = $this->config->get('auth_methods');
        $auth_allow_xheaders = in_array('xheaders', $authmethods);
        if (!$auth_allow_xheaders) {
            return false;
        }

        // If there is already an authenticated user (likely due to the session)
        // then return null and skip authentication: there is no need.
        if ($this->security->getUser()) {
            return false;
        }

        if (!$request->headers->has('X-DOMjudge-Login')
            || !$request->headers->has('X-DOMjudge-Pass')) {
            return false;
        }

        // We also support authenticating if it's a POST to the login route.
        if ($request->attributes->get('_route') === 'login'
            && $request->isMethod('POST')
            && $request->request->get('loginmethod') === 'xheaders') {
            return true;
        }

        return false;
    }

    public function authenticate(Request $request): Passport
    {
        $username = trim($request->headers->get('X-DOMjudge-Login'));
        $password = base64_decode(trim($request->headers->get('X-DOMjudge-Pass')));
        return new Passport(new UserBadge($username), new PasswordCredentials($password));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $firewallName): ?Response
    {
        // On success, redirect to the last page or the homepage if it was a user triggered action.
        if ($request->attributes->get('_route') === 'login'
            && $request->isMethod('POST')
            && $request->request->get('loginmethod') === 'xheaders') {
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
        return null;
    }

    /**
     * Called when authentication is needed, but it's not sent.
     */
    public function start(Request $request, AuthenticationException $authException = null): Response
    {
        return new RedirectResponse($this->router->generate('login', [], UrlGeneratorInterface::ABSOLUTE_URL));
    }
}
