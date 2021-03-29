<?php declare(strict_types=1);

namespace App\EventListener;

use App\Service\DOMJudgeService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\Profiler\Profiler;

/**
 * Class ProfilerDisableListener
 * @package App\EventListener
 */
class ProfilerDisableListener implements EventSubscriberInterface
{
    /**
     * @var KernelInterface
     */
    protected $kernel;

    /**
     * @var DOMJudgeService
     */
    protected $dj;

    /**
     * @var Profiler|null
     */
    protected $profiler;

    /**
     * ProfilerDisableListener constructor.
     * @param KernelInterface $kernel
     * @param DOMJudgeService $dj
     * @param Profiler|null        $profiler
     */
    public function __construct(KernelInterface $kernel, DOMJudgeService $dj, ?Profiler $profiler)
    {
        $this->dj       = $dj;
        $this->profiler = $profiler;
        $this->kernel   = $kernel;
    }

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents(): array
    {
        return [RequestEvent::class => 'onKernelRequest',];
    }

    public function onKernelRequest(): void
    {
        // Disable the profiler for users with the judgehost permission but not the admin one
        if ($this->profiler && $this->dj->checkrole('judgehost') && !$this->dj->checkrole('admin')) {
            $this->profiler->disable();
        }
    }
}
