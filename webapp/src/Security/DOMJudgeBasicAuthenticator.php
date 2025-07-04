<?php declare(strict_types=1);
namespace App\Security;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;

class DOMJudgeBasicAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly Security $security
    ) {}

    /**
     * Called on every request to decide if this authenticator should be
     * used for the request. Returning false will cause this authenticator
     * to be skipped.
     */
    public function supports(Request $request): bool
    {
        // if there is already an authenticated user (likely due to the session)
        // then return null and skip authentication: there is no need.
        if ($this->security->getUser()) {
            return false;
        }

        // No credentials provided, so we can't try to auth anything.
        if ($request->headers->get('php-auth-user', null) === null) {
            return false;
        }

        // If it's stateless, we provide auth support every time.
        $stateless_fw_contexts = [
          'security.firewall.map.context.api',
          'security.firewall.map.context.metrics',
        ];
        $fwcontext = $request->attributes->get('_firewall_context', '');
        if (in_array($fwcontext, $stateless_fw_contexts)) {
            return true;
        }

        return false;
    }

    public function authenticate(Request $request): Passport
    {
        return new Passport(new UserBadge($request->headers->get('php-auth-user')), new PasswordCredentials($request->headers->get('php-auth-pw')));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // On success, let the request continue.
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        // We only throw an error if the credentials provided were wrong or the user doesn't exist.
        // Otherwise, we pass along to the next authenticator.
        if ($exception instanceof BadCredentialsException || $exception instanceof UserNotFoundException) {
            $resp = new Response('', Response::HTTP_UNAUTHORIZED);

            if (!$request->isXmlHttpRequest()) {
                $resp->headers->set('WWW-Authenticate', sprintf('Basic realm="%s"', 'Secured Area'));
            }

            return $resp;
        }

        // Let another guard authenticator handle it.
        return null;
    }
}
