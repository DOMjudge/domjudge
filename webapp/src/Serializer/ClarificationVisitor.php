<?php declare(strict_types=1);

namespace App\Serializer;

use App\Entity\Clarification;
use App\Service\ConfigurationService;
use App\Utils\CcsApiVersion;
use JMS\Serializer\EventDispatcher\Events;
use JMS\Serializer\EventDispatcher\EventSubscriberInterface;
use JMS\Serializer\EventDispatcher\ObjectEvent;
use JMS\Serializer\JsonSerializationVisitor;
use JMS\Serializer\Metadata\StaticPropertyMetadata;

readonly class ClarificationVisitor implements EventSubscriberInterface
{
    public function __construct(
        protected ConfigurationService $config,
    ) {}

    /**
     * @return array<array{event: string, class: string, format: string, method: string}>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            [
                'event' => Events::POST_SERIALIZE,
                'class' => Clarification::class,
                'format' => 'json',
                'method' => 'onPostSerialize',
            ],
        ];
    }

    public function onPostSerialize(ObjectEvent $event): void
    {
        /** @var Clarification $clarification */
        $clarification = $event->getObject();
        /** @var JsonSerializationVisitor $visitor */
        $visitor = $event->getVisitor();
        /** @var CcsApiVersion $ccsApiVersion */
        $ccsApiVersion = $this->config->get('ccs_api_version');

        $recipientId = $clarification->getRecipient()?->getExternalid();

        if ($ccsApiVersion === CcsApiVersion::Format_2026_01) {
            // For 2026-01 we expose to_team_ids as an array. We currently model only a single
            // recipient, so the array always has 0 or 1 entries. When there is no recipient
            // (broadcast) we omit the property entirely; we never expose to_group_ids since we
            // do not model group recipients.
            if ($recipientId !== null) {
                $visitor->visitProperty(
                    new StaticPropertyMetadata(Clarification::class, 'to_team_ids', null),
                    [$recipientId]
                );
            }
        } else {
            // Older API versions keep the single to_team_id field (string or null).
            $visitor->visitProperty(
                new StaticPropertyMetadata(Clarification::class, 'to_team_id', null),
                $recipientId
            );
        }
    }
}
