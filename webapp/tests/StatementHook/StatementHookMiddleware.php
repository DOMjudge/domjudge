<?php declare(strict_types=1);

namespace App\Tests\StatementHook;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use SensitiveParameter;

/**
 * Lets a test run code right before the application issues a given statement.
 *
 * Races between two requests do not reproduce single-threaded, but a test can play the other
 * request: register a hook for the statement where the interleaving matters, and have the
 * callback do what the competitor would have done by then. Test environment only, see the
 * when@test block in config/services.yaml.
 *
 * Hooks fire when a statement is prepared. DBAL executes it right after, so for the code
 * under test that is the same moment.
 */
class StatementHookMiddleware implements Middleware
{
    /**
     * Static, as the kernel is rebooted between requests and takes its services with it.
     *
     * @var list<array{string, callable(): void}>
     */
    private static array $hooks = [];

    /**
     * Run $callback once, right before the next statement that contains $sql is issued.
     *
     * @param callable(): void $callback
     */
    public static function once(string $sql, callable $callback): void
    {
        self::$hooks[] = [$sql, $callback];
    }

    public static function reset(): void
    {
        self::$hooks = [];
    }

    public static function fire(string $sql): void
    {
        foreach (self::$hooks as $index => [$needle, $callback]) {
            if (str_contains($sql, $needle)) {
                // Remove first, the callback issues statements itself.
                unset(self::$hooks[$index]);
                $callback();

                return;
            }
        }
    }

    public function wrap(Driver $driver): Driver
    {
        return new class($driver) extends AbstractDriverMiddleware {
            public function connect(#[SensitiveParameter] array $params): Connection
            {
                return new class(parent::connect($params)) extends AbstractConnectionMiddleware {
                    public function prepare(string $sql): Statement
                    {
                        StatementHookMiddleware::fire($sql);

                        return parent::prepare($sql);
                    }

                    public function query(string $sql): Result
                    {
                        StatementHookMiddleware::fire($sql);

                        return parent::query($sql);
                    }

                    public function exec(string $sql): int|string
                    {
                        StatementHookMiddleware::fire($sql);

                        return parent::exec($sql);
                    }
                };
            }
        };
    }
}
