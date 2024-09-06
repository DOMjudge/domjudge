<?php declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

// The AbstractSessionListener (which sets the cookie) has a priority of -1000, so we need to
// set a priority of -1001 to run before it.
#[AsEventListener(priority: -1001)]
class NoSessionCookieForApiListener
{
    public function __invoke(ResponseEvent $event): void
    {
        // We do not want to set the session cookie for API requests. Since the firewall is
        // stateful (because we want form logins to allow to access the API), we need to remove
        // the cookie
        $request = $event->getRequest();
        $response = $event->getResponse();
        if ($request->attributes->get('_firewall_context') === 'security.firewall.map.context.api') {
            $response->headers->removeCookie($request->getSession()->getName());
        }
    }
}
