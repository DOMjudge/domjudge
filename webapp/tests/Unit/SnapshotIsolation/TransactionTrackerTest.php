<?php declare(strict_types=1);

namespace App\Tests\Unit\SnapshotIsolation;

use App\Tests\SnapshotIsolation\SnapshotIsolationViolation;
use App\Tests\SnapshotIsolation\TransactionTracker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TransactionTrackerTest extends TestCase
{
    /**
     * @param list<string> $statements
     * @param array<string, string> $allowlist
     */
    private function observeInTransaction(array $statements, array $allowlist = []): TransactionTracker
    {
        $tracker = new TransactionTracker($allowlist);
        $tracker->beginTransaction();
        foreach ($statements as $sql) {
            $tracker->observe($sql);
        }

        return $tracker;
    }

    #[DataProvider('provideViolations')]
    public function testWriteOrLockAfterAPlainReadIsAViolation(string $read, string $statement): void
    {
        $this->expectException(SnapshotIsolationViolation::class);
        $this->observeInTransaction([$read, $statement]);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideViolations(): iterable
    {
        yield 'update' => [
            'SELECT jobid FROM judgetask WHERE judgehostid IS NULL',
            'UPDATE judgetask SET valid=0 WHERE jobid = 1',
        ];
        // The read view covers the whole transaction, not only the tables read.
        yield 'update of a table that was not read' => [
            'SELECT judgingid FROM judging WHERE submitid = 1',
            'UPDATE judgetask SET valid=0 WHERE jobid = 1',
        ];
        yield 'delete' => [
            'SELECT queuetaskid FROM queuetask WHERE judgingid = 1',
            'DELETE FROM queuetask WHERE judgingid = 1',
        ];
        yield 'replace' => [
            'SELECT cid FROM scorecache WHERE cid = 1',
            'REPLACE INTO scorecache (cid, teamid, probid) VALUES (1, 2, 3)',
        ];
        yield 'insert ignore' => [
            'SELECT teamid FROM team WHERE teamid = 1',
            'INSERT IGNORE INTO balloon (submitid, teamid, probid, cid) VALUES (1, 1, 1, 1)',
        ];
        yield 'insert on duplicate key update' => [
            'SELECT teamid FROM balloon WHERE teamid = 1',
            'INSERT INTO balloon (teamid) VALUES (1) ON DUPLICATE KEY UPDATE teamid = 1',
        ];
        yield 'locking read' => [
            'SELECT judgingid FROM judging WHERE submitid = 1',
            'SELECT jobid FROM judgetask WHERE jobid = 1 FOR UPDATE',
        ];
        yield 'multi-table update' => [
            'SELECT runid FROM judging_run WHERE judgingid = 1',
            'UPDATE judgetask jt INNER JOIN judging_run jr ON jr.judgetaskid = jt.judgetaskid
                 SET jt.judgehostid = NULL, jt.starttime = NULL
                 WHERE jt.jobid = 1',
        ];
        yield 'backquoted name' => [
            'SELECT * FROM `judgetask` WHERE jobid = 1',
            'UPDATE `judgetask` SET valid=0 WHERE jobid = 1',
        ];
    }

    /**
     * @param list<string> $statements
     */
    #[DataProvider('provideAllowed')]
    public function testOtherSequencesAreAllowed(array $statements): void
    {
        $this->observeInTransaction($statements);

        $this->addToAssertionCount(1);
    }

    /**
     * @return iterable<string, array{list<string>}>
     */
    public static function provideAllowed(): iterable
    {
        yield 'lock, then plain read, then write the locked table' => [[
            'SELECT judgingid FROM judging WHERE judgingid = 1 FOR UPDATE',
            'SELECT runid FROM judging_run WHERE judgingid = 1',
            'UPDATE judging SET valid=0 WHERE judgingid = 1',
        ]];
        yield 'shared lock' => [[
            'SELECT jobid FROM judgetask LOCK IN SHARE MODE',
            'SELECT judgingid FROM judging WHERE submitid = 1',
            'UPDATE judgetask SET valid=0 WHERE jobid = 1',
        ]];
        // Writes do not create a read view.
        yield 'write before read' => [[
            'UPDATE judging SET valid=0 WHERE submitid = 1',
            'SELECT judgingid FROM judging WHERE submitid = 1',
        ]];
        yield 'plain insert' => [[
            'SELECT teamid FROM queuetask WHERE teamid = 1',
            'INSERT INTO queuetask (judgingid, priority, teamid) VALUES (1, 0, 1)',
        ]];
        yield 'statements that touch no table' => [[
            'SAVEPOINT DOCTRINE_2',
            'SET NAMES utf8mb4',
            'SELECT GET_LOCK(?, 3)',
            'RELEASE SAVEPOINT DOCTRINE_2',
            'UPDATE judgetask SET valid=0 WHERE jobid = 1',
        ]];
    }

    public function testASubqueryOfALockingReadLocksNothing(): void
    {
        $tracker = $this->observeInTransaction([
            'SELECT judgingid FROM judging WHERE submitid IN (SELECT submitid FROM submission) FOR UPDATE',
            'SELECT runid FROM judging_run',
            'UPDATE judging SET valid = 0',
        ]);

        $this->expectException(SnapshotIsolationViolation::class);
        $tracker->observe('UPDATE submission SET valid = 0');
    }

    public function testOutsideATransactionNothingIsDetected(): void
    {
        $tracker = new TransactionTracker();
        $tracker->observe('SELECT jobid FROM judgetask');
        $tracker->observe('UPDATE judgetask SET valid=0 WHERE jobid = 1');

        $this->addToAssertionCount(1);
    }

    public function testANewTransactionStartsWithoutReadViewOrLocks(): void
    {
        $tracker = $this->observeInTransaction(['SELECT jobid FROM judgetask FOR UPDATE', 'SELECT judgingid FROM judging']);
        $tracker->endTransaction();

        $tracker->beginTransaction();
        $tracker->observe('UPDATE judgetask SET valid=0 WHERE jobid = 1');
        $tracker->observe('SELECT judgingid FROM judging');

        $this->expectException(SnapshotIsolationViolation::class);
        $tracker->observe('UPDATE judgetask SET valid=0 WHERE jobid = 1');
    }

    private function performRead(TransactionTracker $tracker): void
    {
        $tracker->observe('SELECT externalid FROM problem WHERE externalid = ?');
    }

    private function performWrite(TransactionTracker $tracker): void
    {
        $tracker->observe('UPDATE problem SET externalid = ? WHERE probid = ?');
    }

    /**
     * @param array<string, string> $allowlist
     */
    #[DataProvider('provideSuppressingAllowlists')]
    public function testAllowlistedFrameSuppressesTheViolation(array $allowlist): void
    {
        $tracker = $this->observeInTransaction([], $allowlist);
        $this->performRead($tracker);
        $this->performWrite($tracker);

        $this->addToAssertionCount(1);
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function provideSuppressingAllowlists(): iterable
    {
        yield 'method on the stack of the write' => [[
            self::class . '::testAllowlistedFrameSuppressesTheViolation' => 'covered by this test',
        ]];
        yield 'namespace prefix' => [[
            'App\Tests\Unit\SnapshotIsolation\\' => 'whole namespace',
        ]];
    }

    /**
     * @param array<string, string> $allowlist
     */
    #[DataProvider('provideUnrelatedAllowlists')]
    public function testUnrelatedAllowlistEntryDoesNotSuppress(array $allowlist): void
    {
        $tracker = $this->observeInTransaction([], $allowlist);
        $this->performRead($tracker);

        $this->expectException(SnapshotIsolationViolation::class);
        $this->performWrite($tracker);
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function provideUnrelatedAllowlists(): iterable
    {
        yield 'other method' => [['App\Some\Other::method' => 'unrelated']];
        // The read view it created still exposes the write.
        yield 'method that did the read' => [[self::class . '::performRead' => 'the read']];
        yield 'other namespace prefix' => [['App\DataFixtures\Test\\' => 'fixtures only']];
    }

    public function testViolationReportsTablesStatementsAndFrames(): void
    {
        $tracker = $this->observeInTransaction([
            'SELECT judgingid FROM judging WHERE judgingid = 1 FOR UPDATE',
            'SELECT DISTINCT jobid FROM judgetask WHERE judgehostid IS NULL AND valid = 1',
        ]);

        try {
            $tracker->observe('UPDATE judgetask jt JOIN judging j ON j.judgingid = jt.jobid SET jt.valid = 0');
            self::fail('Expected a SnapshotIsolationViolation.');
        } catch (SnapshotIsolationViolation $violation) {
            self::assertSame(['judgetask'], $violation->tables, 'the locked table is not reported');
            self::assertStringContainsString('SELECT DISTINCT jobid', $violation->readSql);
            self::assertStringContainsString('UPDATE judgetask jt', $violation->sql);
            self::assertContains(self::class . '::testViolationReportsTablesStatementsAndFrames', $violation->frames);
            self::assertContains(self::class . '::observeInTransaction', $violation->readFrames);
            self::assertStringContainsString('judgetask', $violation->getMessage());
        }
    }
}
