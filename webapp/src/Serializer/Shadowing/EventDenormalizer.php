<?php declare(strict_types=1);

namespace App\Serializer\Shadowing;

use App\DataTransferObject\Shadowing\Event;
use App\DataTransferObject\Shadowing\EventData;
use App\DataTransferObject\Shadowing\EventType;
use App\DataTransferObject\Shadowing\Operation;
use App\Utils\CcsApiVersion;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerAwareTrait;

/**
 * This class is responsible for changing an event array, either in the legacy 2020-03 format
 * or the new 2022-07 format, into an Event object.
 */
class EventDenormalizer implements DenormalizerInterface, SerializerAwareInterface
{
    use SerializerAwareTrait;

    /**
     * @param array{id?: string,
     *     type: string,
     *     op?: string,
     *     token?: string,
     *     data: array{id: string}|array<array{id: string}>|null
     * }                                  $data
     * @param array{api_version?: string} $context
     *
     * @return Event<EventData>
     *
     * @throws ExceptionInterface
     */
    public function denormalize(
        mixed $data,
        string $type,
        ?string $format = null,
        array $context = []
    ): Event {
        if (!$this->supportsDenormalization($data, $type, $format, $context)) {
            throw new InvalidArgumentException('Unsupported data.');
        }

        if (!$this->serializer instanceof DenormalizerInterface) {
            throw new LogicException('Cannot denormalize attribute "data" because the injected serializer is not a denormalizer.');
        }

        $eventType = EventType::fromString($data['type']);
        if ($this->getCcsApiVersion($data, $context) === CcsApiVersion::Format_2020_03) {
            $operation = Operation::from($data['op']);
            if ($operation === Operation::DELETE) {
                $eventData = [];
            } elseif ($eventType->getEventClass() === null) {
                $eventData = [];
            } else {
                $eventData = [$this->serializer->denormalize($data['data'], EventData::class, $format, $context + ['event_type' => $eventType])];
            }
            return new Event(
                $data['id'] ?? null,
                $eventType,
                $operation,
                $data['data']['id'] ?? null,
                $eventData,
            );
        } else {
            $operation = isset($data['data']) ? Operation::CREATE : Operation::DELETE;
            if (isset($data['data']) && !isset($data['data'][0])) {
                $data['data'] = [$data['data']];
            }
            if ($operation === Operation::CREATE && count($data['data']) === 1) {
                $id = $data['data'][0]['id'] ?? null;
            } elseif ($operation === Operation::DELETE) {
                $id = $data['id'];
            } else {
                $id = null;
            }
            if ($eventType->getEventClass() === null) {
                $eventData = [];
            } else {
                $eventData = isset($data['data']) ? $this->serializer->denormalize($data['data'], EventData::class . '[]', $format, $context + ['event_type' => $eventType]) : [];
            }
            return new Event(
                $data['token'] ?? null,
                $eventType,
                $operation,
                $id,
                $eventData,
            );
        }
    }

    /**
     * @param array{api_version?: string} $context
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
        if ($type !== Event::class) {
            return false;
        }
        return true;
    }

    /**
     * @return array<string, bool|null>
     */
    public function getSupportedTypes(?string $format): array
    {
        return [Event::class => false, '*' => null];
    }


    /**
     * @param array{op?: string}          $event
     * @param array{api_version?: string} $context
     */
    protected function getCcsApiVersion(array $event, array $context): CcsApiVersion
    {
        return match ($context['api_version']) {
            '2020-03' => CcsApiVersion::Format_2020_03,
            '2021-11', '2022-07', '2023-06' => CcsApiVersion::Format_2023_06,
            '2025-draft' => CcsApiVersion::Format_2025_DRAFT,
            default => isset($event['op']) ? CcsApiVersion::Format_2020_03 : CcsApiVersion::Format_2025_DRAFT,
        };
    }
}
