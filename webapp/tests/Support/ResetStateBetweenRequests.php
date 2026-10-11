<?php declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The test client does not reboot the kernel between requests, as that is
 * slow. Reset the stateful services (like the entity manager) instead, so
 * every request after the first one starts without leftovers of the previous.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 4096)]
class ResetStateBetweenRequests
{
    private bool $seenRequest = false;

    public function __construct(
        #[Autowire(service: 'services_resetter')]
        private readonly ResetInterface $servicesResetter
    ) {}

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        if ($this->seenRequest) {
            $this->servicesResetter->reset();
        }
        $this->seenRequest = true;
    }
}
