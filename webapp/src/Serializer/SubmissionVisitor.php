<?php declare(strict_types=1);

namespace App\Serializer;

use App\Entity\Submission;
use App\Service\DOMjudgeService;
use App\Service\EventLogService;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\EventDispatcher\Events;
use JMS\Serializer\EventDispatcher\EventSubscriberInterface;
use JMS\Serializer\EventDispatcher\ObjectEvent;
use JMS\Serializer\JsonSerializationVisitor;
use JMS\Serializer\Metadata\StaticPropertyMetadata;

/**
 * Class SubmissionVisitor
 * @package App\Serializer
 */
class SubmissionVisitor implements EventSubscriberInterface
{
    /**
     * @var DOMjudgeService
     */
    protected $dj;

    /**
     * @var EventLogService
     */
    protected $eventLogService;

    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * SubmissionVisitor constructor.
     * @param DOMjudgeService        $dj
     * @param EventLogService        $eventLogService
     * @param EntityManagerInterface $em
     */
    public function __construct(
        DOMjudgeService $dj,
        EventLogService $eventLogService,
        EntityManagerInterface $em
    ) {
        $this->dj              = $dj;
        $this->eventLogService = $eventLogService;
        $this->em              = $em;
    }

    /**
     * @inheritdoc
     */
    public static function getSubscribedEvents()
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

    /**
     * @param ObjectEvent $event
     * @throws \Exception
     */
    public function onPostSerialize(ObjectEvent $event)
    {
        if ($this->dj->checkrole('jury')) {
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
            $visitor->visitProperty($property, [['href' => $route, 'mime' => 'application/zip']]);
        }
    }
}
