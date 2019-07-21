<?php declare(strict_types=1);
namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;

class DOMJudgeBasicAuthenticator extends AbstractGuardAuthenticator
{
    private $csrfTokenManager;
    private $security;
    private $encoder;

    public function __construct(
        CsrfTokenManagerInterface $csrfTokenManager,
        Security $security,
        UserPasswordEncoderInterface $encoder
    ) {
        $this->csrfTokenManager = $csrfTokenManager;
        $this->security = $security;
        $this->encoder = $encoder;
    }

    /**
     * Called on every request to decide if this authenticator should be
     * used for the request. Returning false will cause this authenticator
     * to be skipped.
     */
    public function supports(Request $request)
    {
        // if there is already an authenticated user (likely due to the session)
        // then return null and skip authentication: there is no need.
        if ($this->security->getUser()) {
            return false;
        }

        // No credentials provided, so we can't try to auth anything
        if ($request->headers->get('php-auth-user', null) === null) {
          return false;
        }

        // If it's stateless, we provide auth support every time
        $stateless_fw_contexts = [
          'security.firewall.map.context.api',
        ];
        $fwcontext = $request->attributes->get('_firewall_context', '');
        if (in_array($fwcontext, $stateless_fw_contexts)) {
          return true;
        }

        return false;
    }

    /**
     * Called on every request. Return whatever credentials you want to
     * be passed to getUser() as $credentials.
     */
    public function getCredentials(Request $request)
    {
        return [
            'username'  => $request->headers->get('php-auth-user'),
            'password'  => $request->headers->get('php-auth-pw'),
        ];
    }

    public function getUser($credentials, UserProviderInterface $userProvider)
    {
        if ($credentials['username'] === null) {
            return null;
        }
        return $userProvider->loadUserByUsername($credentials['username']);
    }

    public function checkCredentials($credentials, UserInterface $user)
    {
        return $this->encoder->isPasswordValid($user, $credentials['password']);
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey)
    {
        // on success, let the request continue
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
    {
      # We only throw an error if the credentials provided were wrong or the user doesn't exist
      # Otherwise we pass along to the next authenticator
      if ($exception instanceof BadCredentialsException || $exception instanceof UsernameNotFoundException) {
        $resp = new Response('', Response::HTTP_UNAUTHORIZED);
        $resp->headers->set('WWW-Authenticate', sprintf('Basic realm="%s"', 'Secured Area'));
        return $resp;
      }

      // Let another guard authenticator handle it
      return null;
    }

    /**
     * Called when authentication is needed, but it's not sent
     */
    public function start(Request $request, AuthenticationException $authException = null)
    {
      $resp = new Response('', Response::HTTP_UNAUTHORIZED);
      $resp->headers->set('WWW-Authenticate', sprintf('Basic realm="%s"', 'Secured Area'));
      return $resp;
    }

    public function supportsRememberMe()
    {
        return false;
    }
}
