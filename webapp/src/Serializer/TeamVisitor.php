<?php declare(strict_types=1);

namespace App\Serializer;

use App\Entity\Team;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Utils\Utils;
use JMS\Serializer\EventDispatcher\Events;
use JMS\Serializer\EventDispatcher\EventSubscriberInterface;
use JMS\Serializer\EventDispatcher\ObjectEvent;
use JMS\Serializer\JsonSerializationVisitor;
use JMS\Serializer\Metadata\StaticPropertyMetadata;
use Symfony\Component\HttpFoundation\RequestStack;

class TeamVisitor implements EventSubscriberInterface
{
    protected DOMJudgeService $dj;
    protected EventLogService $eventLogService;
    protected RequestStack $requestStack;

    public function __construct(
        DOMJudgeService $dj,
        EventLogService $eventLogService,
        RequestStack $requestStack
    ) {
        $this->dj = $dj;
        $this->eventLogService = $eventLogService;
        $this->requestStack = $requestStack;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            [
                'event'  => Events::POST_SERIALIZE,
                'class'  => Team::class,
                'format' => 'json',
                'method' => 'onPostSerialize'
            ],
        ];
    }

    public function onPostSerialize(ObjectEvent $event): void
    {
        /** @var JsonSerializationVisitor $visitor */
        $visitor = $event->getVisitor();
        /** @var Team $team */
        $team = $event->getObject();

        // Check if the asset actually exists
        if (!($teamPhoto = $this->dj->assetPath((string)$team->getTeamid(), 'team', true))) {
            return;
        }

        $parts     = explode('.', $teamPhoto);
        $extension = $parts[count($parts) - 1];

        $imageSize = Utils::getImageSize($teamPhoto);

        $id = $team->getApiId($this->eventLogService);

        $route = $this->dj->apiRelativeUrl(
            'v4_team_photo',
            [
                'cid' => $this->requestStack->getCurrentRequest()->attributes->get('cid'),
                'id'  => $id,
            ]
        );
        $property = new StaticPropertyMetadata(
            Team::class,
            'photo',
            null
        );
        $visitor->visitProperty($property, [
            [
                'href'     => $route,
                'mime'     => mime_content_type($teamPhoto),
                'width'    => $imageSize[0],
                'height'   => $imageSize[1],
                'filename' => 'photo.' . $extension
            ]
        ]);
    }
}
