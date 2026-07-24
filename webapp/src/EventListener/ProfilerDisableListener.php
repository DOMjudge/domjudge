<?php declare(strict_types=1);

namespace App\EventListener;

use App\Service\AuthorizedUserService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Profiler\Profiler;

#[AsEventListener]
readonly class ProfilerDisableListener
{
    public function __construct(
        protected AuthorizedUserService $authService,
        protected ?Profiler             $profiler
    ) {}

    public function __invoke(RequestEvent $event): void
    {
        if ($this->profiler) {
            // Disable the profiler for users with the judgehost permission but not the admin one.
            if ($this->authService->checkRole('judgehost') && !$this->authService->checkRole('admin')) {
                $this->profiler->disable();
            }
            // Disable the profiler if using the web API to import.
            if ($event->getRequest()->attributes->get('_route') == 'current_app_api_problem_addproblem') {
                $this->profiler->disable();
            }
        }
    }
}
