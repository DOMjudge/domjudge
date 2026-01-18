<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class PasswordChangeService
{
    public function __construct(
        private readonly ConfigurationService $config,
        private readonly AuthorizedUserService $authService,
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly DOMJudgeService $dj,
        #[Autowire(param: 'min_password_length')]
        private readonly int $minimumPasswordLength
    ) {}

    /**
     * Check if the specified user (or the currently logged-in user if null) is allowed to change their password.
     */
    public function canChangePassword(?User $user = null): bool
    {
        $user ??= $this->authService->getUser();
        if ($user === null || $user->getPassword() === null) {
            return false;
        }

        $roles        = $user->getRoleList();
        $allowedRoles = $this->config->get('password_change_roles');
        foreach ($roles as $role) {
            if (in_array($role, $allowedRoles, true)) {
                return true;
            }
        }

        if ($team = $user->getTeam()) {
            foreach ($team->getCategories() as $category) {
                if ($category->getAllowPasswordChange()) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Change a user's password after validating permissions, current password, and minimum length.
     */
    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (!$this->canChangePassword($user)) {
            throw new AccessDeniedHttpException('You are not allowed to change your password.');
        }

        if (!$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
            throw new BadRequestHttpException('Current password is incorrect.');
        }

        if (mb_strlen($newPassword) < $this->minimumPasswordLength) {
            throw new BadRequestHttpException(
                sprintf('New password must be at least %d characters.', $this->minimumPasswordLength)
            );
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $newPassword));
        $this->em->flush();
        $this->dj->auditlog('user', $user->getExternalid() ?? (string)$user->getUserid(), 'password changed');
    }
}
