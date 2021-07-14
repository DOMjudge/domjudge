<?php declare(strict_types=1);

namespace App\Serializer;

use App\Entity\Team;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use Exception;
use JMS\Serializer\EventDispatcher\Events;
use JMS\Serializer\EventDispatcher\EventSubscriberInterface;
use JMS\Serializer\EventDispatcher\ObjectEvent;
use JMS\Serializer\JsonSerializationVisitor;
use JMS\Serializer\Metadata\StaticPropertyMetadata;
use Symfony\Component\HttpFoundation\RequestStack;

class TeamVisitor implements EventSubscriberInterface
{
    /**
     * @var DOMJudgeService
     */
    protected $dj;

    /**
     * @var EventLogService
     */
    protected $eventLogService;

    /**
     * @var RequestStack
     */
    protected $requestStack;

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

    /**
     * @param ObjectEvent $event
     *
     * @throws Exception
     */
    public function onPostSerialize(ObjectEvent $event)
    {
        /** @var JsonSerializationVisitor $visitor */
        $visitor = $event->getVisitor();
        /** @var Team $team */
        $team = $event->getObject();

        // Check if the asset actually exists
        if (!($teamPhoto = $this->dj->assetPath((string)$team->getTeamid(), 'team', true))) {
            return;
        }

        $imageSize = getimagesize($teamPhoto);

        $idField = sprintf('get%s', ucfirst($this->eventLogService->externalIdFieldForEntity(Team::class) ?? 'teamid'));

        $route = $this->dj->apiRelativeUrl(
            'v4_team_photo',
            [
                'cid' => $this->requestStack->getCurrentRequest()->attributes->get('cid'),
                'id'  => call_user_func([$team, $idField]),
            ]
        );
        $property = new StaticPropertyMetadata(
            Team::class,
            'photo',
            null
        );
        $visitor->visitProperty($property, [['href' => $route, 'mime' => 'image/jpeg', 'width' => $imageSize[0], 'height' => $imageSize[1]]]);
    }
}
