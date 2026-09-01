<?php declare(strict_types=1);

namespace App\EventListener;

use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Throwable;

/**
 * Log requests that failed on a database conflict under a fixed, greppable marker.
 *
 * Deadlocks, lock wait timeouts and MariaDB's "record has changed since last read" used to be
 * indistinguishable from any other 500, so nobody could tell how often they happened. This
 * listener only logs them: a conflict that gets this far is a code path that still needs
 * fixing, and a friendlier response would hide that. Judgedaemons retry failed requests anyway.
 *
 * See https://github.com/DOMjudge/domjudge/issues/2848
 */
#[AsEventListener]
readonly class LogDatabaseConflictListener
{
    /**
     * Raised by MariaDB under innodb_snapshot_isolation; Doctrine has no exception class for it.
     */
    private const ERROR_RECORD_HAS_CHANGED = 1020;

    public function __construct(protected LoggerInterface $logger) {}

    public function __invoke(ExceptionEvent $event): void
    {
        // The conflict is usually wrapped, by Doctrine and then by the controller, so walk the chain.
        for ($throwable = $event->getThrowable(); $throwable !== null; $throwable = $throwable->getPrevious()) {
            $kind = self::conflictKind($throwable);
            if ($kind === null) {
                continue;
            }

            $this->logger->warning('db-conflict: {kind} on {method} {path}: {message}', [
                'kind' => $kind,
                'method' => $event->getRequest()->getMethod(),
                'path' => $event->getRequest()->getPathInfo(),
                'message' => $throwable->getMessage(),
            ]);
            return;
        }
    }

    private static function conflictKind(Throwable $throwable): ?string
    {
        return match (true) {
            $throwable instanceof DeadlockException => 'deadlock',
            $throwable instanceof LockWaitTimeoutException => 'lock wait timeout',
            $throwable instanceof DriverException
                && $throwable->getCode() === self::ERROR_RECORD_HAS_CHANGED => 'record changed since last read',
            default => null,
        };
    }
}
