<?php declare(strict_types=1);

namespace App\Tests\SnapshotIsolation;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;

class SnapshotIsolationConnection extends AbstractConnectionMiddleware
{
    public function __construct(Connection $connection, private readonly TransactionTracker $tracker)
    {
        parent::__construct($connection);
    }

    public function prepare(string $sql): Statement
    {
        // Statement::execute() does not receive the SQL, so carry it along.
        return new SnapshotIsolationStatement(parent::prepare($sql), $sql, $this->tracker);
    }

    public function query(string $sql): Result
    {
        $this->tracker->observe($sql);

        return parent::query($sql);
    }

    public function exec(string $sql): int|string
    {
        $this->tracker->observe($sql);

        return parent::exec($sql);
    }

    public function beginTransaction(): void
    {
        $this->tracker->beginTransaction();

        parent::beginTransaction();
    }

    public function commit(): void
    {
        $this->tracker->endTransaction();

        parent::commit();
    }

    public function rollBack(): void
    {
        $this->tracker->endTransaction();

        parent::rollBack();
    }
}
