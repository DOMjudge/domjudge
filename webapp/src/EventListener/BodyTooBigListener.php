<?php declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Class BodyTooBigListener
 * @package App\EventListener
 */
class BodyTooBigListener implements EventSubscriberInterface
{
    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents()
    {
        return [ControllerEvent::class => 'onKernelController',];
    }

    public function onKernelController(ControllerEvent $event)
    {
        // When we have a POST, PUT or PATCH but no request or file attributes
        // but we do have a non-zero content-length header, the caller
        // (probably) exceeded PHP's post_max_size, so make sure to report
        // this. We use onKernelController here to make sure any API response
        // parsers are already set up.

        $request = $event->getRequest();
        if ($request->isMethod('POST') || $request->isMethod('PATCH') || $request->isMethod('PUT')) {
            if ($request->request->count() === 0 && $request->files->count() === 0 &&
                $request->headers->get('content-length', 0) > 0) {
                $msg = sprintf(
                    "Body data exceeded php.ini's 'post_max_size' directive (currently set to %s)",
                    ini_get('post_max_size')
                );
                throw new BadRequestHttpException($msg);
            }
        }
    }
}
