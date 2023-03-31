<?php declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

#[AsEventListener]
class ApiHeadersListener
{
    public function __invoke(ResponseEvent $event): void
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
