<?php declare(strict_types=1);

namespace App\Utils;

use App\Entity\Contest;

/**
 * Class FreezeData
 *
 * @package App\Utils
 */
class FreezeData
{
    const KEY_SHOW_FINAL = 'show-final';
    const KEY_SHOW_FINAL_JURY = 'show-final-jury';
    const KEY_SHOW_FROZEN = 'show-frozen';
    const KEY_STARTED = 'started';
    const KEY_STOPPED = 'stopped';
    const KEY_RUNNING = 'running';
    const KEY_FINALIZED = 'finalized';

    protected ?Contest $contest;

    /** @var bool[] */
    protected array $cache = [];

    public function __construct(Contest $contest = null)
    {
        $this->contest = $contest;
    }

    /**
     * Whether to show final scores
     */
    public function showFinal(bool $jury = false): bool
    {
        if (!isset($this->cache[self::KEY_SHOW_FINAL]) ||
            !isset($this->cache[self::KEY_SHOW_FINAL_JURY])) {
            if (!$this->contest || !$this->contest->getStarttimeEnabled()) {
                $this->cache[self::KEY_SHOW_FINAL]      = false;
                $this->cache[self::KEY_SHOW_FINAL_JURY] = false;
            } else {
                $now = Utils::now();
                $this->cache[self::KEY_SHOW_FINAL_JURY] =
                    Utils::difftime((float)$this->contest->getEndtime(), $now) <= 0;

                // Show final scores if contest is over and unfreezetime has been
                // reached, or if contest is over and no freezetime had been set.
                $hasFreezeTime                     = $this->contest->getFreezetime() !== null;
                $hasUnfreezeTime                   = $this->contest->getUnfreezetime() !== null;
                $this->cache[self::KEY_SHOW_FINAL] =
                    (!$hasFreezeTime && Utils::difftime((float)$this->contest->getEndtime(), $now) <= 0) ||
                    ($hasUnfreezeTime && Utils::difftime((float)$this->contest->getUnfreezetime(), $now) <= 0);
            }
        }

        return $jury ? $this->cache[self::KEY_SHOW_FINAL_JURY] : $this->cache[self::KEY_SHOW_FINAL];
    }

    /**
     * Whether to show the frozen scoreboard.
     */
    public function showFrozen(): bool
    {
        if (!isset($this->cache[self::KEY_SHOW_FROZEN])) {
            if (!$this->contest || !$this->contest->getStarttimeEnabled()) {
                $this->cache[self::KEY_SHOW_FROZEN] = false;
            } else {
                // Freeze scoreboard if freeze time has been reached and we're not showing the final score yet.
                $now             = Utils::now();
                $hasFreezeTime   = $this->contest->getFreezetime() !== null;
                $hasUnfreezeTime = $this->contest->getUnfreezetime() !== null;
                // Show frozen scoreboard when we are between freezetime and unfreezetime.
                $this->cache[self::KEY_SHOW_FROZEN] =
                    ($hasFreezeTime && Utils::difftime((float)$this->contest->getFreezetime(), $now) <= 0) &&
                    (!$hasUnfreezeTime || Utils::difftime($now, (float)$this->contest->getUnfreezetime()) <= 0);
            }
        }

        return $this->cache[self::KEY_SHOW_FROZEN];
    }

    /**
     * Whether the contest has started.
     */
    public function started(): bool
    {
        if (!isset($this->cache[self::KEY_STARTED])) {
            if (!$this->contest || !$this->contest->getStarttimeEnabled()) {
                $this->cache[self::KEY_STARTED] = false;
            } else {
                $now                            = Utils::now();
                $this->cache[self::KEY_STARTED] = Utils::difftime((float)$this->contest->getStarttime(), $now) <= 0;
            }
        }

        return $this->cache[self::KEY_STARTED];
    }

    /**
     * Whether the contest has stopped.
     */
    public function stopped(): bool
    {
        if (!isset($this->cache[self::KEY_STOPPED])) {
            if (!$this->contest || !$this->contest->getStarttimeEnabled()) {
                $this->cache[self::KEY_STOPPED] = false;
            } else {
                $now                            = Utils::now();
                $this->cache[self::KEY_STOPPED] = Utils::difftime((float)$this->contest->getEndtime(), $now) <= 0;
            }
        }

        return $this->cache[self::KEY_STOPPED];
    }

    /**
     * Whether the contest is running.
     */
    public function running(): bool
    {
        if (!isset($this->cache[self::KEY_RUNNING])) {
            $this->cache[self::KEY_RUNNING] = $this->started() && !$this->stopped();
        }

        return $this->cache[self::KEY_RUNNING];
    }

    /**
     * Whether the contest is finalized.
     * This is normally only used when we have a downstream CDS.
     */
    public function finalized(): bool
    {
        if (!isset($this->cache[self::KEY_FINALIZED])) {
            if (!$this->contest || !$this->contest->getStarttimeEnabled()) {
                $this->cache[self::KEY_FINALIZED] = false;
            } else {
                $now                              = Utils::now();
                $this->cache[self::KEY_FINALIZED] = $this->contest->getFinalizetime() !== null &&
                    Utils::difftime((float)$this->contest->getFinalizetime(), $now) <= 0;
            }
        }

        return $this->cache[self::KEY_FINALIZED];
    }

    /**
     * Get the progress of this freezedata.
     */
    public function getProgress(): int
    {
        $now = Utils::now();
        if (!$this->started()) {
            return -1;
        }
        $left = Utils::difftime((float)$this->contest->getEndtime(), $now);
        if ($left <= 0) {
            return 100;
        }

        $passed   = Utils::difftime((float)$this->contest->getStarttime(), $now);
        $duration = Utils::difftime((float)$this->contest->getStarttime(), (float)$this->contest->getEndtime());
        return (int)($passed * 100. / $duration);
    }
}
