<?php declare(strict_types=1);

namespace DOMJudgeBundle\Serializer;

use DOMJudgeBundle\Entity\Contest;
use DOMJudgeBundle\Service\DOMJudgeService;
use JMS\Serializer\EventDispatcher\EventSubscriberInterface;
use JMS\Serializer\EventDispatcher\ObjectEvent;
use JMS\Serializer\JsonSerializationVisitor;

/**
 * Class ContestVisitor
 * @package DOMJudgeBundle\Serializer
 */
class ContestVisitor implements EventSubscriberInterface
{
    /**
     * @var DOMJudgeService
     */
    private $dj;

    /**
     * ContestVisitor constructor.
     * @param DOMJudgeService $dj
     */
    public function __construct(DOMJudgeService $dj)
    {
        $this->dj = $dj;
    }

    /**
     * @inheritdoc
     */
    public static function getSubscribedEvents()
    {
        return [
            [
                'event' => 'serializer.post_serialize',
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
        $visitor = $event->getVisitor();
        $visitor->setData('penalty_time', (int)$this->dj->dbconfig_get('penalty_time',20));
    }
}
