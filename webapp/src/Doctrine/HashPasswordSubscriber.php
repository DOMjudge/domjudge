<?php declare(strict_types=1);

namespace App\Doctrine;

use App\Entity\User;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class HashPasswordSubscriber implements EventSubscriber
{
    protected UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function getSubscribedEvents(): array
    {
        return [Events::prePersist, Events::preUpdate];
    }

    public function prePersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof User) {
            return;
        }

        $this->encodePassword($entity);
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof User) {
            return;
        }

        $this->encodePassword($entity);

        // Necessary to force the update to see the change.
        $em   = $args->getObjectManager();
        $meta = $em->getClassMetadata(get_class($entity));
        $em->getUnitOfWork()->recomputeSingleEntityChangeSet($meta, $entity);
    }

    private function encodePassword(User $entity): void
    {
        if (!$entity->getPlainPassword()) {
            return;
        }
        $encoded = $this->passwordHasher->hashPassword(
            $entity,
            $entity->getPlainPassword()
        );
        $entity->setPassword($encoded);
    }
}
