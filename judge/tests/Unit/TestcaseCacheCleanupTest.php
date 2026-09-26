<?php declare(strict_types=1);

namespace DOMjudge\Tests\Unit;

use DOMjudge\JudgeDaemon;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

class TestcaseCacheCleanupTest extends TestCase
{
    private ?JudgeDaemon $daemon = null;
    private ?ReflectionMethod $method = null;
    private string $tempDir;

    protected function setUp(): void
    {
        $reflection = new ReflectionClass(JudgeDaemon::class);
        $this->daemon = $reflection->newInstanceWithoutConstructor();
        $this->method = $reflection->getMethod('clearTestcaseCache');

        $this->tempDir = sys_get_temp_dir() . '/domjudge-test-' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            exec('rm -rf ' . escapeshellarg($this->tempDir));
        }
    }

    private function clearTestcaseCache(string $workdirpath): bool
    {
        return $this->method->invoke($this->daemon, $workdirpath);
    }

    private function permissions(string $path): int
    {
        clearstatcache(true, $path);
        return fileperms($path) & 0777;
    }

    public function testClearsCacheWithoutDeletingJudgingDirectories(): void
    {
        $workdirpath = $this->tempDir . '/endpoint-1';
        mkdir($workdirpath . '/testcase/42', 0755, true);
        mkdir($workdirpath . '/123/456', 0755, true);
        file_put_contents($workdirpath . '/testcase/42/hash.in', 'input');
        file_put_contents($workdirpath . '/testcase/42/hash.out', 'output');
        file_put_contents($workdirpath . '/123/456/result', 'result');

        $this->assertTrue($this->clearTestcaseCache($workdirpath));
        $this->assertDirectoryExists($workdirpath . '/testcase');
        $this->assertFileDoesNotExist($workdirpath . '/testcase/42/hash.in');
        $this->assertSame(0700, $this->permissions($workdirpath . '/testcase'));
        $this->assertFileExists($workdirpath . '/123/456/result');
    }

    public function testMissingCacheIsCreatedWithRestrictedPermissions(): void
    {
        $workdirpath = $this->tempDir . '/endpoint-1';
        mkdir($workdirpath, 0755, true);

        $this->assertTrue($this->clearTestcaseCache($workdirpath));
        $this->assertDirectoryExists($workdirpath . '/testcase');
        $this->assertSame(0700, $this->permissions($workdirpath . '/testcase'));
    }

    public function testCacheSymlinkDoesNotDeleteTarget(): void
    {
        $workdirpath = $this->tempDir . '/endpoint-1';
        $outside = $this->tempDir . '/outside';
        mkdir($workdirpath, 0755, true);
        mkdir($outside, 0755, true);
        file_put_contents($outside . '/keep', 'keep');
        symlink($outside, $workdirpath . '/testcase');

        $this->assertTrue($this->clearTestcaseCache($workdirpath));
        $this->assertFileExists($outside . '/keep');
        $this->assertFalse(is_link($workdirpath . '/testcase'));
        $this->assertDirectoryExists($workdirpath . '/testcase');
        $this->assertSame(0700, $this->permissions($workdirpath . '/testcase'));
    }
}
