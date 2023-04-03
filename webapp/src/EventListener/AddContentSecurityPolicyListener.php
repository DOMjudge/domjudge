<?php declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Profiler\Profiler;

#[AsEventListener]
class AddContentSecurityPolicyListener
{
    public function __construct(protected readonly ?Profiler $profiler) {}

    public function __invoke(ResponseEvent $event): void
    {
        // Set the correct CSP based on whether the profiler is enabled, since
        // the profiler requires 'unsafe-eval' for script-src 'self'.
        $response = $event->getResponse();
        $cspExtra = $this->profiler ? "'unsafe-eval'" : "";
        $csp = "default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline' $cspExtra; img-src 'self' data:";
        $response->headers->set('Content-Security-Policy', $csp);
    }
}
