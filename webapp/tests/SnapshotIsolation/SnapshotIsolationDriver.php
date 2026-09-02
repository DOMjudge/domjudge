<?php declare(strict_types=1);

namespace App\Tests\SnapshotIsolation;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use SensitiveParameter;

class SnapshotIsolationDriver extends AbstractDriverMiddleware
{
    /**
     * @param array<string, string> $allowlist
     */
    public function __construct(Driver $driver, private readonly array $allowlist)
    {
        parent::__construct($driver);
    }

    /**
     * {@inheritDoc}
     */
    public function connect(
        #[SensitiveParameter]
        array $params,
    ): DriverConnection {
        // One tracker per connection: transaction state is per connection.
        return new SnapshotIsolationConnection(
            parent::connect($params),
            new TransactionTracker($this->allowlist)
        );
    }
}
