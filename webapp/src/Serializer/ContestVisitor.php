<?php declare(strict_types=1);

namespace App\Serializer;

use App\Entity\Contest;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
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
     * ContestVisitor constructor.
     *
     * @param ConfigurationService $config
     */
    public function __construct(ConfigurationService $config)
    {
        $this->config = $config;
    }

    /**
     * @inheritdoc
     */
    public static function getSubscribedEvents()
    {
        return [
            [
                'event' => Events::POST_SERIALIZE,
                'class' => Contest::class,
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
        $visitor  = $event->getVisitor();
        $property = new StaticPropertyMetadata(
            Contest::class,
            'penalty_time',
            null
        );
        $visitor->visitProperty($property, (int)$this->config->get('penalty_time'));
    }
}
