<?php declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\UserInterface;

#[AsEventListener]
readonly class UpdateUserRolesListener
{
    public function __construct(protected TokenStorageInterface $tokenStorage) {}

    public function __invoke(RequestEvent $event): void
    {
        // Only handle main requests, not sub requests.
        if (!$event->isMainRequest()) {
            return;
        }

        // If we have no token, do nothing.
        if (!$token = $this->tokenStorage->getToken()) {
            return;
        }

        $user  = $token->getUser();
        $roles = $token->getRoleNames();

        // If the roles from the token differ from the roles of the user,
        // update the token.
        if ($user instanceof UserInterface && $roles !== $user->getRoles()) {
            $token = new UsernamePasswordToken(
                $user,
                'main',
                $user->getRoles()
            );

            $this->tokenStorage->setToken($token);
        }
    }
}
