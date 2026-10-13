<?php declare(strict_types=1);

namespace App\Tests\SnapshotIsolation;

/**
 * Finds transactions that can fail with MariaDB error 1020 ("Record has changed since last
 * read") once innodb_snapshot_isolation is ON.
 *
 * After a transaction's first plain (non-locking) read, which creates its read view, every
 * row it writes or locks fails with 1020 if another transaction changed that row in the
 * meantime. This holds for rows it never read, and for rows of tables it never read from:
 *
 *   BEGIN
 *   SELECT ... FROM a      -- creates the read view
 *   UPDATE b ...           -- 1020 if the row of b changed since the SELECT
 *
 * The race does not reproduce single-threaded, but the statement order that allows it does
 * show: a write or locking read on a table after the first plain read, unless the table was
 * locked before that read, because rows locked before the read view exists cannot change
 * underneath the transaction.
 *
 * The tracker looks at the SQL instead of the PHP source, because Doctrine lazy-loading
 * issues plain reads that are not visible there.
 *
 * Known limitations:
 *  - Locks are tracked per table: locking some rows of a table exempts writes to all of them.
 *  - Plain INSERTs are not checked. They fail only on a key a concurrent transaction
 *    inserted, which is an error (1062) without snapshot isolation as well.
 *  - Tables are found by regex. Of a comma-separated FROM list only the first table is seen,
 *    and statements starting with a comment or a modifier (UPDATE IGNORE) are misread.
 *  - An allowlisted method exempts everything below it on the stack.
 */
class TransactionTracker
{
    private const TABLE = '`?([A-Za-z_]\w*)`?';
    private const LOCKING_READ = '/\b(FOR\s+UPDATE|LOCK\s+IN\s+SHARE\s+MODE|FOR\s+SHARE)\b/i';

    private bool $inTransaction = false;

    /** @var array{sql: string, frames: list<string>}|null The plain read that created the read view. */
    private ?array $readView = null;

    /** @var array<string, true> Tables locked before the read view was created. */
    private array $locked = [];

    /**
     * @param array<string, string> $allowlist Class::method, or a namespace ending in a
     *                                         backslash, mapped to why it is safe.
     */
    public function __construct(private readonly array $allowlist = []) {}

    public function beginTransaction(): void
    {
        $this->inTransaction = true;
        $this->readView = null;
        $this->locked = [];
    }

    public function endTransaction(): void
    {
        $this->inTransaction = false;
    }

    /**
     * @throws SnapshotIsolationViolation
     */
    public function observe(string $sql): void
    {
        if (!$this->inTransaction || preg_match('/^\s*\(*\s*(\w+)/', $sql, $matches) !== 1) {
            return;
        }

        $kind = strtoupper($matches[1]);
        if ($kind === 'SELECT') {
            $tables = $this->tables($sql, '\b(?:FROM|JOIN)\s+');
            if ($tables === []) {
                // Like SELECT GET_LOCK(...): touches no table, creates no read view.
                return;
            }
            if (preg_match(self::LOCKING_READ, $sql) !== 1) {
                $this->readView ??= ['sql' => $sql, 'frames' => $this->applicationFrames()];
                return;
            }
            if ($this->readView === null) {
                // Rows a subquery reads are not locked.
                $outer = (string)preg_replace('/\((?:[^()]++|(?R))*\)/', '', $sql);
                $this->locked += array_fill_keys($this->tables($outer, '\b(?:FROM|JOIN)\s+'), true);
                return;
            }
        } else {
            $tables = match ($kind) {
                'UPDATE' => $this->tables(preg_split('/\bSET\b/i', $sql, 2)[0], '(?:\b(?:UPDATE|JOIN)\s+|,\s*)'),
                'DELETE' => $this->tables($sql, '\b(?:FROM|JOIN)\s+'),
                'REPLACE' => $this->tables($sql, '\bINTO\s+'),
                // INSERT IGNORE fails on a key a concurrent transaction inserted, where it
                // would otherwise do nothing.
                'INSERT' => preg_match('/^\s*INSERT\s+IGNORE\b|\bON\s+DUPLICATE\s+KEY\s+UPDATE\b/i', $sql) === 1
                    ? $this->tables($sql, '\bINTO\s+') : [],
                default => [],
            };
        }

        $unlocked = array_values(array_diff($tables, array_keys($this->locked)));
        if ($this->readView === null || $unlocked === []) {
            return;
        }

        $frames = $this->applicationFrames();
        if (!$this->isExempt($frames)) {
            throw new SnapshotIsolationViolation($unlocked, $this->readView['sql'], $sql, $this->readView['frames'], $frames);
        }
    }

    /**
     * Lowercased names of the tables that directly follow a match of $before.
     *
     * @return list<string>
     */
    private function tables(string $sql, string $before): array
    {
        preg_match_all('/' . $before . self::TABLE . '/i', $sql, $matches);

        return array_values(array_unique(array_map(strtolower(...), $matches[1])));
    }

    /**
     * @param list<string> $frames
     */
    private function isExempt(array $frames): bool
    {
        foreach ($frames as $frame) {
            foreach ($this->allowlist as $entry => $reason) {
                if ($frame === $entry || (str_ends_with($entry, '\\') && str_starts_with($frame, $entry))) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * The App\ methods on the stack, nearest first. The query itself is issued deep inside
     * Doctrine, so the responsible method is usually several frames up. Closures are skipped:
     * their names differ across PHP versions, and the method declaring them is on the stack.
     *
     * @return list<string>
     */
    private function applicationFrames(): array
    {
        $frames = [];
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
            $class = $frame['class'] ?? '';
            if (str_starts_with($class, 'App\\')
                && !str_starts_with($class, 'App\\Tests\\SnapshotIsolation\\')
                && !str_contains($frame['function'], '{closure')) {
                $frames[$class . '::' . $frame['function']] = true;
            }
        }

        return array_keys($frames);
    }
}
