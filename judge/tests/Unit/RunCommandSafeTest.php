<?php declare(strict_types=1);

namespace DOMjudge\Tests\Unit;

use DOMjudge\JudgeDaemon;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

class RunCommandSafeTest extends TestCase
{
    private ?JudgeDaemon $daemon = null;
    private ?ReflectionMethod $method = null;
    private string $tempDir;

    protected function setUp(): void
    {
        $reflection = new ReflectionClass(JudgeDaemon::class);
        $this->daemon = $reflection->newInstanceWithoutConstructor();
        $this->method = $reflection->getMethod('runCommandSafe');

        $this->tempDir = sys_get_temp_dir() . '/domjudge-test-' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $this->runCommandSafe(['rm', '-rf', $this->tempDir]);
        }
    }

    /**
     * @param string[] $command_parts
     */
    private function runCommandSafe(
        array $command_parts,
        ?int &$retval = null,
        bool $log_nonzero_exitcode = true,
        ?string $stdin_source = null,
        ?string $stdout_target = null,
        ?string $stderr_target = null
    ): bool {
        // We need to pass $retval by reference, so we use invokeArgs
        $args = [
            $command_parts,
            &$retval,
            $log_nonzero_exitcode,
            $stdin_source,
            $stdout_target,
            $stderr_target
        ];
        return $this->method->invokeArgs($this->daemon, $args);
    }

    public function testBasicSuccess(): void
    {
        $result = $this->runCommandSafe(['true']);
        $this->assertTrue($result);
    }

    public function testBasicFailure(): void
    {
        $retval = 0;
        $result = $this->runCommandSafe(['false'], $retval);
        $this->assertFalse($result);
        $this->assertEquals(1, $retval);
    }

    public function testExitCode(): void
    {
        $retval = 0;
        $result = $this->runCommandSafe(['sh', '-c', 'exit 42'], $retval);
        $this->assertFalse($result);
        $this->assertEquals(42, $retval);
    }

    public function testLargeOutputNoDeadlock(): void
    {
        // 1MB of zeros. This would deadlock if pipes were used and not read.
        $result = $this->runCommandSafe(['head', '-c', '1048576', '/dev/zero']);
        $this->assertTrue($result);
    }

    public function testStdoutRedirection(): void
    {
        $target = $this->tempDir . '/stdout.txt';
        $result = $this->runCommandSafe(['echo', '-n', 'hello world'], $retval, true, null, $target);
        $this->assertTrue($result);
        $this->assertEquals('hello world', file_get_contents($target));
    }

    public function testStdinRedirection(): void
    {
        $source = $this->tempDir . '/stdin.txt';
        $target = $this->tempDir . '/stdout.txt';
        file_put_contents($source, 'input data');

        $result = $this->runCommandSafe(['cat'], $retval, true, $source, $target);
        $this->assertTrue($result);
        $this->assertEquals('input data', file_get_contents($target));
    }

    public function testEmptyCommand(): void
    {
        $retval = 0;
        $result = $this->runCommandSafe([], $retval);
        $this->assertFalse($result);
        $this->assertEquals(-1, $retval);
    }
}
