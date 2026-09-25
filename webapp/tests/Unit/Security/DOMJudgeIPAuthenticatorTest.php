<?php declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\DOMJudgeIPAuthenticator;
use App\Service\ConfigurationService;
use Doctrine\ORM\EntityManagerInterface;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

/**
 * The IP authenticator logs a user in purely based on the IP address the request came from,
 * so both the decision to run it at all and the lookup it performs are security relevant.
 */
class DOMJudgeIPAuthenticatorTest extends TestCase
{
    private const API_FIREWALL = 'security.firewall.map.context.api';
    private const METRICS_FIREWALL = 'security.firewall.map.context.metrics';
    private const MAIN_FIREWALL = 'security.firewall.map.context.main';

    private CsrfTokenManagerInterface&MockObject $csrfTokenManager;
    private Security&MockObject $security;
    private EntityManagerInterface&MockObject $em;
    private ConfigurationService&MockObject $config;
    private RouterInterface&MockObject $router;
    private RequestStack $requestStack;
    private UserRepository&MockObject $userRepository;

    protected function setUp(): void
    {
        $this->csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $this->security         = $this->createMock(Security::class);
        $this->em               = $this->createMock(EntityManagerInterface::class);
        $this->config           = $this->createMock(ConfigurationService::class);
        $this->router           = $this->createMock(RouterInterface::class);
        $this->requestStack     = new RequestStack();
        $this->userRepository   = $this->createMock(UserRepository::class);

        $this->em
            ->method('getRepository')
            ->with(User::class)
            ->willReturn($this->userRepository);
    }

    private function authenticator(): DOMJudgeIPAuthenticator
    {
        return new DOMJudgeIPAuthenticator(
            $this->csrfTokenManager,
            $this->security,
            $this->em,
            $this->config,
            $this->router,
            $this->requestStack
        );
    }

    /**
     * @param string[] $authMethods
     */
    private function configReturns(array $authMethods, bool $ipAutologin = false): void
    {
        $this->config
            ->method('get')
            ->willReturnCallback(fn(string $name) => match ($name) {
                'auth_methods' => $authMethods,
                'ip_autologin' => $ipAutologin,
                default        => null,
            });
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private static function request(
        string $method = 'GET',
        array $parameters = [],
        ?string $route = null,
        string $firewallContext = self::MAIN_FIREWALL,
        string $clientIp = '10.0.0.5'
    ): Request {
        $request = Request::create('/', $method, $parameters, [], [], ['REMOTE_ADDR' => $clientIp]);
        if ($route !== null) {
            $request->attributes->set('_route', $route);
        }
        $request->attributes->set('_firewall_context', $firewallContext);

        return $request;
    }

    /**
     * @param string[] $authMethods
     */
    #[DataProvider('provideSupports')]
    public function testSupports(
        array $authMethods,
        bool $ipAutologin,
        bool $alreadyAuthenticated,
        Request $request,
        bool $expected
    ): void {
        $this->configReturns($authMethods, $ipAutologin);
        $this->security
            ->method('getUser')
            ->willReturn($alreadyAuthenticated ? new User() : null);

        self::assertSame($expected, $this->authenticator()->supports($request));
    }

    public static function provideSupports(): Generator
    {
        // Without 'ipaddress' in auth_methods the authenticator must never run, not even on
        // the stateless API firewall and not even when IP autologin is switched on.
        yield 'ipaddress authentication disabled' => [
            ['password'], false, false,
            self::request(firewallContext: self::API_FIREWALL),
            false,
        ];
        yield 'ipaddress disabled but autologin on' => [
            ['password'], true, false,
            self::request(),
            false,
        ];

        // Stateless firewalls authenticate on every request.
        yield 'api firewall' => [
            ['ipaddress'], false, false,
            self::request(firewallContext: self::API_FIREWALL),
            true,
        ];
        yield 'metrics firewall' => [
            ['ipaddress'], false, false,
            self::request(firewallContext: self::METRICS_FIREWALL),
            true,
        ];

        // On the main firewall the authenticator only runs with autologin enabled...
        yield 'main firewall without autologin' => [
            ['ipaddress'], false, false,
            self::request(),
            false,
        ];
        yield 'main firewall with autologin' => [
            ['ipaddress'], true, false,
            self::request(),
            true,
        ];

        // ...or when the login form was submitted asking for it.
        yield 'login form with ipaddress method' => [
            ['ipaddress'], false, false,
            self::request('POST', ['loginmethod' => 'ipaddress'], 'login'),
            true,
        ];
        yield 'login form with another method' => [
            ['ipaddress'], false, false,
            self::request('POST', ['loginmethod' => 'password'], 'login'),
            false,
        ];
        yield 'login page without submitting' => [
            ['ipaddress'], false, false,
            self::request(route: 'login'),
            false,
        ];

        // An already authenticated user needs no further authentication, except on the login
        // page where they may be asking to log in as somebody else.
        yield 'already authenticated on a normal page' => [
            ['ipaddress'], true, true,
            self::request(),
            false,
        ];
        yield 'already authenticated on the api firewall' => [
            ['ipaddress'], false, true,
            self::request(firewallContext: self::API_FIREWALL),
            false,
        ];
        yield 'already authenticated on the login page' => [
            ['ipaddress'], false, true,
            self::request('POST', ['loginmethod' => 'ipaddress'], 'login'),
            true,
        ];
    }

    public function testAuthenticateOnlyMatchesEnabledUsersOnTheClientIp(): void
    {
        $user = (new User())->setUsername('teamuser');
        $this->userRepository
            ->expects(self::once())
            ->method('findBy')
            // Dropping either filter would turn this into an authentication bypass.
            ->with(['ipAddress' => '10.0.0.5', 'enabled' => 1])
            ->willReturn([$user]);

        $request = self::request();
        $this->requestStack->push($request);

        $passport = $this->authenticator()->authenticate($request);

        self::assertSame('teamuser', $passport->getBadge(UserBadge::class)->getUserIdentifier());
    }

    public function testAuthenticateUsesTheClientIpOfTheMainRequest(): void
    {
        $this->userRepository
            ->expects(self::once())
            ->method('findBy')
            ->with(['ipAddress' => '192.0.2.7', 'enabled' => 1])
            ->willReturn([(new User())->setUsername('teamuser')]);

        $mainRequest = self::request(clientIp: '192.0.2.7');
        $this->requestStack->push($mainRequest);
        $subRequest = self::request(clientIp: '10.0.0.5');
        $this->requestStack->push($subRequest);

        $this->authenticator()->authenticate($subRequest);
    }

    public function testAuthenticateNarrowsDownByBasicAuthUsername(): void
    {
        $this->userRepository
            ->expects(self::once())
            ->method('findBy')
            ->with(['ipAddress' => '10.0.0.5', 'enabled' => 1, 'username' => 'basicuser'])
            ->willReturn([(new User())->setUsername('basicuser')]);

        $request = self::request();
        $request->headers->set('php-auth-user', 'basicuser');
        $this->requestStack->push($request);

        $this->authenticator()->authenticate($request);
    }

    public function testAuthenticateNarrowsDownByPostedUsername(): void
    {
        $this->userRepository
            ->expects(self::once())
            ->method('findBy')
            ->with(['ipAddress' => '10.0.0.5', 'enabled' => 1, 'username' => 'formuser'])
            ->willReturn([(new User())->setUsername('formuser')]);

        $request = self::request('POST', ['_username' => 'formuser']);
        $this->requestStack->push($request);

        $this->authenticator()->authenticate($request);
    }

    public function testPostedUsernameOverridesBasicAuthUsername(): void
    {
        $this->userRepository
            ->expects(self::once())
            ->method('findBy')
            ->with(['ipAddress' => '10.0.0.5', 'enabled' => 1, 'username' => 'formuser'])
            ->willReturn([(new User())->setUsername('formuser')]);

        $request = self::request('POST', ['_username' => 'formuser']);
        $request->headers->set('php-auth-user', 'basicuser');
        $this->requestStack->push($request);

        $this->authenticator()->authenticate($request);
    }

    public function testAuthenticateFailsWithoutAMatchingUser(): void
    {
        $this->userRepository->method('findBy')->willReturn([]);

        $request = self::request();
        $this->requestStack->push($request);

        $this->expectException(UserNotFoundException::class);
        $this->authenticator()->authenticate($request);
    }

    public function testAuthenticateFailsWhenTheIpMatchesMoreThanOneUser(): void
    {
        $this->userRepository->method('findBy')->willReturn([
            (new User())->setUsername('one'),
            (new User())->setUsername('two'),
        ]);

        $request = self::request();
        $this->requestStack->push($request);

        $this->expectException(UserNotFoundException::class);
        $this->authenticator()->authenticate($request);
    }

    public function testAuthenticateRejectsAnInvalidCsrfTokenOnTheLoginForm(): void
    {
        $this->csrfTokenManager->method('isTokenValid')->willReturn(false);
        $this->userRepository->expects(self::never())->method('findBy');

        $request = self::request('POST', ['_csrf_token' => 'wrong'], 'login');
        $this->requestStack->push($request);

        $this->expectException(InvalidCsrfTokenException::class);
        $this->authenticator()->authenticate($request);
    }

    public function testAuthenticateAcceptsAValidCsrfTokenOnTheLoginForm(): void
    {
        $this->csrfTokenManager->method('isTokenValid')->willReturn(true);
        $this->userRepository
            ->method('findBy')
            ->willReturn([(new User())->setUsername('teamuser')]);

        $request = self::request('POST', ['_csrf_token' => 'right'], 'login');
        $this->requestStack->push($request);

        $passport = $this->authenticator()->authenticate($request);

        self::assertSame('teamuser', $passport->getBadge(UserBadge::class)->getUserIdentifier());
    }

    public function testAuthenticateDoesNotCheckCsrfOutsideTheLoginForm(): void
    {
        // Only a login form submission carries a CSRF token.
        $this->csrfTokenManager->expects(self::never())->method('isTokenValid');
        $this->userRepository
            ->method('findBy')
            ->willReturn([(new User())->setUsername('teamuser')]);

        $request = self::request(firewallContext: self::API_FIREWALL);
        $this->requestStack->push($request);

        $this->authenticator()->authenticate($request);
    }

    public function testOnAuthenticationSuccessRedirectsToTheTargetPathAfterLogin(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $session->set('_security.main.target_path', '/jury/problems');

        $request = self::request('POST', ['loginmethod' => 'ipaddress'], 'login');
        $request->setSession($session);

        $response = $this->authenticator()->onAuthenticationSuccess(
            $request,
            $this->createMock(TokenInterface::class),
            'main'
        );

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/jury/problems', $response->getTargetUrl());
        self::assertFalse($session->has('_security.main.target_path'));
    }

    public function testOnAuthenticationSuccessRedirectsToTheRootWithoutATargetPath(): void
    {
        $this->router->method('generate')->with('root')->willReturn('/');

        $request = self::request('POST', ['loginmethod' => 'ipaddress'], 'login');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $response = $this->authenticator()->onAuthenticationSuccess(
            $request,
            $this->createMock(TokenInterface::class),
            'main'
        );

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/', $response->getTargetUrl());
    }

    public function testOnAuthenticationSuccessDoesNotRedirectOutsideTheLoginForm(): void
    {
        $response = $this->authenticator()->onAuthenticationSuccess(
            self::request(),
            $this->createMock(TokenInterface::class),
            'main'
        );

        self::assertNull($response);
    }

    public function testOnAuthenticationFailureLetsOtherAuthenticatorsTry(): void
    {
        self::assertNull(
            $this->authenticator()->onAuthenticationFailure(self::request(), new UserNotFoundException())
        );
    }

    public function testStartAsksForBasicAuthentication(): void
    {
        $response = $this->authenticator()->start(self::request());

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        self::assertSame('Basic realm="Secured Area"', $response->headers->get('WWW-Authenticate'));
    }
}
