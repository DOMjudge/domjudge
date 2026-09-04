<?php declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\User;
use App\Security\UserStateUpdater;
use App\Service\DOMJudgeService;
use Doctrine\ORM\EntityManagerInterface;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Event\AuthenticationSuccessEvent;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;

/**
 * Which timestamp a successful authentication updates depends on the firewall it happened on:
 * the API is called constantly, so it must not touch the interactive login time or write an
 * audit log entry for every request.
 */
class UserStateUpdaterTest extends TestCase
{
    private DOMJudgeService&MockObject $dj;
    private EntityManagerInterface&MockObject $em;
    private RequestStack $requestStack;

    protected function setUp(): void
    {
        $this->dj           = $this->createMock(DOMJudgeService::class);
        $this->em           = $this->createMock(EntityManagerInterface::class);
        $this->requestStack = new RequestStack();
        $this->requestStack->push(Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '192.0.2.7']));
    }

    private function updater(): UserStateUpdater
    {
        return new UserStateUpdater($this->dj, $this->em, $this->requestStack);
    }

    private function update(User $user, string $firewallName): void
    {
        $this->updater()->updateUserState(
            new AuthenticationSuccessEvent(new PostAuthenticationToken($user, $firewallName, ['ROLE_USER']))
        );
    }

    public function testItSubscribesToSuccessfulAuthentication(): void
    {
        self::assertSame(
            [AuthenticationSuccessEvent::class => 'updateUserState'],
            UserStateUpdater::getSubscribedEvents()
        );
    }

    public function testMainFirewallUpdatesTheInteractiveLoginTime(): void
    {
        $this->dj->method('getClientIp')->willReturn('10.0.0.5');
        $this->em->expects(self::once())->method('flush');

        $user = (new User())->setUsername('teamuser');
        $this->update($user, 'main');

        self::assertNotNull($user->getLastLogin());
        self::assertNull($user->getLastApiLogin());
        self::assertSame('10.0.0.5', $user->getLastIpAddress());
    }

    #[DataProvider('provideStatelessFirewalls')]
    public function testStatelessFirewallsUpdateTheApiLoginTime(string $firewallName): void
    {
        $this->dj->method('getClientIp')->willReturn('10.0.0.5');
        // The API is polled constantly, so it gets its own timestamp and no audit entry.
        $this->dj->expects(self::never())->method('auditlog');

        $user = (new User())->setUsername('teamuser');
        $this->update($user, $firewallName);

        self::assertNotNull($user->getLastApiLogin());
        self::assertNull($user->getLastLogin());
        self::assertSame('10.0.0.5', $user->getLastIpAddress());
    }

    public static function provideStatelessFirewalls(): Generator
    {
        yield ['api'];
        yield ['metrics'];
    }

    public function testOtherFirewallsUpdateNeitherLoginTime(): void
    {
        $this->dj->method('getClientIp')->willReturn('10.0.0.5');

        $user = (new User())->setUsername('teamuser');
        $this->update($user, 'somethingelse');

        self::assertNull($user->getLastLogin());
        self::assertNull($user->getLastApiLogin());
        self::assertSame('10.0.0.5', $user->getLastIpAddress());
    }

    public function testMainFirewallWritesAnAuditLogEntry(): void
    {
        $this->dj->method('getClientIp')->willReturn('10.0.0.5');
        $this->dj
            ->expects(self::once())
            ->method('auditlog')
            // The audit log uses the main request's IP, not the one the service reports.
            ->with('user', 'teamuser', 'logged on on 192.0.2.7', null, 'teamuser');

        $user = (new User())->setUsername('teamuser');
        $user->setExternalid('teamuser');

        $this->update($user, 'main');
    }

    public function testFirstLoginIsRecordedOnlyOnce(): void
    {
        $this->dj->method('getClientIp')->willReturn('10.0.0.5');

        $user = (new User())->setUsername('teamuser');
        $this->update($user, 'main');

        $firstLogin = $user->getFirstLogin();
        self::assertNotNull($firstLogin);

        $this->update($user, 'main');

        self::assertSame($firstLogin, $user->getFirstLogin());
    }

    public function testUsersFromAnotherProviderAreIgnored(): void
    {
        $this->em->expects(self::never())->method('flush');
        $this->dj->expects(self::never())->method('auditlog');

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn(null);

        $this->updater()->updateUserState(new AuthenticationSuccessEvent($token));
    }

    public function testTokensWithoutAFirewallNameCountAsMain(): void
    {
        $this->dj->method('getClientIp')->willReturn('10.0.0.5');
        $this->dj->expects(self::once())->method('auditlog');

        $user  = (new User())->setUsername('teamuser');
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $this->updater()->updateUserState(new AuthenticationSuccessEvent($token));

        self::assertNotNull($user->getLastLogin());
        self::assertNull($user->getLastApiLogin());
    }
}
