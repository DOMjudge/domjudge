<?php declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerEvent;

#[AsEventListener]
class RespondInJsonForApiListener
{
    public function __invoke(ControllerEvent $event): void
    {
        // For API requests, if no accept header has been set, or it is text/html or */*,
        // set it to application/json.

        $request = $event->getRequest();
        if ($request->attributes->get('_fos_rest_zone')) {
            $acceptHeader = $request->headers->get('accept');

            if (!$acceptHeader
                || str_starts_with($acceptHeader, 'text/html')
                || str_starts_with($acceptHeader, '*/*')) {
                $request->headers->set('accept', 'application/json');
            }
        }
    }
}
