<?php declare(strict_types=1);

namespace App\Serializer;

use App\Entity\ContestProblem;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Utils\Utils;
use JMS\Serializer\EventDispatcher\Events;
use JMS\Serializer\EventDispatcher\EventSubscriberInterface;
use JMS\Serializer\EventDispatcher\ObjectEvent;
use JMS\Serializer\JsonSerializationVisitor;
use JMS\Serializer\Metadata\StaticPropertyMetadata;

class ContestProblemVisitor implements EventSubscriberInterface
{
    protected DOMJudgeService $dj;
    protected EventLogService $eventLogService;

    public function __construct(
        DOMJudgeService $dj,
        EventLogService $eventLogService
    ) {
        $this->dj              = $dj;
        $this->eventLogService = $eventLogService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            [
                'event' => Events::POST_SERIALIZE,
                'class' => ContestProblem::class,
                'format' => 'json',
                'method' => 'onPostSerialize'
            ],
        ];
    }

    public function onPostSerialize(ObjectEvent $event): void
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

        // Problem statement
        if ($contestProblem->getProblem()->getProblemtextType() === 'pdf') {
            $route = $this->dj->apiRelativeUrl(
                'v4_app_api_problem_statement',
                [
                    'cid' => $contestProblem->getContest()->getApiId($this->eventLogService),
                    'id'  => $contestProblem->getApiId($this->eventLogService),
                ]
            );
            $property = new StaticPropertyMetadata(
                ContestProblem::class,
                'statement',
                null
            );
            $visitor->visitProperty($property, [
                [
                    'href'     => $route,
                    'mime'     => 'application/pdf',
                    'filename' => $contestProblem->getShortname() . '.pdf',
                ]
            ]);
        }
    }
}
