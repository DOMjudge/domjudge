<?php declare(strict_types=1);

namespace App\Serializer;

use App\Entity\Contest;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use Exception;
use JMS\Serializer\EventDispatcher\Events;
use JMS\Serializer\EventDispatcher\EventSubscriberInterface;
use JMS\Serializer\EventDispatcher\ObjectEvent;
use JMS\Serializer\JsonSerializationVisitor;
use JMS\Serializer\Metadata\StaticPropertyMetadata;

/**
 * Class ContestVisitor
 * @package App\Serializer
 */
class ContestVisitor implements EventSubscriberInterface
{
    /**
     * @var ConfigurationService
     */
    protected $config;

    /**
     * @var DOMJudgeService
     */
    protected $dj;

    /**
     * @var EventLogService
     */
    protected $eventLogService;

    /**
     * @var bool
     */
    protected $bannerExists;

    /**
     * ContestVisitor constructor.
     *
     * @param ConfigurationService $config
     * @param DOMJudgeService      $dj
     * @param EventLogService      $eventLogService
     * @param bool                 $bannerExists
     */
    public function __construct(
        ConfigurationService $config,
        DOMJudgeService $dj,
        EventLogService $eventLogService,
        bool $bannerExists
    ) {
        $this->config = $config;
        $this->dj = $dj;
        $this->eventLogService = $eventLogService;
        $this->bannerExists = $bannerExists;
    }

    /**
     * @inheritdoc
     */
    public static function getSubscribedEvents()
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

    /**
     * @param ObjectEvent $event
     *
     * @throws Exception
     */
    public function onPostSerialize(ObjectEvent $event)
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

        if ($this->bannerExists) {
            $idField = sprintf('get%s', ucfirst($this->eventLogService->externalIdFieldForEntity(Contest::class) ?? 'cid'));
            $id = call_user_func([$contest, $idField]);

            $banner = sprintf('%s/public/images/banner.png', $this->dj->getDomjudgeWebappDir());

            $imageSize = getimagesize($banner);

            $route = $this->dj->apiRelativeUrl('v4_contest_banner', ['id' => $id]);
            $property = new StaticPropertyMetadata(
                Contest::class,
                'banner',
                null
            );
            $visitor->visitProperty($property, [['href' => $route, 'mime' => 'image/png', 'width' => $imageSize[0], 'height' => $imageSize[1]]]);
        }
    }
}
