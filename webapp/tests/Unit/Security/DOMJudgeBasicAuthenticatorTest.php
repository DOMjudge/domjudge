<?php declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\User;
use App\Security\DOMJudgeBasicAuthenticator;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;

/**
 * HTTP basic authentication is only offered on the stateless firewalls; the interactive
 * firewall uses the login form instead.
 */
class DOMJudgeBasicAuthenticatorTest extends TestCase
{
    private const API_FIREWALL = 'security.firewall.map.context.api';
    private const METRICS_FIREWALL = 'security.firewall.map.context.metrics';
    private const MAIN_FIREWALL = 'security.firewall.map.context.main';

    private Security&MockObject $security;

    protected function setUp(): void
    {
        $this->security = $this->createMock(Security::class);
    }

    private function authenticator(): DOMJudgeBasicAuthenticator
    {
        return new DOMJudgeBasicAuthenticator($this->security);
    }

    private static function request(
        string $firewallContext = self::MAIN_FIREWALL,
        ?string $authUser = 'teamuser',
        ?string $authPassword = 'password'
    ): Request {
        $request = Request::create('/api/v4/contests');
        $request->attributes->set('_firewall_context', $firewallContext);
        if ($authUser !== null) {
            $request->headers->set('php-auth-user', $authUser);
        }
        if ($authPassword !== null) {
            $request->headers->set('php-auth-pw', $authPassword);
        }

        return $request;
    }

    #[DataProvider('provideSupports')]
    public function testSupports(bool $alreadyAuthenticated, Request $request, bool $expected): void
    {
        $this->security
            ->method('getUser')
            ->willReturn($alreadyAuthenticated ? new User() : null);

        self::assertSame($expected, $this->authenticator()->supports($request));
    }

    public static function provideSupports(): Generator
    {
        yield 'api firewall with credentials' => [
            false, self::request(self::API_FIREWALL), true,
        ];
        yield 'metrics firewall with credentials' => [
            false, self::request(self::METRICS_FIREWALL), true,
        ];
        yield 'main firewall with credentials' => [
            false, self::request(), false,
        ];
        yield 'api firewall without credentials' => [
            false, self::request(self::API_FIREWALL, authUser: null), false,
        ];
        yield 'already authenticated' => [
            true, self::request(self::API_FIREWALL), false,
        ];
    }

    public function testAuthenticateUsesTheBasicAuthCredentials(): void
    {
        $passport = $this->authenticator()->authenticate(self::request(self::API_FIREWALL));

        self::assertSame('teamuser', $passport->getBadge(UserBadge::class)->getUserIdentifier());
        self::assertSame('password', $passport->getBadge(PasswordCredentials::class)->getPassword());
    }

    public function testOnAuthenticationSuccessLetsTheRequestContinue(): void
    {
        self::assertNull(
            $this->authenticator()->onAuthenticationSuccess(
                self::request(self::API_FIREWALL),
                $this->createMock(TokenInterface::class),
                'api'
            )
        );
    }

    #[DataProvider('provideRejectedExceptions')]
    public function testOnAuthenticationFailureChallenges(AuthenticationException $exception): void
    {
        $response = $this->authenticator()->onAuthenticationFailure(self::request(self::API_FIREWALL), $exception);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        // Command line clients need the challenge to know to send credentials.
        self::assertSame('Basic realm="Secured Area"', $response->headers->get('WWW-Authenticate'));
    }

    public static function provideRejectedExceptions(): Generator
    {
        yield 'bad credentials' => [new BadCredentialsException()];
        yield 'unknown user' => [new UserNotFoundException()];
    }

    public function testOnAuthenticationFailureDoesNotChallengeXhrRequests(): void
    {
        $request = self::request(self::API_FIREWALL);
        // Without this the browser stacks its own login dialog on top of ours.
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');

        $response = $this->authenticator()->onAuthenticationFailure($request, new BadCredentialsException());

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        self::assertFalse($response->headers->has('WWW-Authenticate'));
    }

    public function testOnAuthenticationFailurePassesOnOtherExceptions(): void
    {
        self::assertNull(
            $this->authenticator()->onAuthenticationFailure(
                self::request(self::API_FIREWALL),
                new AuthenticationException()
            )
        );
    }
}
