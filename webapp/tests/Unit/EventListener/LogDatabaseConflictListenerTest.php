<?php declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\EventListener\LogDatabaseConflictListener;
use Doctrine\DBAL\Driver\Exception as DriverExceptionInterface;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Doctrine\DBAL\Query;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use RuntimeException;
use Stringable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Throwable;

class LogDatabaseConflictListenerTest extends TestCase
{
    /**
     * Feed the throwable to the listener and return what it logged.
     *
     * @return list<array{level: mixed, message: string, context: array<string, mixed>}>
     */
    private function handle(Throwable $throwable): array
    {
        $logger = new class extends AbstractLogger {
            /** @var list<array{level: mixed, message: string, context: array<string, mixed>}> */
            public array $records = [];

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->records[] = ['level' => $level, 'message' => (string)$message, 'context' => $context];
            }
        };

        $listener = new LogDatabaseConflictListener($logger);
        $listener(new ExceptionEvent(
            $this->createMock(HttpKernelInterface::class),
            Request::create('/jury/submissions/1/verify', 'POST'),
            HttpKernelInterface::MAIN_REQUEST,
            $throwable
        ));

        return $logger->records;
    }

    private static function driverException(int $code): DriverExceptionInterface
    {
        return new class($code) extends RuntimeException implements DriverExceptionInterface {
            public function __construct(int $errorCode)
            {
                parent::__construct('driver failure', $errorCode);
            }

            public function getSQLState(): string
            {
                return 'HY000';
            }
        };
    }

    #[DataProvider('provideConflicts')]
    public function testConflictsAreLogged(Throwable $throwable, string $expectedKind): void
    {
        $records = $this->handle($throwable);

        self::assertCount(1, $records, 'the conflict should have been logged exactly once');
        $record = $records[0];
        self::assertSame('warning', $record['level']);
        self::assertStringStartsWith('db-conflict: ', $record['message']);
        self::assertSame($expectedKind, $record['context']['kind']);
        self::assertSame('POST', $record['context']['method']);
        self::assertSame('/jury/submissions/1/verify', $record['context']['path']);
    }

    /**
     * @return iterable<string, array{Throwable, string}>
     */
    public static function provideConflicts(): iterable
    {
        $query = new Query('UPDATE judging SET verified = 1', [], []);

        yield 'deadlock' => [
            new DeadlockException(self::driverException(1213), $query),
            'deadlock',
        ];
        yield 'lock wait timeout' => [
            new LockWaitTimeoutException(self::driverException(1205), $query),
            'lock wait timeout',
        ];
        // Doctrine has no dedicated class for 1020, so it arrives as a plain DriverException.
        yield 'record changed since last read' => [
            new DriverException(self::driverException(1020), $query),
            'record changed since last read',
        ];
        yield 'wrapped by the controller' => [
            new RuntimeException('failed to verify', 0, new DeadlockException(self::driverException(1213), $query)),
            'deadlock',
        ];
    }

    public function testUnrelatedDriverErrorsAreIgnored(): void
    {
        // 1062 is a duplicate key: a bug, but not a conflict this is about.
        $records = $this->handle(new DriverException(self::driverException(1062), new Query('INSERT INTO balloon', [], [])));

        self::assertSame([], $records);
    }

    public function testUnrelatedExceptionsAreIgnored(): void
    {
        $records = $this->handle(new RuntimeException('something else entirely'));

        self::assertSame([], $records);
    }
}
