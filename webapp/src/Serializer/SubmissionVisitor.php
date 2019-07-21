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
use Symfony\Component\Routing\RouterInterface;

/**
 * Class SubmissionVisitor
 * @package App\Serializer
 */
class SubmissionVisitor implements EventSubscriberInterface
{
    /**
     * @var DOMJudgeService
     */
    protected $dj;

    /**
     * @var EventLogService
     */
    protected $eventLogService;

    /**
     * @var RouterInterface
     */
    protected $router;

    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * SubmissionVisitor constructor.
     * @param DOMJudgeService        $dj
     * @param EventLogService        $eventLogService
     * @param RouterInterface        $router
     * @param EntityManagerInterface $em
     */
    public function __construct(
        DOMJudgeService $dj,
        EventLogService $eventLogService,
        RouterInterface $router,
        EntityManagerInterface $em
    ) {
        $this->dj              = $dj;
        $this->eventLogService = $eventLogService;
        $this->router          = $router;
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
            $submission         = $event->getObject();
            $filesRoute         = $this->router->generate(
                'submission_files',
                [
                    'cid' => $submission->getContest()->getApiId($this->eventLogService),
                    'id'  => $submission->getApiId($this->eventLogService)
                ]
            );
            $apiRootRoute       = $this->router->generate('api_root');
            $relativeFilesRoute = substr(
                $filesRoute,
                strlen($apiRootRoute) + 1 // +1 because api_root does not contain final /
            );
            $property = new StaticPropertyMetadata(
                Submission::class,
                'files',
                null
            );
            $visitor->visitProperty($property, [['href' => $relativeFilesRoute, 'mime' => 'application/zip']]);
        }
    }
}
