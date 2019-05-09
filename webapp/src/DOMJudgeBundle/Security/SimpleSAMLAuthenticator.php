<?php declare(strict_types=1);

namespace DOMJudgeBundle\Security;

use Sgomez\Bundle\SSPGuardBundle\Security\Authenticator\SSPGuardAuthenticator;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class SimpleSAMLAuthenticator extends SSPGuardAuthenticator
{
    public function start(Request $request, AuthenticationException $authException = null)
    {
        return new RedirectResponse($this->router->generate('login'));
    }
    
    public function getUser($credentials, UserProviderInterface $userProvider)
    {
        return $userProvider->loadUserByUsername($credentials[$this->authSource->getUserId()][0]);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
    {
        $this->saveAuthenticationErrorToSession($request, $exception);

        return new RedirectResponse($this->router->generate('login'));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey)
    {
        $targetPath = $this->getTargetPath($request, $providerKey);

        if (!$targetPath) {
            $targetPath = $this->router->generate('root');
        }

        return new RedirectResponse($targetPath);
    }

    // override supports() not to require a fixed authsource id
    public function supports(Request $request)
    {
        $match = $this->router->match($request->getPathInfo());
        return 'ssp_guard_check' === $match['_route'];
    }

    // override getCredentials() not to require a fixed authsource id
    public function getCredentials(Request $request)
    {
        $match = $this->router->match($request->getPathInfo());
        $this->authSource = $this->authSourceRegistry->getAuthSource($match['authSource']);
        return $this->authSource->getCredentials();
    }
}
