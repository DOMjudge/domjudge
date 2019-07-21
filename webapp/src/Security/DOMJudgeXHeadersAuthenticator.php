<?php declare(strict_types=1);

namespace App\Security;

use App\Service\DOMJudgeService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class DOMJudgeXHeadersAuthenticator extends AbstractGuardAuthenticator
{
    use TargetPathTrait;

    private $security;
    private $encoder;
    private $dj;
    private $router;

    /**
     * DOMJudgeXHeadersAuthenticator constructor.
     * @param Security                     $security
     * @param UserPasswordEncoderInterface $encoder
     * @param DOMJudgeService              $dj
     * @param RouterInterface              $router
     */
    public function __construct(
        Security $security,
        UserPasswordEncoderInterface $encoder,
        DOMJudgeService $dj,
        RouterInterface $router
    ) {
        $this->security  = $security;
        $this->encoder   = $encoder;
        $this->dj        = $dj;
        $this->router    = $router;
    }

    /**
     * Called on every request to decide if this authenticator should be
     * used for the request. Returning false will cause this authenticator
     * to be skipped.
     */
    public function supports(Request $request)
    {
        $authmethods = $this->dj->dbconfig_get('auth_methods', []);
        $auth_allow_xheaders = in_array('xheaders', $authmethods);
        if (!$auth_allow_xheaders) {
            return false;
        }

        // if there is already an authenticated user (likely due to the session)
        // then return null and skip authentication: there is no need.
        if ($this->security->getUser()) {
            return false;
        }
        // We also support authenticating if it's a POST to the login route
        if ($request->attributes->get('_route') === 'login'
            && $request->isMethod('POST')
            && $request->request->get('loginmethod') === 'xheaders') {
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
            'username' => trim($request->headers->get('X-DOMjudge-Login')),
            'password' => $password = base64_decode(trim($request->headers->get('X-DOMjudge-Pass'))),
        ];
    }

    public function getUser($credentials, UserProviderInterface $userProvider)
    {
        if ($credentials['username'] == null) {
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
        // on success, redirect to the last page or the homepage if it was a user triggered action
        if ($request->attributes->get('_route') === 'login'
            && $request->isMethod('POST')
            && $request->request->get('loginmethod') === 'xheaders') {

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

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
    {
        return null;
    }

    /**
     * Called when authentication is needed, but it's not sent
     */
    public function start(Request $request, AuthenticationException $authException = null)
    {
        $data = array(
            'message' => 'Authentication Required'
        );

        return new JsonResponse($data, Response::HTTP_UNAUTHORIZED);
    }

    public function supportsRememberMe()
    {
        return false;
    }
}
