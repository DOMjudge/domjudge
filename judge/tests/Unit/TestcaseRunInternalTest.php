<?php declare(strict_types=1);

namespace DOMjudge\Tests\Unit;

use DOMjudge\JudgeDaemon;
use DOMjudge\Verdict;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * @phpstan-type TimeLimit array{cpu: array{0: string, 1: string}, wall: array{0: string, 1: string}}
 */
class TestcaseRunInternalTest extends TestCase
{
    private ?JudgeDaemon $daemon = null;
    private ?ReflectionMethod $method = null;
    private string $tempDir;
    private string $originalCwd;

    protected function setUp(): void
    {
        $this->originalCwd = getcwd();

        $reflection = new ReflectionClass(JudgeDaemon::class);
        $this->daemon = $reflection->newInstanceWithoutConstructor();
        $this->method = $reflection->getMethod('testcaseRunInternal');

        // Initialize the runuser and rungroup properties that testcaseRunInternal now uses
        $runuserProperty = $reflection->getProperty('runuser');
        $runuserProperty->setValue($this->daemon, RUNUSER);

        $rungroupProperty = $reflection->getProperty('rungroup');
        $rungroupProperty->setValue($this->daemon, RUNGROUP);

        $this->tempDir = sys_get_temp_dir() . '/domjudge-test-' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);
        $this->recursiveDelete($this->tempDir);
    }

    private function recursiveDelete(string $dir): void
    {
        if (is_dir($dir)) {
            exec('rm -rf ' . escapeshellarg($dir));
        }
    }

    /**
     * @param TimeLimit $timelimit
     * @param array<string, string>|null $run_config
     * @param array<string, string>|null $compare_config
     */
    private function callTestcaseRunInternal(
        string $input,
        string $output,
        array $timelimit,
        string $passdir,
        string $run_runpath,
        bool $combined_run_compare,
        string $compare_runpath,
        ?string $compare_args,
        ?array $run_config = null,
        ?array $compare_config = null
    ): Verdict {
        $run_config = $run_config ?? $this->defaultRunConfig();
        $compare_config = $compare_config ?? $this->defaultCompareConfig();

        return $this->method->invoke(
            $this->daemon,
            $input,
            $output,
            $timelimit,
            $passdir,
            $run_runpath,
            $combined_run_compare,
            $compare_runpath,
            $compare_args,
            $run_config,
            $compare_config
        );
    }

    /**
     * @return array{memory_limit: int, output_limit: int, process_limit: int}
     */
    private function defaultRunConfig(): array
    {
        return [
            'memory_limit' => 2097152,
            'output_limit' => 8192,
            'process_limit' => 64,
        ];
    }

    /**
     * @return array{script_timelimit: int, script_memory_limit: int, script_filesize_limit: int}
     */
    private function defaultCompareConfig(): array
    {
        return [
            'script_timelimit' => 30,
            'script_memory_limit' => 2097152,
            'script_filesize_limit' => 2621440,
        ];
    }

    private function createTestFile(string $name, string $content = ''): string
    {
        $path = $this->tempDir . '/' . $name;
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($path, $content);
        return $path;
    }

    private function createExecutable(string $name): string
    {
        $path = $this->createTestFile($name, "#!/bin/sh\nexit 0\n");
        chmod($path, 0755);
        return $path;
    }

    private function createPassDir(): string
    {
        $passdir = $this->tempDir . '/passdir';
        mkdir($passdir, 0755, true);
        mkdir($passdir . '/execdir', 0755, true);
        $program = $passdir . '/execdir/program';
        file_put_contents($program, "#!/bin/sh\necho test\n");
        chmod($program, 0755);
        return $passdir;
    }

    /**
     * @return TimeLimit
     */
    private function defaultTimelimit(): array
    {
        return [
            'cpu' => ['5', '10'],
            'wall' => ['10', '20'],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function missingFileProvider(): array
    {
        return [
            'input file not found' => ['input', '/nonexistent/input.txt'],
            'output file not found' => ['output', '/nonexistent/output.txt'],
            'passdir not found' => ['passdir', '/nonexistent/passdir'],
        ];
    }

    /**
     * @dataProvider missingFileProvider
     */
    public function testMissingFileReturnsInternalError(string $which, string $badPath): void
    {
        $input = $which === 'input' ? $badPath : $this->createTestFile('testdata.in', 'test input');
        $output = $which === 'output' ? $badPath : $this->createTestFile('testdata.out', 'expected output');
        $passdir = $which === 'passdir' ? $badPath : $this->createPassDir();
        $run = $this->createExecutable('run.sh');
        $compare = $this->createExecutable('compare.sh');

        $result = $this->callTestcaseRunInternal(
            $input,
            $output,
            $this->defaultTimelimit(),
            $passdir,
            $run,
            false,
            $compare,
            null
        );

        $this->assertEquals(Verdict::INTERNAL_ERROR, $result);
    }

    public function testPassdirNotWritableReturnsInternalError(): void
    {
        $input = $this->createTestFile('testdata.in', 'test input');
        $output = $this->createTestFile('testdata.out', 'expected output');
        $passdir = $this->tempDir . '/readonly_passdir';
        mkdir($passdir, 0555, true);
        $run = $this->createExecutable('run.sh');
        $compare = $this->createExecutable('compare.sh');

        $result = $this->callTestcaseRunInternal(
            $input,
            $output,
            $this->defaultTimelimit(),
            $passdir,
            $run,
            false,
            $compare,
            null
        );

        chmod($passdir, 0755);

        $this->assertEquals(Verdict::INTERNAL_ERROR, $result);
    }

    public function testPassdirNotExecutableReturnsInternalError(): void
    {
        $input = $this->createTestFile('testdata.in', 'test input');
        $output = $this->createTestFile('testdata.out', 'expected output');
        $passdir = $this->tempDir . '/noexec_passdir';
        mkdir($passdir, 0666, true);
        $run = $this->createExecutable('run.sh');
        $compare = $this->createExecutable('compare.sh');

        $result = $this->callTestcaseRunInternal(
            $input,
            $output,
            $this->defaultTimelimit(),
            $passdir,
            $run,
            false,
            $compare,
            null
        );

        chmod($passdir, 0755);

        $this->assertEquals(Verdict::INTERNAL_ERROR, $result);
    }

    public function testProgramNotExecutableReturnsInternalError(): void
    {
        $input = $this->createTestFile('testdata.in', 'test input');
        $output = $this->createTestFile('testdata.out', 'expected output');
        $passdir = $this->createPassDir();
        chmod($passdir . '/execdir/program', 0644);
        $run = $this->createExecutable('run.sh');
        $compare = $this->createExecutable('compare.sh');

        $result = $this->callTestcaseRunInternal(
            $input,
            $output,
            $this->defaultTimelimit(),
            $passdir,
            $run,
            false,
            $compare,
            null
        );

        $this->assertEquals(Verdict::INTERNAL_ERROR, $result);
    }

    public function testRunScriptNotExecutableReturnsInternalError(): void
    {
        $input = $this->createTestFile('testdata.in', 'test input');
        $output = $this->createTestFile('testdata.out', 'expected output');
        $passdir = $this->createPassDir();
        $run = $this->createTestFile('run.sh', "#!/bin/sh\nexit 0\n");
        $compare = $this->createExecutable('compare.sh');

        $result = $this->callTestcaseRunInternal(
            $input,
            $output,
            $this->defaultTimelimit(),
            $passdir,
            $run,
            false,
            $compare,
            null
        );

        $this->assertEquals(Verdict::INTERNAL_ERROR, $result);
    }

    public function testCompareScriptNotExecutableReturnsInternalError(): void
    {
        $input = $this->createTestFile('testdata.in', 'test input');
        $output = $this->createTestFile('testdata.out', 'expected output');
        $passdir = $this->createPassDir();
        $run = $this->createExecutable('run.sh');
        $compare = $this->createTestFile('compare.sh', "#!/bin/sh\nexit 42\n");

        $result = $this->callTestcaseRunInternal(
            $input,
            $output,
            $this->defaultTimelimit(),
            $passdir,
            $run,
            false,
            $compare,
            null
        );

        $this->assertEquals(Verdict::INTERNAL_ERROR, $result);
    }
}
