<?php declare(strict_types=1);

namespace App\Serializer\Shadowing;

use App\DataTransferObject\Shadowing\EventData;
use App\DataTransferObject\Shadowing\EventType;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerAwareTrait;

/**
 * This class converts the data of an event into the correct event data object
 */
class EventDataDenormalizer implements DenormalizerInterface, SerializerAwareInterface
{
    use SerializerAwareTrait;

    /**
     * @param array{api_version?: string, event_type?: EventType} $context
     *
     * @throws ExceptionInterface
     */
    public function denormalize(
        mixed $data,
        string $type,
        ?string $format = null,
        array $context = []
    ): mixed {
        if (!$this->supportsDenormalization($data, $type, $format, $context)) {
            throw new InvalidArgumentException('Unsupported data.');
        }

        if (!$this->serializer instanceof DenormalizerInterface) {
            throw new LogicException('Cannot denormalize attribute "data" because the injected serializer is not a denormalizer.');
        }

        $eventType = $context['event_type'];
        $eventClass = $eventType->getEventClass();
        if ($eventClass === null) {
            return null;
        }

        // Unset the event type, so we are not calling ourselves recursively
        unset($context['event_type']);
        return $this->serializer->denormalize($data, $eventClass, $format, $context);
    }

    /**
     * @param array{api_version?: string, event_type?: mixed} $context
     */
    public function supportsDenormalization(
        mixed $data,
        string $type,
        ?string $format = null,
        array $context = []
    ): bool {
        if (!is_array($data)) {
            return false;
        }
        if ($type !== EventData::class) {
            return false;
        }

        if (!isset($context['event_type'])) {
            return false;
        }

        if (!$context['event_type'] instanceof EventType) {
            return false;
        }

        return true;
    }

    /**
     * @return array<string, bool|null>
     */
    public function getSupportedTypes(?string $format): array
    {
        return [EventData::class => false, '*' => null];
    }
}
