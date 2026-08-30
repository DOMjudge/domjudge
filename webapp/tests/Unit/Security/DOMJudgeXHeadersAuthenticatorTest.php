<?php declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\User;
use App\Security\DOMJudgeXHeadersAuthenticator;
use App\Service\ConfigurationService;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;

/**
 * The X-headers authenticator takes a username and a base64 encoded password out of request
 * headers, so it must only ever run when that method is enabled and explicitly asked for.
 */
class DOMJudgeXHeadersAuthenticatorTest extends TestCase
{
    private const BOTH_HEADERS = [
        'X-DOMjudge-Login' => 'teamuser',
        'X-DOMjudge-Pass'  => 'cGFzc3dvcmQ=',
    ];

    private Security&MockObject $security;
    private ConfigurationService&MockObject $config;
    private RouterInterface&MockObject $router;

    protected function setUp(): void
    {
        $this->security = $this->createMock(Security::class);
        $this->config   = $this->createMock(ConfigurationService::class);
        $this->router   = $this->createMock(RouterInterface::class);
    }

    private function authenticator(): DOMJudgeXHeadersAuthenticator
    {
        return new DOMJudgeXHeadersAuthenticator($this->security, $this->config, $this->router);
    }

    /**
     * @param array<string, mixed> $parameters
     * @param array<string, string> $headers
     */
    private static function request(
        string $method = 'GET',
        array $parameters = [],
        ?string $route = null,
        array $headers = []
    ): Request {
        $request = Request::create('/', $method, $parameters);
        if ($route !== null) {
            $request->attributes->set('_route', $route);
        }
        foreach ($headers as $name => $value) {
            $request->headers->set($name, $value);
        }

        return $request;
    }

    /**
     * @param array<string, string> $headers
     */
    private static function loginRequest(array $headers, string $loginMethod = 'xheaders'): Request
    {
        return self::request('POST', ['loginmethod' => $loginMethod], 'login', $headers);
    }

    /**
     * @param string[] $authMethods
     */
    #[DataProvider('provideSupports')]
    public function testSupports(
        array $authMethods,
        bool $alreadyAuthenticated,
        Request $request,
        bool $expected
    ): void {
        $this->config->method('get')->willReturnCallback(
            fn(string $name) => $name === 'auth_methods' ? $authMethods : null
        );
        $this->security
            ->method('getUser')
            ->willReturn($alreadyAuthenticated ? new User() : null);

        self::assertSame($expected, $this->authenticator()->supports($request));
    }

    public static function provideSupports(): Generator
    {
        yield 'xheaders authentication disabled' => [
            ['password'], false, self::loginRequest(self::BOTH_HEADERS), false,
        ];
        yield 'already authenticated' => [
            ['xheaders'], true, self::loginRequest(self::BOTH_HEADERS), false,
        ];
        yield 'both headers on a login submit' => [
            ['xheaders'], false, self::loginRequest(self::BOTH_HEADERS), true,
        ];
        yield 'login header missing' => [
            ['xheaders'], false, self::loginRequest(['X-DOMjudge-Pass' => 'cGFzc3dvcmQ=']), false,
        ];
        yield 'password header missing' => [
            ['xheaders'], false, self::loginRequest(['X-DOMjudge-Login' => 'teamuser']), false,
        ];
        yield 'another login method requested' => [
            ['xheaders'], false, self::loginRequest(self::BOTH_HEADERS, 'password'), false,
        ];

        // Unlike the IP authenticator this one never authenticates outside the login form,
        // so the headers on their own are not enough on any route.
        yield 'headers without a login submit' => [
            ['xheaders'], false, self::request(headers: self::BOTH_HEADERS), false,
        ];
        yield 'headers on a GET of the login page' => [
            ['xheaders'], false, self::request(route: 'login', headers: self::BOTH_HEADERS), false,
        ];
    }

    public function testAuthenticateDecodesTheCredentials(): void
    {
        $request  = self::loginRequest(self::BOTH_HEADERS);
        $passport = $this->authenticator()->authenticate($request);

        self::assertSame('teamuser', $passport->getBadge(UserBadge::class)->getUserIdentifier());
        self::assertSame('password', $passport->getBadge(PasswordCredentials::class)->getPassword());
    }

    public function testAuthenticateTrimsTheHeaders(): void
    {
        $request = self::loginRequest([
            'X-DOMjudge-Login' => '  teamuser  ',
            'X-DOMjudge-Pass'  => '  cGFzc3dvcmQ=  ',
        ]);

        $passport = $this->authenticator()->authenticate($request);

        self::assertSame('teamuser', $passport->getBadge(UserBadge::class)->getUserIdentifier());
        self::assertSame('password', $passport->getBadge(PasswordCredentials::class)->getPassword());
    }

    public function testOnAuthenticationSuccessRedirectsToTheTargetPath(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $session->set('_security.main.target_path', '/team');

        $request = self::loginRequest(self::BOTH_HEADERS);
        $request->setSession($session);

        $response = $this->authenticator()->onAuthenticationSuccess(
            $request,
            $this->createMock(TokenInterface::class),
            'main'
        );

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/team', $response->getTargetUrl());
        self::assertFalse($session->has('_security.main.target_path'));
    }

    public function testOnAuthenticationSuccessRedirectsToTheRootWithoutATargetPath(): void
    {
        $this->router->method('generate')->with('root')->willReturn('/');

        $request = self::loginRequest(self::BOTH_HEADERS);
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
        self::assertNull(
            $this->authenticator()->onAuthenticationSuccess(
                self::request(headers: self::BOTH_HEADERS),
                $this->createMock(TokenInterface::class),
                'main'
            )
        );
    }

    public function testOnAuthenticationFailureLetsOtherAuthenticatorsTry(): void
    {
        self::assertNull(
            $this->authenticator()->onAuthenticationFailure(self::request(), new BadCredentialsException())
        );
    }

    public function testStartRedirectsToTheLoginPage(): void
    {
        $this->router->method('generate')->willReturn('https://example.org/login');

        $response = $this->authenticator()->start(self::request());

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('https://example.org/login', $response->getTargetUrl());
    }
}
