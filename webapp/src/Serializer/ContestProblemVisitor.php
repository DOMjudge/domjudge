<?php declare(strict_types=1);

namespace App\Serializer;

use App\DataTransferObject\FileWithName;
use App\Entity\ContestProblem;
use App\Service\DOMJudgeService;
use JMS\Serializer\EventDispatcher\Events;
use JMS\Serializer\EventDispatcher\EventSubscriberInterface;
use JMS\Serializer\EventDispatcher\ObjectEvent;
use JMS\Serializer\JsonSerializationVisitor;

readonly class ContestProblemVisitor implements EventSubscriberInterface
{
    public function __construct(protected DOMJudgeService $dj) {}

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
        if ($contestProblem->getProblem()->getProblemstatementType() === 'pdf') {
            $route = $this->dj->apiRelativeUrl(
                'v4_app_api_problem_statement',
                [
                    'cid' => $contestProblem->getContest()->getExternalid(),
                    'id'  => $contestProblem->getExternalId(),
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

        // Problem attachments
        $attachments = [];
        foreach ($contestProblem->getProblem()->getAttachments() as $attachment) {
            $route = $this->dj->apiRelativeUrl(
                'v4_app_api_problem_attachment',
                [
                    'cid'      => $contestProblem->getContest()->getExternalid(),
                    'id'       => $contestProblem->getExternalId(),
                    'filename' => $attachment->getName(),
                ]
            );
            $attachments[] = new FileWithName(
                $route,
                $attachment->getMimeType(),
                $attachment->getName()
            );
        }
        $contestProblem->getProblem()->setAttachmentsForApi($attachments);
    }
}
