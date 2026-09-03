<?php declare(strict_types=1);

namespace App\Tests\Session;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Marks the phases of a request in the SessionEventLog. The render phase is set by
 * RenderPhaseProfilerExtension when the first template starts rendering.
 */
final class SessionPhaseListener
{
    #[AsEventListener(priority: 4096)]
    public function onRequest(RequestEvent $event): void
    {
        if ($event->isMainRequest()) {
            SessionEventLog::setPhase(SessionEventLog::PHASE_REQUEST);
        }
    }

    // Runs after every other controller (arguments) listener, right before the action.
    #[AsEventListener(priority: -4096)]
    public function onControllerArguments(ControllerArgumentsEvent $event): void
    {
        if ($event->isMainRequest()) {
            SessionEventLog::setPhase(SessionEventLog::PHASE_ACTION);
        }
    }

    // Runs before the security and session listeners store the token and save the session.
    #[AsEventListener(priority: 4096)]
    public function onResponse(ResponseEvent $event): void
    {
        if ($event->isMainRequest()) {
            SessionEventLog::setPhase(SessionEventLog::PHASE_RESPONSE);
        }
    }
}
