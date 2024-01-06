<?php declare(strict_types=1);

namespace App\Serializer;

use App\Entity\Submission;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\EventDispatcher\Events;
use JMS\Serializer\EventDispatcher\EventSubscriberInterface;
use JMS\Serializer\EventDispatcher\ObjectEvent;
use JMS\Serializer\JsonSerializationVisitor;
use JMS\Serializer\Metadata\StaticPropertyMetadata;

class SubmissionVisitor implements EventSubscriberInterface
{
    public function __construct(
        protected readonly DOMJudgeService $dj,
        protected readonly EventLogService $eventLogService,
        protected readonly EntityManagerInterface $em
    ) {}

    /**
     * @return array<array{event: string, class: string, format: string, method: string}>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            [
                'event' => Events::POST_SERIALIZE,
                'class' => Submission::class,
                'format' => 'json',
                'method' => 'onPostSerialize'
            ],
        ];
    }

    public function onPostSerialize(ObjectEvent $event): void
    {
        if ($this->dj->checkrole('api_source_reader')) {
            /** @var JsonSerializationVisitor $visitor */
            $visitor = $event->getVisitor();
            /** @var Submission $submission */
            $submission = $event->getObject();
            $route = $this->dj->apiRelativeUrl(
                'v4_submission_files',
                [
                    'cid' => $submission->getContest()->getApiId($this->eventLogService),
                    'id'  => $submission->getExternalid() ?? $submission->getSubmitid(),
                ]
            );
            $property = new StaticPropertyMetadata(
                Submission::class,
                'files',
                null
            );
            $visitor->visitProperty($property, [
                [
                    'href'     => $route,
                    'mime'     => 'application/zip',
                    'filename' => 'submission.zip',
                ]
            ]);
        }
    }
}
