<?php declare(strict_types=1);

namespace DOMJudgeBundle\Utils;

use DOMJudgeBundle\Entity\Contest;

class FreezeData
{
    const KEY_SHOW_FINAL = 'show-final';
    const KEY_SHOW_FINAL_JURY = 'show-final-jury';
    const KEY_SHOW_FROZEN = 'show-frozen';
    const KEY_STARTED = 'started';
    const KEY_STOPPED = 'stopped';
    const KEY_RUNNING = 'running';

    /**
     * @var Contest|null
     */
    protected $contest;

    /**
     * @var bool[]
     */
    protected $cache = [];

    /**
     * FreezeData constructor.
     * @param Contest|null $contest
     */
    public function __construct(Contest $contest = null)
    {
        $this->contest = $contest;
    }

    /**
     * Whether to show final scores
     * @param bool $jury
     * @return bool
     */
    public function showFinal(bool $jury = false)
    {
        if (!isset($this->cache[self::KEY_SHOW_FINAL]) || !isset($this->cache[self::KEY_SHOW_FINAL_JURY])) {
            if (!$this->contest || !$this->contest->getStarttimeEnabled()) {
                $this->cache[self::KEY_SHOW_FINAL] = $this->cache[self::KEY_SHOW_FINAL_JURY] = false;
            } else {
                // Show final scores if contest is over and unfreezetime has been
                // reached, or if contest is over and no freezetime had been set.
                // We can compare $now and the dbfields stringwise.
                $now                                    = microtime(true);
                $this->cache[self::KEY_SHOW_FINAL_JURY] = $this->contest->getFinalizetime() &&
                    Utils::difftime((float)$this->contest->getFinalizetime(), $now) <= 0;
                $this->cache[self::KEY_SHOW_FINAL]      = $this->cache[self::KEY_SHOW_FINAL_JURY] &&
                    (
                        !$this->contest->getFreezetime() ||
                        ($this->contest->getUnfreezetime() && Utils::difftime((float)$this->contest->getUnfreezetime(), $now) <= 0)
                    );
            }
        }

        return $jury ? $this->cache[self::KEY_SHOW_FINAL_JURY] : $this->cache[self::KEY_SHOW_FINAL];
    }

    /**
     * Whether to show the frozen scoreboard
     * @param bool $jury
     * @return bool
     */
    public function showFrozen(bool $jury = false)
    {
        if (!isset($this->cache[self::KEY_SHOW_FROZEN])) {
            if (!$this->contest || !$this->contest->getStarttimeEnabled()) {
                $this->cache[self::KEY_SHOW_FROZEN] = false;
            } else {
                // Freeze scoreboard if freeze time has been reached and we're not showing the final score yet
                $now                                = microtime(true);
                $this->cache[self::KEY_SHOW_FROZEN] = !$this->showFinal($jury) &&
                    $this->contest->getFreezetime() &&
                    Utils::difftime((float)$this->contest->getFreezetime(), $now) <= 0;
            }
        }

        return $this->cache[self::KEY_SHOW_FROZEN];
    }

    /**
     * Whether the contest has started
     * @return bool
     */
    public function started()
    {
        if (!isset($this->cache[self::KEY_STARTED])) {
            if (!$this->contest || !$this->contest->getStarttimeEnabled()) {
                $this->cache[self::KEY_STARTED] = false;
            } else {
                $now                            = microtime(true);
                $this->cache[self::KEY_STARTED] = Utils::difftime((float)$this->contest->getStarttime(), $now) <= 0;
            }
        }

        return $this->cache[self::KEY_STARTED];
    }

    /**
     * Whether the contest has stopped
     * @return bool
     */
    public function stopped()
    {
        if (!isset($this->cache[self::KEY_STOPPED])) {
            if (!$this->contest || !$this->contest->getStarttimeEnabled()) {
                $this->cache[self::KEY_STOPPED] = false;
            } else {
                $now                            = microtime(true);
                $this->cache[self::KEY_STOPPED] = Utils::difftime((float)$this->contest->getEndtime(), $now) <= 0;
            }
        }

        return $this->cache[self::KEY_STOPPED];
    }

    /**
     * Whether the contest is running
     * @return bool
     */
    public function running()
    {
        if (!isset($this->cache[self::KEY_RUNNING])) {
            $this->cache[self::KEY_RUNNING] = $this->started() && !$this->stopped();
        }

        return $this->cache[self::KEY_RUNNING];
    }
}
