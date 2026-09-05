<?php declare(strict_types=1);

namespace DOMjudge\Tests\Unit;

use DOMjudge\JudgeDaemon;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

class TestcaseDirectoryResetTest extends TestCase
{
    private ?JudgeDaemon $daemon = null;
    private ?ReflectionMethod $resetMethod = null;
    private string $tempDir;

    protected function setUp(): void
    {
        $reflection = new ReflectionClass(JudgeDaemon::class);
        $this->daemon = $reflection->newInstanceWithoutConstructor();
        $this->resetMethod = $reflection->getMethod('resetTestcaseDirectory');

        $this->tempDir = sys_get_temp_dir() . '/domjudge-test-' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            exec('rm -rf ' . escapeshellarg($this->tempDir));
        }
    }

    private function resetTestcaseDirectory(string $path): bool
    {
        return $this->resetMethod->invoke($this->daemon, $path);
    }

    public function testRepeatedSetupRemovesPreviousHardlinksAndOutput(): void
    {
        $compileDir = $this->tempDir . '/compile';
        mkdir($compileDir, 0755, true);
        file_put_contents($compileDir . '/program', 'compiled program');

        $testcaseDir = $this->tempDir . '/testcase00001';
        $programDir = $testcaseDir . '/1/execdir';
        mkdir($programDir, 0755, true);
        link($compileDir . '/program', $programDir . '/program');
        file_put_contents($testcaseDir . '/1/program.out', 'stale output');
        mkdir($testcaseDir . '/1/feedback', 0755, true);
        file_put_contents(
            $testcaseDir . '/1/feedback/judgemessage.txt',
            'stale feedback'
        );

        $this->assertSame(
            fileinode($compileDir . '/program'),
            fileinode($programDir . '/program')
        );

        $this->assertTrue($this->resetTestcaseDirectory($testcaseDir));
        $this->assertDirectoryExists($testcaseDir);
        $this->assertFileDoesNotExist($testcaseDir . '/1/program.out');
        $this->assertFileDoesNotExist(
            $testcaseDir . '/1/feedback/judgemessage.txt'
        );

        $programDir = $testcaseDir . '/1/execdir';
        mkdir($programDir, 0755, true);
        link($compileDir . '/program', $programDir . '/program');
        $this->assertFileExists($programDir . '/program');
        $this->assertSame(
            fileinode($compileDir . '/program'),
            fileinode($programDir . '/program')
        );
    }
}
