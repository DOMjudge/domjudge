<?php declare(strict_types=1);

namespace App\Serializer;

use App\DataTransferObject\FileWithName;
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
    public function __construct(
        protected readonly DOMJudgeService $dj,
        protected readonly EventLogService $eventLogService
    ) {}

    /**
     * @return array<array{event: string, class: string, format: string, method: string}>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            [
                'event' => Events::PRE_SERIALIZE,
                'class' => ContestProblem::class,
                'format' => 'json',
                'method' => 'onPreSerialize'
            ],
        ];
    }

    public function onPreSerialize(ObjectEvent $event): void
    {
        /** @var JsonSerializationVisitor $visitor */
        $visitor = $event->getVisitor();
        /** @var ContestProblem $contestProblem */
        $contestProblem = $event->getObject();

        // Problem statement
        if ($contestProblem->getProblem()->getProblemtextType() === 'pdf') {
            $route = $this->dj->apiRelativeUrl(
                'v4_app_api_problem_statement',
                [
                    'cid' => $contestProblem->getContest()->getApiId($this->eventLogService),
                    'id'  => $contestProblem->getApiId($this->eventLogService),
                ]
            );
            $contestProblem->getProblem()->setStatementForApi(new FileWithName(
                $route,
                'application/pdf',
                $contestProblem->getShortname() . '.pdf'
            ));
        } else {
            $contestProblem->getProblem()->setStatementForApi();
        }
    }
}
