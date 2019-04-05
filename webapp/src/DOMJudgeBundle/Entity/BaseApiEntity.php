<?php declare(strict_types=1);

namespace DOMJudgeBundle\Entity;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\MappingException;
use DOMJudgeBundle\Service\EventLogService;
use Exception;

/**
 * Class BaseApiEntity
 *
 * Base entity class API entities should use to support getting the API ID
 *
 * @package DOMJudgeBundle\Entity
 */
abstract class BaseApiEntity
{
    /**
     * Get the API ID for this entity
     * @param EventLogService        $eventLogService
     * @param EntityManagerInterface $entityManager
     * @return mixed
     * @throws Exception
     */
    public function getApiId(EventLogService $eventLogService, EntityManagerInterface $entityManager)
    {
        if ($field = $eventLogService->externalIdFieldForEntity($this)) {
            return $this->{$field};
        } else {
            $metadata = $entityManager->getClassMetadata(get_class($this));
            try {
                return $this->{$metadata->getSingleIdentifierFieldName()};
            } catch (MappingException $e) {
                throw new \BadMethodCallException(sprintf('Entity \'%s\' as a composite primary key',
                                                          get_class($this)));
            }
        }
    }
}
