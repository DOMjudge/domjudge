<?php declare(strict_types=1);

namespace App\Twig\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * This listener allows one to set a custom response object in a controller.
 * It will then use that response, but use the content of the original controller response.
 */
class CustomResponseListener
{
    protected ?Response $response = null;

    public function setCustomResponse(Response $response): void
    {
        $this->response = $response;
    }

    #[AsEventListener]
    public function onKernelResponse(ResponseEvent $event): void
    {
        if ($this->response) {
            $this->response->setContent($event->getResponse()->getContent());
            $event->setResponse($this->response);

            // Make sure to clear the response if we have more requests after this one.
            $this->response = null;
        }
    }
}
