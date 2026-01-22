<?php declare(strict_types=1);

namespace App\Doctrine;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsEntityListener(event: Events::prePersist, entity: User::class)]
#[AsEntityListener(event: Events::preUpdate, entity: User::class)]
readonly class UserPasswordHasher
{
    public function __construct(protected UserPasswordHasherInterface $passwordHasher) {}


    public function __invoke(User $user, PrePersistEventArgs|PreUpdateEventArgs $args): void
    {
        $this->encodePassword($user);

        if ($args instanceof PreUpdateEventArgs) {
            // Necessary to force the update to see the change.
            $em = $args->getObjectManager();
            $meta = $em->getClassMetadata(User::class);
            $em->getUnitOfWork()->recomputeSingleEntityChangeSet($meta, $user);
        }
    }

    private function encodePassword(User $user): void
    {
        if (!$user->getPlainPassword()) {
            return;
        }
        $encoded = $this->passwordHasher->hashPassword(
            $user,
            $user->getPlainPassword()
        );
        $user->setPassword($encoded);
    }
}
