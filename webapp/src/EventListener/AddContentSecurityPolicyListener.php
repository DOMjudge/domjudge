<?php declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Profiler\Profiler;

#[AsEventListener]
class AddContentSecurityPolicyListener
{
    public function __construct(
        protected readonly ?Profiler $profiler,
        protected readonly array $cspConfig
    ) {}

    public function __invoke(ResponseEvent $event): void
    {
        $response = $event->getResponse();

        $csp = implode('; ', [
            $this->getDefaultSrcCsp(),
            $this->getStyleSrcCsp(),
            $this->getScriptSrcCsp(),
            $this->getImageSrcCsp(),
        ]);

        $response->headers->set('Content-Security-Policy', $csp);
    }

    private function getDefaultSrcCsp(): string
    {
        return "default-src " . $this->cspConfig['defaultSrc'];
    }

    private function getStyleSrcCsp(): string
    {
        return "style-src " . $this->cspConfig['styleSrc'];
    }

    private function getScriptSrcCsp(): string
    {
        // Set the correct CSP based on whether the profiler is enabled, since
        // the profiler requires 'unsafe-eval' for script-src 'self'.
        $unsafeEvalCsp = $this->profiler ? " 'unsafe-eval'" : "";
        return "script-src " . $this->cspConfig['scriptSrc'] . $unsafeEvalCsp;
    }

    private function getImageSrcCsp(): string
    {
        return "img-src " . $this->cspConfig['imgSrc'];
    }
}
