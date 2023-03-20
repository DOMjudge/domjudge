<?php declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

class ApiHeadersListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [ResponseEvent::class => 'onKernelResponse'];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        // Check if this is an API request.
        if (str_starts_with($request->getPathInfo(), '/api')) {
            // It is, so add CORS headers.
            $response = $event->getResponse();
            $response->headers->set('Access-Control-Allow-Origin', '*');
        }
    }
}
