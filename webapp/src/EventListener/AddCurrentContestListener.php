<?php declare(strict_types=1);

namespace App\EventListener;

use App\Service\DOMJudgeService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

class AddCurrentContestListener implements EventSubscriberInterface
{
    protected DOMJudgeService $dj;

    public function __construct(DOMJudgeService $dj)
    {
        $this->dj = $dj;
    }

    public static function getSubscribedEvents(): array
    {
        return [ResponseEvent::class => 'onKernelResponse'];
    }

    public function onKernelResponse(ResponseEvent $event)
    {
        $event->getResponse()->headers->set('X-Current-Contest', $this->dj->getCurrentContestCookie());
    }
}
