<?php declare(strict_types=1);

namespace App\Serializer;

use App\Entity\Clarification;
use App\Entity\ExternalRelationshipEntityInterface;
use App\Entity\Submission;
use App\Service\EventLogService;
use BadMethodCallException;
use JMS\Serializer\EventDispatcher\Events;
use JMS\Serializer\EventDispatcher\EventSubscriberInterface;
use JMS\Serializer\EventDispatcher\ObjectEvent;
use JMS\Serializer\JsonSerializationVisitor;
use JMS\Serializer\Metadata\StaticPropertyMetadata;

/**
 * Class SetExternalIdVisitor
 * @package App\Serializer
 */
class SetExternalIdVisitor implements EventSubscriberInterface
{
    protected EventLogService $eventLogService;

    public function __construct(EventLogService $eventLogService)
    {
        $this->eventLogService = $eventLogService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            [
                'event' => Events::POST_SERIALIZE,
                'format' => 'json',
                'method' => 'onPostSerialize'
            ],
        ];
    }

    public function onPostSerialize(ObjectEvent $event): void
    {
        /** @var JsonSerializationVisitor $visitor */
        $visitor = $event->getVisitor();
        $object  = $event->getObject();

        try {
            if ($externalIdField = $this->eventLogService->externalIdFieldForEntity(get_class($object))) {
                $method = sprintf('get%s', ucfirst($externalIdField));
                if (method_exists($object, $method)) {
                    $property = new StaticPropertyMetadata(
                        get_class($object),
                        'id',
                        null
                    );
                    $visitor->visitProperty($property, $object->{$method}());
                }
            } elseif (($object instanceof Submission || $object instanceof Clarification) && $object->getExternalid() !== null) {
                // Special case for submissions and clarifications: they can have an external ID even if when running in
                // full local mode, because one can use the API to upload one with an external ID
                $property = new StaticPropertyMetadata(
                    get_class($object),
                    'id',
                    null
                );
                $visitor->visitProperty($property, $object->getExternalid());
            }
        } catch (BadMethodCallException $e) {
            // Ignore these exceptions, as this means this is not an entity or it is not configured.
        }

        if ($object instanceof ExternalRelationshipEntityInterface) {
            foreach ($object->getExternalRelationships() as $field => $entity) {
                try {
                    if (is_array($entity)) {
                        if (empty($entity) || !($externalIdField = $this->eventLogService->externalIdFieldForEntity(get_class($entity[0])))) {
                            continue;
                        }
                        $method = sprintf('get%s', ucfirst($externalIdField));
                        $property = new StaticPropertyMetadata(
                            get_class($object),
                            $field,
                            null
                        );
                        $data = [];
                        foreach ($entity as $item) {
                            $data[] = $item->{$method}();
                        }
                        $visitor->visitProperty($property, $data);
                    } elseif ($entity && $externalIdField = $this->eventLogService->externalIdFieldForEntity(get_class($entity))) {
                        $method = sprintf('get%s', ucfirst($externalIdField));
                        if (method_exists($entity, $method)) {
                            $property = new StaticPropertyMetadata(
                                get_class($object),
                                $field,
                                null
                            );
                            $visitor->visitProperty($property, $entity->{$method}());
                        }
                    } elseif ($entity && ($entity instanceof Submission || $entity instanceof Clarification) && $entity->getExternalid() !== null) {
                        // Special case for submissions and clarifications: they can have an external ID even if when running in
                        // full local mode, because one can use the API to upload one with an external ID
                        $property = new StaticPropertyMetadata(
                            get_class($entity),
                            $field,
                            null
                        );
                        $visitor->visitProperty($property, $entity->getExternalid());
                    }
                } catch (BadMethodCallException $e) {
                    // Ignore these exceptions, as this means this is not an entity or it is not configured.
                }
            }
        }
    }
}
