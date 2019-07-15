<?php declare(strict_types=1);

namespace DOMJudgeBundle\EventListener;

use Symfony\Component\HttpKernel\Event\ResponseEvent;

class ApiHeadersListener
{
    public function onKernelResponse(ResponseEvent $event)
    {
        $request = $event->getRequest();
        // Check if this is an API request
        if (strpos($request->getPathInfo(), '/api') === 0) {
            // It is, so add CORS headers
            $response = $event->getResponse();
            $response->headers->set('Access-Control-Allow-Origin', '*');
        }
    }
}
