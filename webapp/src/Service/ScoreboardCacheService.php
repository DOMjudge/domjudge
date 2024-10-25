<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\Contest;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class ScoreboardCacheService
{
    public function __construct(
        protected readonly ConfigurationService $config,
        protected readonly TagAwareCacheInterface $twigCache,
    ) {}

    /**
     * @template T
     * @param Contest $contest
     * @param string $cacheKey
     * @param callable(): T $callable
     *
     * @return T
     */
    public function cacheScoreboardData(
        Contest $contest,
        string $cacheKey,
        callable $callable
    ): mixed {
        if (!$this->config->get('cache_full_scoreboard')) {
            return $callable();
        }

        return $this->twigCache->get($cacheKey, function (ItemInterface $item) use (
            $callable,
            $contest
        ) {
            $item->tag('scoreboard_' . $contest->getCid());

            return $callable();
        });
    }

    public function invalidate(Contest $contest): void
    {
        $this->twigCache->invalidateTags(['scoreboard_' . $contest->getCid()]);
    }
}
