<?php declare(strict_types=1);

namespace App\Tests\Unit\SnapshotIsolation;

use App\Tests\SnapshotIsolation\SnapshotIsolationConnection;
use App\Tests\SnapshotIsolation\SnapshotIsolationMiddleware;
use App\Tests\SnapshotIsolation\SnapshotIsolationViolation;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the middleware chain itself, in particular that SQL survives the hop from
 * prepare() to execute(): the DBAL Statement interface does not carry it.
 */
class SnapshotIsolationMiddlewareTest extends TestCase
{
    private function connect(): SnapshotIsolationConnection
    {
        $statement = $this->createMock(Statement::class);
        $statement->method('execute')->willReturn($this->createMock(Result::class));

        $inner = $this->createMock(DriverConnection::class);
        $inner->method('prepare')->willReturn($statement);
        $inner->method('query')->willReturn($this->createMock(Result::class));
        $inner->method('exec')->willReturn(0);

        $driver = $this->createMock(Driver::class);
        $driver->method('connect')->willReturn($inner);

        $connection = (new SnapshotIsolationMiddleware())->wrap($driver)->connect([]);

        self::assertInstanceOf(SnapshotIsolationConnection::class, $connection);

        return $connection;
    }

    public function testPreparedStatementsCarryTheirSqlToExecution(): void
    {
        $connection = $this->connect();

        // Prepared outside the transaction, executed inside it: only the execution counts.
        $select = $connection->prepare('SELECT jobid FROM judgetask WHERE judgehostid IS NULL');
        $update = $connection->prepare('UPDATE judgetask SET valid = 0 WHERE jobid = ?');

        $connection->beginTransaction();
        $select->execute();

        $this->expectException(SnapshotIsolationViolation::class);
        $update->execute();
    }

    public function testQueryAndExecAreObserved(): void
    {
        $connection = $this->connect();
        $connection->beginTransaction();
        $connection->query('SELECT jobid FROM judgetask');

        $this->expectException(SnapshotIsolationViolation::class);
        $connection->exec('UPDATE judgetask SET valid = 0 WHERE jobid = 1');
    }
}
