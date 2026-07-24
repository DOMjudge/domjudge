<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class AuthorizedUserService
{
    public function __construct(
        protected readonly AuthorizationCheckerInterface $authorizationChecker,
        protected readonly TokenStorageInterface $tokenStorage
    ) {}

    public function getUser(): ?User
    {
        $token = $this->tokenStorage->getToken();
        if ($token == null) {
            return null;
        }

        $user = $token->getUser();

        // Ignore user objects if they aren't an App user.
        // Covers cases where users are not logged in.
        if (!is_a($user, 'App\Entity\User')) {
            return null;
        }

        return $user;
    }

    public function checkRole(string $rolename, bool $check_superset = true): bool
    {
        $user = $this->getUser();
        if ($user === null) {
            return false;
        }

        if ($check_superset) {
            if ($this->authorizationChecker->isGranted('ROLE_ADMIN') &&
                ($rolename == 'team' && $user->getTeam() != null)) {
                return true;
            }
        }
        return $this->authorizationChecker->isGranted('ROLE_' . strtoupper($rolename));
    }
}
