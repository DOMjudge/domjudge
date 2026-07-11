<?php declare(strict_types=1);

namespace DOMjudge\Tests\Unit;

use DOMjudge\JudgeDaemon;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

class ChmodRecursiveTest extends TestCase
{
    private ?JudgeDaemon $daemon = null;
    private ?ReflectionMethod $method = null;
    private string $tempDir;

    protected function setUp(): void
    {
        $reflection = new ReflectionClass(JudgeDaemon::class);
        $this->daemon = $reflection->newInstanceWithoutConstructor();
        $this->method = $reflection->getMethod('chmodRecursive');

        $this->tempDir = sys_get_temp_dir() . '/domjudge-test-' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            // Make sure everything is writable so the tree can be removed.
            $this->chmodRecursive($this->tempDir, 0700, 0);
            exec('rm -rf ' . escapeshellarg($this->tempDir));
        }
    }

    private function chmodRecursive(string $path, int $add, int $remove): bool
    {
        return $this->method->invoke($this->daemon, $path, $add, $remove);
    }

    private function perms(string $path): int
    {
        clearstatcache();
        return fileperms($path) & 07777;
    }

    public function testAddsWriteBitsRecursively(): void
    {
        mkdir($this->tempDir . '/sub/deep', 0755, true);
        file_put_contents($this->tempDir . '/a.txt', 'x');
        file_put_contents($this->tempDir . '/sub/deep/b.txt', 'y');
        chmod($this->tempDir, 0755);
        chmod($this->tempDir . '/a.txt', 0640);
        chmod($this->tempDir . '/sub/deep/b.txt', 0600);

        $this->assertTrue($this->chmodRecursive($this->tempDir, 0022, 0));

        // The directory itself and every entry below it gained go+w...
        $this->assertSame(0777, $this->perms($this->tempDir));
        $this->assertSame(0662, $this->perms($this->tempDir . '/a.txt'));
        $this->assertSame(0622, $this->perms($this->tempDir . '/sub/deep/b.txt'));
    }

    public function testRemovesWriteBitsRecursively(): void
    {
        mkdir($this->tempDir . '/sub', 0777, true);
        file_put_contents($this->tempDir . '/a.txt', 'x');
        chmod($this->tempDir . '/a.txt', 0666);

        $this->assertTrue($this->chmodRecursive($this->tempDir, 0, 0022));

        $this->assertSame(0755, $this->perms($this->tempDir));
        $this->assertSame(0755, $this->perms($this->tempDir . '/sub'));
        $this->assertSame(0644, $this->perms($this->tempDir . '/a.txt'));
    }

    public function testPreservesSetuidSetgidSticky(): void
    {
        chmod($this->tempDir, 07755);

        $this->assertTrue($this->chmodRecursive($this->tempDir, 0022, 0));

        // go+w added, but the setuid/setgid/sticky bits are untouched.
        $this->assertSame(07777, $this->perms($this->tempDir));
    }

    public function testDoesNotFollowSymlinks(): void
    {
        $outside = $this->tempDir . '/outside.txt';
        file_put_contents($outside, 'z');
        chmod($outside, 0600);

        $treeDir = $this->tempDir . '/tree';
        mkdir($treeDir, 0755, true);
        symlink($outside, $treeDir . '/link');

        $this->assertTrue($this->chmodRecursive($treeDir, 0022, 0));

        // The symlink target, reached only through the link, is untouched.
        $this->assertSame(0600, $this->perms($outside));
    }

    public function testRemovesOwnerTraverseBitsPostOrder(): void
    {
        if (posix_getuid() === 0) {
            $this->markTestSkipped('root bypasses directory read/traverse permissions');
        }

        mkdir($this->tempDir . '/sub/deep', 0700, true);
        file_put_contents($this->tempDir . '/sub/deep/b.txt', 'y');
        chmod($this->tempDir . '/sub/deep/b.txt', 0644);

        // Removing owner read+traverse (0500) from every directory would, with a
        // naive pre-order walk, lock the recursion out of the subtree before it
        // reaches the leaf. The post-order handling must still descend first.
        $this->assertTrue($this->chmodRecursive($this->tempDir, 0, 0500));

        // Re-grant traverse on the directories only (leaving the leaf file
        // untouched) so we can inspect it; a pre-order walk would never have
        // reached the file, so its bits would still be the original 0644.
        chmod($this->tempDir, 0700);
        chmod($this->tempDir . '/sub', 0700);
        chmod($this->tempDir . '/sub/deep', 0700);
        // 0644 with owner read+execute (0500) removed -> owner keeps write only.
        $this->assertSame(0244, $this->perms($this->tempDir . '/sub/deep/b.txt'));
    }

    public function testReportsFailureForUnreadableSubdirectory(): void
    {
        if (posix_getuid() === 0) {
            $this->markTestSkipped('root bypasses directory read permissions');
        }

        $unreadable = $this->tempDir . '/unreadable';
        mkdir($unreadable, 0755, true);
        file_put_contents($unreadable . '/f', 'x');
        chmod($unreadable, 0000);

        // A directory whose contents cannot be listed must be reported as a
        // failure (like `chmod -R`), not silently succeed. scandir() raises an
        // expected warning here, which we suppress.
        $this->assertFalse(@$this->chmodRecursive($this->tempDir, 0, 0022));

        // Restore access so tearDown can clean up.
        chmod($unreadable, 0755);
    }
}
