<?php declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\User;
use App\Security\UserChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\DisabledException;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The user checker is the last line that keeps a disabled account from being logged in,
 * whichever authenticator handled the request.
 */
class UserCheckerTest extends TestCase
{
    private UserChecker $userChecker;

    protected function setUp(): void
    {
        $this->userChecker = new UserChecker();
    }

    public function testDisabledUserIsRejected(): void
    {
        $user = (new User())->setEnabled(false);

        $this->expectException(DisabledException::class);
        $this->userChecker->checkPostAuth($user);
    }

    public function testEnabledUserIsAccepted(): void
    {
        $user = (new User())->setEnabled(true);

        $this->userChecker->checkPostAuth($user);

        // checkPostAuth() throws for a disabled user; reaching this point is the assertion.
        self::assertTrue($user->getEnabled());
    }


    public function testOtherUserImplementationsAreLeftAlone(): void
    {
        // checkPostAuth() throws for a disabled user of ours; returning at all is the assertion.
        $this->expectNotToPerformAssertions();

        $this->userChecker->checkPostAuth($this->createMock(UserInterface::class));
    }

    public function testCheckPreAuthDoesNothing(): void
    {
        $this->expectNotToPerformAssertions();

        $this->userChecker->checkPreAuth((new User())->setEnabled(false));
    }
}
