<?php declare(strict_types=1);

namespace App\Tests\SnapshotIsolation;

use Doctrine\DBAL\Driver\Middleware\AbstractStatementMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;

class SnapshotIsolationStatement extends AbstractStatementMiddleware
{
    public function __construct(
        Statement $statement,
        private readonly string $sql,
        private readonly TransactionTracker $tracker,
    ) {
        parent::__construct($statement);
    }

    public function execute(): Result
    {
        // Observe at execution rather than at prepare time: a statement may be prepared
        // outside a transaction and executed inside one, and only the latter matters.
        $this->tracker->observe($this->sql);

        return parent::execute();
    }
}
