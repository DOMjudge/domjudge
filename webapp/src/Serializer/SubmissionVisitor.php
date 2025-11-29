<?php declare(strict_types=1);

namespace App\Serializer;

use App\DataTransferObject\FileWithName;
use App\Entity\Submission;
use App\Service\DOMJudgeService;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\EventDispatcher\Events;
use JMS\Serializer\EventDispatcher\EventSubscriberInterface;
use JMS\Serializer\EventDispatcher\ObjectEvent;
use JMS\Serializer\Metadata\StaticPropertyMetadata;

class SubmissionVisitor implements EventSubscriberInterface
{
    public function __construct(
        protected readonly DOMJudgeService $dj,
        protected readonly EntityManagerInterface $em,
    ) {}

    /**
     * @return array<array{event: string, class: string, format: string, method: string}>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            [
                'event' => Events::PRE_SERIALIZE,
                'class' => Submission::class,
                'format' => 'json',
                'method' => 'onPreSerialize',
            ],
        ];
    }

    public function onPreSerialize(ObjectEvent $event): void
    {
        /** @var Submission $submission */
        $submission = $event->getObject();
        if ($this->dj->checkrole('api_source_reader')) {
            $route = $this->dj->apiRelativeUrl(
                'v4_submission_files',
                [
                    'cid' => $submission->getContest()->getExternalid(),
                    'id'  => $submission->getExternalid(),
                ]
            );
            $submission->setFileForApi(new FileWithName(
                href: $route,
                mime: 'application/zip',
                filename: 'submission.zip',
            ));
        } else {
            $submission->setFileForApi();
        }
    }
}
