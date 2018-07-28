<?php

namespace DOMJudgeBundle\EventListener;

use Symfony\Component\HttpKernel\Event\FilterResponseEvent;

class ApiHeadersListener
{
    public function onKernelResponse(FilterResponseEvent $event)
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
