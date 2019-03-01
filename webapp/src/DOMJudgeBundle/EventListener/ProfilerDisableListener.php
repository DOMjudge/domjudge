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
    protected $DOMJudgeService;

    /**
     * @var Profiler
     */
    protected $profiler;

    /**
     * ProfilerDisableListener constructor.
     * @param KernelInterface $kernel
     * @param DOMJudgeService $DOMJudgeService
     * @param Profiler        $profiler
     */
    public function __construct(KernelInterface $kernel, DOMJudgeService $DOMJudgeService, Profiler $profiler)
    {
        $this->DOMJudgeService = $DOMJudgeService;
        $this->profiler        = $profiler;
        $this->kernel          = $kernel;
    }

    public function onKernelRequest()
    {
        // Disable the profiler for users with the judgehost permission but not the admin one, unless DEBUG contains DEBUG_JUDGE
        if ($this->DOMJudgeService->checkrole('judgehost') && !$this->DOMJudgeService->checkrole('admin')) {
            require_once $this->DOMJudgeService->getDomjudgeEtcDir() . '/common-config.php';

            if (!(DEBUG & DEBUG_JUDGE)) {
                $this->profiler->disable();
            }
        }
    }
}
