<?php declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Profiler\Profiler;

class AddContentSecurityPolicyListener implements EventSubscriberInterface
{
    /**
     * @var Profiler|null
     */
    protected $profiler;

    public function __construct(?Profiler $profiler)
    {
        $this->profiler = $profiler;
    }

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents()
    {
        return [ResponseEvent::class => 'onKernelResponse'];
    }

    public function onKernelResponse(ResponseEvent $event)
    {
        // Set the correct CSP based on whether the profiler is enabled, since
        // the profiler requires 'unsafe-eval' for script-src 'self'
        $response = $event->getResponse();
        $cspExtra = $this->profiler ? "'unsafe-eval'" : "";
        $csp = "default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline' $cspExtra; img-src 'self' data:";
        $response->headers->set('Content-Security-Policy', $csp);
    }
}
