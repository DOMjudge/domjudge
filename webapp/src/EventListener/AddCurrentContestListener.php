<?php declare(strict_types=1);

namespace App\EventListener;

use App\Service\DOMJudgeService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

#[AsEventListener]
class AddCurrentContestListener
{
    public function __construct(protected readonly DOMJudgeService $dj) {}

    public function __invoke(ResponseEvent $event)
    {
        $event->getResponse()->headers->set('X-Current-Contest', $this->dj->getCurrentContestCookie());
    }
}
