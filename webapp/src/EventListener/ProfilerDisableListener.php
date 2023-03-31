<?php declare(strict_types=1);

namespace App\EventListener;

use App\Service\DOMJudgeService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\Profiler\Profiler;

#[AsEventListener]
class ProfilerDisableListener
{
    public function __construct(
        protected readonly KernelInterface $kernel,
        protected readonly DOMJudgeService $dj,
        protected readonly ?Profiler $profiler
    ) {}

    public function __invoke(RequestEvent $event): void
    {
        // Disable the profiler for users with the judgehost permission but not the admin one.
        if ($this->profiler && $this->dj->checkrole('judgehost') && !$this->dj->checkrole('admin')) {
            $this->profiler->disable();
        }
    }
}
