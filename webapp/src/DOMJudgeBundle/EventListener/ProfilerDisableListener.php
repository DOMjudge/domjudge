<?php declare(strict_types=1);

namespace DOMJudgeBundle\EventListener;

use DOMJudgeBundle\Service\DOMJudgeService;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\Profiler\Profiler;

/**
 * Class ProfilerDisableListener
 * @package DOMJudgeBundle\EventListener
 */
class ProfilerDisableListener
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
     * @var Profiler
     */
    protected $profiler;

    /**
     * ProfilerDisableListener constructor.
     * @param KernelInterface $kernel
     * @param DOMJudgeService $dj
     * @param Profiler        $profiler
     */
    public function __construct(KernelInterface $kernel, DOMJudgeService $dj, Profiler $profiler)
    {
        $this->dj       = $dj;
        $this->profiler = $profiler;
        $this->kernel   = $kernel;
    }

    public function onKernelRequest()
    {
        // Disable the profiler for users with the judgehost permission but not the admin one,
        // unless DEBUG contains DEBUG_JUDGE.
        if ($this->dj->checkrole('judgehost') && !$this->dj->checkrole('admin')) {
            require_once $this->dj->getDomjudgeEtcDir() . '/common-config.php';
            if (!(DEBUG & DEBUG_JUDGE)) {
                $this->profiler->disable();
            }
        }
    }
}
