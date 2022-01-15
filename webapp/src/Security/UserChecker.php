<?php declare(strict_types=1);

namespace App\Security;

use App\Entity\User as DOMjudgeUser;
use Symfony\Component\Security\Core\Exception\DisabledException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{

    public function checkPreAuth(UserInterface $user)
    {
        if (!$user instanceof DOMjudgeUser) {
            return;
        }
    }

    public function checkPostAuth(UserInterface $user)
    {
        if (!$user instanceof DOMjudgeUser) {
            return;
        }

        if (!$user->getEnabled()) {
            throw new DisabledException();
        }
    }
}
