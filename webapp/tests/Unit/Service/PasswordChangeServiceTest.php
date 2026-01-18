<?php declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Role;
use App\Entity\Team;
use App\Entity\TeamCategory;
use App\Entity\User;
use App\Service\AuthorizedUserService;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\PasswordChangeService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class PasswordChangeServiceTest extends TestCase
{
    private ConfigurationService&MockObject $config;
    private AuthorizedUserService&MockObject $authService;
    private EntityManagerInterface&MockObject $em;
    private UserPasswordHasherInterface&MockObject $passwordHasher;
    private DOMJudgeService&MockObject $dj;
    private PasswordChangeService $service;

    protected function setUp(): void
    {
        $this->config = $this->createMock(ConfigurationService::class);
        $this->authService = $this->createMock(AuthorizedUserService::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->dj = $this->createMock(DOMJudgeService::class);

        $this->service = new PasswordChangeService(
            $this->config,
            $this->authService,
            $this->em,
            $this->passwordHasher,
            $this->dj,
            8 // min password length
        );
    }

    public function testCanChangePasswordReturnsFalseWhenNoUser(): void
    {
        $this->authService->method('getUser')->willReturn(null);
        self::assertFalse($this->service->canChangePassword(null));
    }

    public function testCanChangePasswordReturnsFalseWhenUserHasNoPassword(): void
    {
        $user = new User();
        $user->setUsername('ipuser');
        $role = new Role();
        $role->setDjRole('jury');
        $user->addUserRole($role);

        $this->config->method('get')
            ->with('password_change_roles')
            ->willReturn(['jury']);

        self::assertFalse($this->service->canChangePassword($user));
    }

    public function testCanChangePasswordAllowedByRole(): void
    {
        $user = new User();
        $user->setUsername('juryuser');
        $user->setPassword('old-hash');
        $role = new Role();
        $role->setDjRole('jury');
        $user->addUserRole($role);

        $this->config->method('get')
            ->with('password_change_roles')
            ->willReturn(['jury']);

        self::assertTrue($this->service->canChangePassword($user));
    }

    public function testCanChangePasswordAllowedByCategory(): void
    {
        $category = new TeamCategory();
        $category->setAllowPasswordChange(true);

        $team = new Team();
        $team->addCategory($category);

        $user = new User();
        $user->setUsername('teamuser');
        $user->setPassword('old-hash');
        $user->setTeam($team);

        $this->config->method('get')
            ->with('password_change_roles')
            ->willReturn([]);

        self::assertTrue($this->service->canChangePassword($user));
    }

    public function testCanChangePasswordDisallowedWhenNeitherRoleNorCategoryAllows(): void
    {
        $category = new TeamCategory();
        $category->setAllowPasswordChange(false);

        $team = new Team();
        $team->addCategory($category);

        $user = new User();
        $user->setUsername('teamuser');
        $user->setPassword('old-hash');
        $user->setTeam($team);

        $this->config->method('get')
            ->with('password_change_roles')
            ->willReturn([]);

        self::assertFalse($this->service->canChangePassword($user));
    }

    public function testChangePasswordThrowsAccessDeniedWhenNotAllowed(): void
    {
        $user = new User();
        $user->setUsername('disallowed');
        $user->setPassword('old-hash');

        $this->config->method('get')
            ->with('password_change_roles')
            ->willReturn([]);

        $this->expectException(AccessDeniedHttpException::class);
        $this->service->changePassword($user, 'old-pass', 'valid-new-pass');
    }

    public function testChangePasswordThrowsBadRequestOnIncorrectCurrentPassword(): void
    {
        $user = new User();
        $user->setUsername('juryuser');
        $user->setPassword('old-hash');
        $role = new Role();
        $role->setDjRole('jury');
        $user->addUserRole($role);

        $this->config->method('get')
            ->with('password_change_roles')
            ->willReturn(['jury']);

        $this->passwordHasher->method('isPasswordValid')
            ->with($user, 'wrong-password')
            ->willReturn(false);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Current password is incorrect.');
        $this->service->changePassword($user, 'wrong-password', 'valid-new-pass');
    }

    public function testChangePasswordThrowsBadRequestWhenNewPasswordTooShort(): void
    {
        $user = new User();
        $user->setUsername('juryuser');
        $user->setPassword('old-hash');
        $role = new Role();
        $role->setDjRole('jury');
        $user->addUserRole($role);

        $this->config->method('get')
            ->with('password_change_roles')
            ->willReturn(['jury']);

        $this->passwordHasher->method('isPasswordValid')
            ->with($user, 'correct-old')
            ->willReturn(true);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('New password must be at least 8 characters.');
        $this->service->changePassword($user, 'correct-old', 'short');
    }

    public function testChangePasswordSuccess(): void
    {
        $user = new User();
        $user->setUsername('juryuser');
        $user->setPassword('old-hash');
        $user->setExternalid('jury-ext');
        $role = new Role();
        $role->setDjRole('jury');
        $user->addUserRole($role);

        $this->config->method('get')
            ->with('password_change_roles')
            ->willReturn(['jury']);

        $this->passwordHasher->method('isPasswordValid')
            ->with($user, 'correct-old')
            ->willReturn(true);

        $this->passwordHasher->method('hashPassword')
            ->with($user, 'brand-new-pass')
            ->willReturn('hashed-brand-new-pass');

        $this->em->expects($this->once())->method('flush');
        $this->dj->expects($this->once())
            ->method('auditlog')
            ->with('user', 'jury-ext', 'password changed');

        $this->service->changePassword($user, 'correct-old', 'brand-new-pass');

        self::assertSame('hashed-brand-new-pass', $user->getPassword());
    }
}
