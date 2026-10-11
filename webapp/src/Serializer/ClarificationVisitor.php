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
        if ($this->config->get('ccs_api_version') !== CcsApiVersion::Format_2026_01) {
            return;
        }

        /** @var Clarification $clarification */
        $clarification = $event->getObject();
        /** @var JsonSerializationVisitor $visitor */
        $visitor = $event->getVisitor();

        // Both are null iff it is a request from a team or a message to all teams. We only support a single
        // recipient team and no groups.
        $recipientId = $clarification->getRecipient()?->getExternalid();
        $visitor->visitProperty(
            new StaticPropertyMetadata(Clarification::class, 'to_team_ids', null),
            $recipientId === null ? null : [$recipientId]
        );
        $visitor->visitProperty(
            new StaticPropertyMetadata(Clarification::class, 'to_group_ids', null),
            $recipientId === null ? null : []
        );
    }
}
