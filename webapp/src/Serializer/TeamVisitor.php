<?php declare(strict_types=1);

namespace App\Serializer;

use App\DataTransferObject\ImageFile;
use App\Entity\Team;
use App\Service\DOMJudgeService;
use App\Utils\Utils;
use JMS\Serializer\EventDispatcher\Events;
use JMS\Serializer\EventDispatcher\EventSubscriberInterface;
use JMS\Serializer\EventDispatcher\ObjectEvent;
use JMS\Serializer\JsonSerializationVisitor;
use JMS\Serializer\Metadata\StaticPropertyMetadata;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class TeamVisitor implements EventSubscriberInterface
{
    public function __construct(
        protected DOMJudgeService $dj,
        protected RequestStack    $requestStack
    ) {}

    /**
     * @return array<array{event: string, class: string, format: string, method: string}>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            [
                'event' => Events::POST_SERIALIZE,
                'class' => Team::class,
                'format' => 'json',
                'method' => 'onPostSerialize',
            ],
            [
                'event' => Events::PRE_SERIALIZE,
                'class' => Team::class,
                'format' => 'json',
                'method' => 'onPreSerialize',
            ],
        ];
    }

    public function onPostSerialize(ObjectEvent $event): void
    {
        /** @var JsonSerializationVisitor $visitor */
        $visitor = $event->getVisitor();
        /** @var Team $team */
        $team = $event->getObject();

        // Use the API ID for label if we have no label set
        if (($team->getLabel() ?? '') === '') {
            $property = new StaticPropertyMetadata(
                Team::class,
                'label',
                null
            );
            $visitor->visitProperty($property, $team->getExternalid());
        }
    }

    public function onPreSerialize(ObjectEvent $event): void
    {
        /** @var Team $team */
        $team = $event->getObject();

        $id = $team->getExternalid();

        // Check if the asset actually exists
        if (!($teamPhoto = $this->dj->assetPath($id, 'team', true))) {
            $team->setPhotoForApi();
            return;
        }

        $parts = explode('.', $teamPhoto);
        $extension = $parts[count($parts) - 1];

        $imageSize = Utils::getImageSize($teamPhoto);

        if ($cid = $this->requestStack->getCurrentRequest()->attributes->get('cid')) {
            $route = $this->dj->apiRelativeUrl(
                'v4_team_photo',
                [
                    'cid' => $cid,
                    'id' => $id,
                ]
            );
        } else {
            $route = $this->dj->apiRelativeUrl('v4_no_contest_team_photo', ['id' => $id]);
        }
        $team->setPhotoForApi(new ImageFile(
            href: $route,
            mime: mime_content_type($teamPhoto),
            filename: 'photo.' . $extension,
            width: $imageSize[0],
            height: $imageSize[1]
        ));
    }
}
