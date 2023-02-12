<?php declare(strict_types=1);

namespace App\Serializer;

use App\Entity\Contest;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Utils\Utils;
use JMS\Serializer\EventDispatcher\Events;
use JMS\Serializer\EventDispatcher\EventSubscriberInterface;
use JMS\Serializer\EventDispatcher\ObjectEvent;
use JMS\Serializer\JsonSerializationVisitor;
use JMS\Serializer\Metadata\StaticPropertyMetadata;

/**
 * Class ContestVisitor
 *
 * @package App\Serializer
 */
class ContestVisitor implements EventSubscriberInterface
{
    protected ConfigurationService $config;
    protected DOMJudgeService $dj;
    protected EventLogService $eventLogService;

    public function __construct(
        ConfigurationService $config,
        DOMJudgeService $dj,
        EventLogService $eventLogService
    ) {
        $this->config = $config;
        $this->dj = $dj;
        $this->eventLogService = $eventLogService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            [
                'event'  => Events::POST_SERIALIZE,
                'class'  => Contest::class,
                'format' => 'json',
                'method' => 'onPostSerialize'
            ],
        ];
    }

    public function onPostSerialize(ObjectEvent $event): void
    {
        /** @var JsonSerializationVisitor $visitor */
        $visitor = $event->getVisitor();
        /** @var Contest $contest */
        $contest = $event->getObject();

        $property = new StaticPropertyMetadata(
            Contest::class,
            'penalty_time',
            null
        );
        $visitor->visitProperty($property, (int)$this->config->get('penalty_time'));

        $id = $contest->getApiId($this->eventLogService);

        // Banner
        if ($banner = $this->dj->assetPath($id, 'contest', true)) {
            $imageSize = Utils::getImageSize($banner);
            $parts     = explode('.', $banner);
            $extension = $parts[count($parts) - 1];

            $route = $this->dj->apiRelativeUrl(
                'v4_contest_banner', ['cid' => $id]
            );
            $property = new StaticPropertyMetadata(
                Contest::class,
                'banner',
                null
            );
            $visitor->visitProperty($property, [
                [
                    'href'     => $route,
                    'mime'     => mime_content_type($banner),
                    'width'    => $imageSize[0],
                    'height'   => $imageSize[1],
                    'filename' => 'banner.' . $extension,
                ]
            ]);
        }
    }
}
