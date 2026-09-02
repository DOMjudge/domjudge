<?php declare(strict_types=1);

namespace App\Tests\SnapshotIsolation;

use RuntimeException;

/**
 * Thrown for a statement that MariaDB can reject with error 1020 once
 * innodb_snapshot_isolation is ON. See TransactionTracker.
 */
class SnapshotIsolationViolation extends RuntimeException
{
    /**
     * @param list<string> $tables     Tables the statement writes or locks without an earlier lock
     * @param list<string> $readFrames Application methods that issued the read creating the read view
     * @param list<string> $frames     Application methods that issued the statement
     */
    public function __construct(
        public readonly array $tables,
        public readonly string $readSql,
        public readonly string $sql,
        public readonly array $readFrames,
        public readonly array $frames,
    ) {
        parent::__construct(sprintf(
            "Snapshot-isolation violation on %s.\n\n"
            . "This statement writes or locks rows it did not lock before the first plain read of its transaction:\n    %s\n"
            . "Plain read:\n    %s\n\n"
            . "With innodb_snapshot_isolation=ON it fails with error 1020 whenever another transaction changed those rows after that read.\n\n"
            . "Statement issued from:\n    %s\nRead issued from:\n    %s\n\n"
            . "Lock the rows before any plain read, or do the read outside the transaction. If no other code can write these rows\n"
            . "concurrently, add a method the statement was issued from to tests/SnapshotIsolation/allowlist.php and say why.\n",
            implode(', ', $tables),
            self::oneLine($sql),
            self::oneLine($readSql),
            implode("\n    ", $frames),
            implode("\n    ", $readFrames),
        ));
    }

    private static function oneLine(string $sql): string
    {
        return mb_strimwidth(trim((string)preg_replace('/\s+/', ' ', $sql)), 0, 300, ' ...');
    }
}
