<?php declare(strict_types=1);

namespace App\Tests\SnapshotIsolation;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;

/**
 * Registers the snapshot-isolation detector on a DBAL connection. Test environment only;
 * see the when@test block in config/services.yaml.
 */
class SnapshotIsolationMiddleware implements Middleware
{
    public function wrap(Driver $driver): Driver
    {
        return new SnapshotIsolationDriver($driver, require __DIR__ . '/allowlist.php');
    }
}
