<?php declare(strict_types=1);

namespace App\Tests\Session;

use Twig\Extension\ProfilerExtension;
use Twig\Profiler\Profile;

/**
 * Marks the moment the first template starts rendering in the SessionEventLog.
 */
final class RenderPhaseProfilerExtension extends ProfilerExtension
{
    public function __construct()
    {
        parent::__construct(new Profile());
    }

    public function enter(Profile $profile): void
    {
        if (SessionEventLog::phase() === SessionEventLog::PHASE_ACTION) {
            SessionEventLog::setPhase(SessionEventLog::PHASE_RENDER);
        }
        parent::enter($profile);
    }
}
