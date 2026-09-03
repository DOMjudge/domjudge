<?php declare(strict_types=1);

namespace App\EventListener;

use App\Attribute\ReleaseSessionLock;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerEvent;

#[AsEventListener]
class ReleaseSessionLockListener
{
    public function __invoke(ControllerEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !$request->isMethodSafe() || !$event->getAttributes(ReleaseSessionLock::class)) {
            return;
        }

        if ($request->hasSession() && $request->getSession()->isStarted()) {
            $request->getSession()->save();
        }
    }
}
