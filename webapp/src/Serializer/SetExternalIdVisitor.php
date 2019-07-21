<?php declare(strict_types=1);

namespace App\Serializer;

use App\Entity\ExternalRelationshipEntityInterface;
use App\Service\EventLogService;
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
    /**
     * @var EventLogService
     */
    protected $eventLogService;

    /**
     * ContestVisitor constructor.
     * @param EventLogService $eventLogService
     */
    public function __construct(EventLogService $eventLogService)
    {
        $this->eventLogService = $eventLogService;
    }

    /**
     * @inheritdoc
     */
    public static function getSubscribedEvents()
    {
        return [
            [
                'event' => Events::POST_SERIALIZE,
                'format' => 'json',
                'method' => 'onPostSerialize'
            ],
        ];
    }

    /**
     * @param ObjectEvent $event
     * @throws \Exception
     */
    public function onPostSerialize(ObjectEvent $event)
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
            }
        } catch (\BadMethodCallException $e) {
            // Ignore these exceptions, as this means this is not an entity or it is not configured
        }

        if ($object instanceof ExternalRelationshipEntityInterface) {
            foreach ($object->getExternalRelationships() as $field => $entity) {
                try {
                    if ($entity && $externalIdField = $this->eventLogService->externalIdFieldForEntity(get_class($entity))) {
                        $method = sprintf('get%s', ucfirst($externalIdField));
                        if (method_exists($entity, $method)) {
                            $property = new StaticPropertyMetadata(
                                get_class($object),
                                $field,
                                null
                            );
                            $visitor->visitProperty($property, $entity->{$method}());
                        }
                    }
                } catch (\BadMethodCallException $e) {
                    // Ignore these exceptions, as this means this is not an entity or it is not configured
                }
            }
        }
    }
}
