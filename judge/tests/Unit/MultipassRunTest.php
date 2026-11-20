<?php declare(strict_types=1);

namespace DOMjudge\Tests\Unit;

use DOMjudge\JudgeDaemon;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

class MultipassRunTest extends TestCase
{
    private JudgeDaemon $daemon;

    protected function setUp(): void
    {
        $this->daemon = (new ReflectionClass(JudgeDaemon::class))->newInstanceWithoutConstructor();
    }

    /**
     * @param array<string, string> $overrides
     * @return array<string, string>
     */
    private function makeJudgingRun(array $overrides = []): array
    {
        return array_merge([
            'runresult' => 'correct',
            'start_time' => '1000.0',
            'end_time' => '1001.5',
            'runtime' => '0.100',
            'output_run' => 'out',
            'output_error' => '',
            'output_system' => '',
            'metadata' => 'meta',
            'output_diff' => 'diff',
            'hostname' => 'judgehost-1',
            'testcasedir' => '/tmp/tc',
            'compare_metadata' => 'cmp',
        ], $overrides);
    }

    public function testBuildPassDataUsesPassStartTimeAndOptionalInput(): void
    {
        $buildPassData = new ReflectionMethod(JudgeDaemon::class, 'buildPassData');
        $run = $this->makeJudgingRun(['start_time' => '1000.0', 'end_time' => '1005.5']);

        $pass1 = $buildPassData->invoke($this->daemon, 1, 1005.0, $run);
        $this->assertSame(1, $pass1['pass']);
        $this->assertSame('1005', $pass1['start_time']);
        $this->assertSame('1005.5', $pass1['end_time']);
        $this->assertArrayNotHasKey('input', $pass1);
        $this->assertArrayNotHasKey('hostname', $pass1);

        $pass2 = $buildPassData->invoke($this->daemon, 2, 1006.25, $run, 'encoded-nextpass');
        $this->assertSame(2, $pass2['pass']);
        $this->assertSame('1006.25', $pass2['start_time']);
        $this->assertSame('encoded-nextpass', $pass2['input']);
    }

    public function testFinalizeMultipassRunReportsMaxRuntimeAcrossPasses(): void
    {
        $buildPassData = new ReflectionMethod(JudgeDaemon::class, 'buildPassData');
        $finalize = new ReflectionMethod(JudgeDaemon::class, 'finalizeMultipassRun');

        $pass1 = $buildPassData->invoke($this->daemon, 1, 1001.0, $this->makeJudgingRun(['runtime' => '0.950']));
        $lastRun = $this->makeJudgingRun(['runresult' => 'wrong-answer', 'runtime' => '0.012']);
        $pass2 = $buildPassData->invoke($this->daemon, 2, 1002.0, $lastRun, 'next-in');

        $final = $finalize->invoke($this->daemon, $lastRun, [$pass1, $pass2]);

        $this->assertSame('0.950', $final['runtime']);
        $this->assertSame('2', $final['pass']);
        $this->assertSame('1000.0', $final['start_time']);
        $decoded = json_decode($final['passes'], true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('0.950', $decoded[0]['runtime']);
        $this->assertSame('0.012', $decoded[1]['runtime']);
    }
}
