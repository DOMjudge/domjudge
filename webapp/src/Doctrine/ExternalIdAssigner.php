<?php declare(strict_types=1);

namespace App\Doctrine;

use App\Entity\CalculatedExternalIdBasedOnRelatedFieldInterface;
use App\Entity\ExternalIdFromInternalIdInterface;
use App\Entity\PrefixedExternalIdInShadowModeInterface;
use App\Entity\PrefixedExternalIdInterface;
use App\Service\DOMJudgeService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::postPersist)]
class ExternalIdAssigner
{
    public function __construct(
        protected readonly EntityManagerInterface $em,
        protected readonly DOMJudgeService $dj,
    ) {}

    public function __invoke(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();
        if ((!$entity instanceof ExternalIdFromInternalIdInterface) && (!$entity instanceof CalculatedExternalIdBasedOnRelatedFieldInterface)) {
            return;
        }

        if ($entity->getExternalId()) {
            return;
        }

        $entityClass = $entity::class;
        if ($entity instanceof CalculatedExternalIdBasedOnRelatedFieldInterface) {
            $externalid = $entity->getCalculatedExternalId();
        } else {
            $metadata = $this->em->getClassMetadata($entityClass);
            $primaryKeyField = $metadata->getSingleIdentifierFieldName();
            $externalid = (string)$metadata->getFieldValue($entity, $primaryKeyField);
            if ($entity instanceof PrefixedExternalIdInterface) {
                $externalid = 'dj-' . $externalid;
            } elseif ($this->dj->shadowMode() && $entity instanceof PrefixedExternalIdInShadowModeInterface) {
                $externalid = 'dj-' . $externalid;
            }
        }

        // Check if there is already an entity with that external ID
        $existingEntity = $this->em->getRepository($entityClass)->findOneBy(['externalid' => $externalid]);
        if ($existingEntity) {
            throw new ExternalIdAlreadyExistsException($entityClass, $externalid);
        }

        $entity->setExternalId($externalid);

        // Note: flushing in postPersist is not safe in general, but we make sure we only do this
        // once, so we can't have an infinite loop here.
        $this->em->flush();
    }
}
