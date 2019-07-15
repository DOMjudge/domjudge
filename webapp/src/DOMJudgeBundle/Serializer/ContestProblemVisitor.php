<?php declare(strict_types=1);

namespace DOMJudgeBundle\Serializer;

use DOMJudgeBundle\Entity\Contest;
use DOMJudgeBundle\Entity\ContestProblem;
use DOMJudgeBundle\Utils\Utils;
use JMS\Serializer\EventDispatcher\EventSubscriberInterface;
use JMS\Serializer\EventDispatcher\ObjectEvent;
use JMS\Serializer\JsonSerializationVisitor;
use JMS\Serializer\Metadata\StaticPropertyMetadata;

class ContestProblemVisitor implements EventSubscriberInterface
{
    /**
     * @inheritdoc
     */
    public static function getSubscribedEvents()
    {
        return [
            [
                'event' => 'serializer.post_serialize',
                'class' => ContestProblem::class,
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
        /** @var ContestProblem $contestProblem */
        $contestProblem = $event->getObject();
        if ($contestProblem->getColor() && ($hex = Utils::convertToHex($contestProblem->getColor()))) {
            $property = new StaticPropertyMetadata(
                ContestProblem::class,
                'rgb',
                null
            );
            $visitor->visitProperty($property, $hex);
        }
        if ($contestProblem->getColor() && ($color = Utils::convertToColor($contestProblem->getColor()))) {
            $property = new StaticPropertyMetadata(
                ContestProblem::class,
                'color',
                null
            );
            $visitor->visitProperty($property, $color);
        }
    }
}
