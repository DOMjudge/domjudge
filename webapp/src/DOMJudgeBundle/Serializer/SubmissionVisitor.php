<?php declare(strict_types=1);

namespace DOMJudgeBundle\Serializer;

use DOMJudgeBundle\Entity\Submission;
use DOMJudgeBundle\Service\DOMJudgeService;
use JMS\Serializer\EventDispatcher\EventSubscriberInterface;
use JMS\Serializer\EventDispatcher\ObjectEvent;
use JMS\Serializer\JsonSerializationVisitor;
use Symfony\Component\Routing\RouterInterface;

/**
 * Class SubmissionVisitor
 * @package DOMJudgeBundle\Serializer
 */
class SubmissionVisitor implements EventSubscriberInterface
{
    /**
     * @var DOMJudgeService
     */
    protected $DOMJudgeService;

    /**
     * @var RouterInterface
     */
    protected $router;

    /**
     * SubmissionVisitor constructor.
     * @param DOMJudgeService $DOMJudgeService
     * @param RouterInterface $router
     */
    public function __construct(DOMJudgeService $DOMJudgeService, RouterInterface $router)
    {
        $this->DOMJudgeService = $DOMJudgeService;
        $this->router          = $router;
    }

    /**
     * @inheritdoc
     */
    public static function getSubscribedEvents()
    {
        return [
            [
                'event' => 'serializer.post_serialize',
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
        if ($this->DOMJudgeService->checkrole('jury')) {
            /** @var JsonSerializationVisitor $visitor */
            $visitor = $event->getVisitor();
            /** @var Submission $submission */
            $submission         = $event->getObject();
            $filesRoute         = $this->router->generate('submission_files',
                                                          ['cid' => $submission->getCid(), 'id' => $submission->getSubmitid()]);
            $apiRootRoute       = $this->router->generate('api_root');
            $relativeFilesRoute = substr($filesRoute, strlen($apiRootRoute) + 1); // +1 because api_root does not contain final /
            $visitor->setData('files', [['href' => $relativeFilesRoute]]);
        }
    }
}
