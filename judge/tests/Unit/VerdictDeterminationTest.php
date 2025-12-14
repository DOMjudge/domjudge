<?php declare(strict_types=1);

namespace DOMjudge\Tests\Unit;

use DOMjudge\JudgeDaemon;
use DOMjudge\Verdict;
use DOMjudge\VerdictInput;
use DOMjudge\ProgramMetadata;
use DOMjudge\CompareMetadata;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

class VerdictDeterminationTest extends TestCase
{
    private ?JudgeDaemon $daemon = null;

    protected function setUp(): void
    {
        $reflection = new ReflectionClass(JudgeDaemon::class);
        $this->daemon = $reflection->newInstanceWithoutConstructor();
    }

    private function callGetVerdictMessage(Verdict $verdict, VerdictInput $input, int $filelimit = 100): ?string
    {
        $reflection = new ReflectionClass(JudgeDaemon::class);
        $method = $reflection->getMethod('getVerdictMessage');
        $method->setAccessible(true);
        return $method->invoke($this->daemon, $verdict, $input->programMeta, $input->compareMeta, $input->combinedRunCompare, $filelimit);
    }

    private function makeInput(
        array $programMeta = [],
        array $compareMeta = [],
        int $compareExitcode = 42,
        bool $combinedRunCompare = false,
        int $programOutSize = 100,
        bool $compareTimedOut = false,
    ): VerdictInput {
        $defaultProgramMeta = [
            'cpu-time' => '0.1',
            'wall-time' => '0.2',
            'memory-bytes' => '1048576',
            'exitcode' => '0',
            'time-result' => 'pass',
            'stdout-bytes' => '0',
            'stderr-bytes' => '0',
            'output-truncated' => '',
        ];
        $defaultCompareMeta = [
            'exitcode' => (string)$compareExitcode,
            'validator-exited-first' => 'false',
        ];

        $programMetadata = ProgramMetadata::fromArray(array_merge($defaultProgramMeta, $programMeta));
        $compareMetadata = CompareMetadata::fromArray(array_merge($defaultCompareMeta, $compareMeta));

        return new VerdictInput(
            programMeta: $programMetadata,
            compareMeta: $compareMetadata,
            compareExitcode: $compareExitcode,
            combinedRunCompare: $combinedRunCompare,
            programOutSize: $programOutSize,
            compareTimedOut: $compareTimedOut,
        );
    }

    public function testCorrectVerdict(): void
    {
        $input = $this->makeInput(compareExitcode: 42);
        $verdict = $this->daemon->determineVerdict($input);
        $this->assertEquals(Verdict::CORRECT, $verdict);
        $this->assertEquals('Correct!', $this->callGetVerdictMessage($verdict, $input));
    }

    public function testWrongAnswerVerdict(): void
    {
        $input = $this->makeInput(compareExitcode: 43);
        $verdict = $this->daemon->determineVerdict($input);
        $this->assertEquals(Verdict::WRONG_ANSWER, $verdict);
        $this->assertEquals('Wrong answer!', $this->callGetVerdictMessage($verdict, $input));
    }

    public function testTimelimitVerdict(): void
    {
        $input = $this->makeInput(
            programMeta: ['time-result' => 'timelimit (hard)', 'exitcode' => '137'],
            compareExitcode: 43,
        );
        $verdict = $this->daemon->determineVerdict($input);
        $this->assertEquals(Verdict::TIMELIMIT, $verdict);
        $this->assertEquals('Timelimit exceeded.', $this->callGetVerdictMessage($verdict, $input));
    }

    public function testSoftTimelimitVerdict(): void
    {
        $input = $this->makeInput(
            programMeta: ['time-result' => 'timelimit (soft)', 'exitcode' => '137'],
            compareExitcode: 43,
        );
        $verdict = $this->daemon->determineVerdict($input);
        $this->assertEquals(Verdict::TIMELIMIT, $verdict);
        $this->assertEquals('Timelimit exceeded.', $this->callGetVerdictMessage($verdict, $input));
    }

    public function testRunErrorVerdict(): void
    {
        $input = $this->makeInput(
            programMeta: ['exitcode' => '1'],
            compareExitcode: 43,
        );
        $verdict = $this->daemon->determineVerdict($input);
        $this->assertEquals(Verdict::RUN_ERROR, $verdict);
        $this->assertEquals('Non-zero exitcode 1', $this->callGetVerdictMessage($verdict, $input));
    }

    public function testOutputLimitVerdict(): void
    {
        $input = $this->makeInput(
            programMeta: ['output-truncated' => 'stdout', 'stdout-bytes' => '1024'],
            compareExitcode: 43,
        );
        $verdict = $this->daemon->determineVerdict($input);
        $this->assertEquals(Verdict::OUTPUT_LIMIT, $verdict);
        $this->assertEquals(
            'Output limit exceeded: 1024 bytes more than the limit of 102400 bytes',
            $this->callGetVerdictMessage($verdict, $input, 100)
        );
    }

    public function testNoOutputVerdict(): void
    {
        $input = $this->makeInput(
            compareExitcode: 43,
            programOutSize: 0,
        );
        $verdict = $this->daemon->determineVerdict($input);
        $this->assertEquals(Verdict::NO_OUTPUT, $verdict);
        $this->assertEquals('Program produced no output.', $this->callGetVerdictMessage($verdict, $input));
    }

    public function testCompareErrorInvalidExitCode(): void
    {
        $input = $this->makeInput(compareExitcode: 1);
        $verdict = $this->daemon->determineVerdict($input);
        $this->assertEquals(Verdict::COMPARE_ERROR, $verdict);
        $this->assertNull($this->callGetVerdictMessage($verdict, $input));
    }

    public function testCompareErrorTimeout(): void
    {
        $input = $this->makeInput(compareTimedOut: true);
        $verdict = $this->daemon->determineVerdict($input);
        $this->assertEquals(Verdict::COMPARE_ERROR, $verdict);
        $this->assertNull($this->callGetVerdictMessage($verdict, $input));
    }

    public function testInteractiveValidatorExitsFirstOverridesTimelimit(): void
    {
        $input = $this->makeInput(
            programMeta: ['time-result' => 'timelimit (hard)', 'exitcode' => '137'],
            compareMeta: ['validator-exited-first' => 'true', 'exitcode' => '43'],
            compareExitcode: 43,
            combinedRunCompare: true,
        );
        $verdict = $this->daemon->determineVerdict($input);
        $this->assertEquals(Verdict::WRONG_ANSWER, $verdict);
        $this->assertEquals(
            'Timelimit exceeded, but validator exited first with WA. Wrong answer!',
            $this->callGetVerdictMessage($verdict, $input)
        );
    }

    public function testInteractiveValidatorExitsFirstOverridesRunError(): void
    {
        $input = $this->makeInput(
            programMeta: ['exitcode' => '1'],
            compareMeta: ['validator-exited-first' => 'true', 'exitcode' => '43'],
            compareExitcode: 43,
            combinedRunCompare: true,
        );
        $verdict = $this->daemon->determineVerdict($input);
        $this->assertEquals(Verdict::WRONG_ANSWER, $verdict);
        $this->assertEquals(
            'Non-zero exitcode 1, but validator exited first with WA. Wrong answer!',
            $this->callGetVerdictMessage($verdict, $input)
        );
    }

    public function testInteractiveValidatorExitsFirstDoesNotOverrideWhenCorrect(): void
    {
        $input = $this->makeInput(
            programMeta: ['time-result' => 'timelimit (hard)', 'exitcode' => '137'],
            compareMeta: ['validator-exited-first' => 'true', 'exitcode' => '42'],
            compareExitcode: 42,
            combinedRunCompare: true,
        );
        $verdict = $this->daemon->determineVerdict($input);
        $this->assertEquals(Verdict::TIMELIMIT, $verdict);
        $this->assertEquals('Timelimit exceeded.', $this->callGetVerdictMessage($verdict, $input));
    }

    public function testNonInteractiveIgnoresValidatorExitedFirst(): void
    {
        $input = $this->makeInput(
            programMeta: ['time-result' => 'timelimit (hard)', 'exitcode' => '137'],
            compareMeta: ['validator-exited-first' => 'true', 'exitcode' => '43'],
            compareExitcode: 43,
            combinedRunCompare: false,
        );
        $verdict = $this->daemon->determineVerdict($input);
        $this->assertEquals(Verdict::TIMELIMIT, $verdict);
        $this->assertEquals('Timelimit exceeded.', $this->callGetVerdictMessage($verdict, $input));
    }

    public function testCompareTimeoutPriority(): void
    {
        $input = $this->makeInput(
            programMeta: ['exitcode' => '0'],
            compareExitcode: 42,
            compareTimedOut: true,
        );
        $verdict = $this->daemon->determineVerdict($input);
        $this->assertEquals(Verdict::COMPARE_ERROR, $verdict);
        $this->assertNull($this->callGetVerdictMessage($verdict, $input));
    }

    public function testTimelimitPriorityOverRunError(): void
    {
        $input = $this->makeInput(
            programMeta: ['time-result' => 'timelimit', 'exitcode' => '139'],
            compareExitcode: 43,
        );
        $verdict = $this->daemon->determineVerdict($input);
        $this->assertEquals(Verdict::TIMELIMIT, $verdict);
        $this->assertEquals('Timelimit exceeded.', $this->callGetVerdictMessage($verdict, $input));
    }

    public function testRunErrorPriorityOverOutputLimit(): void
    {
        $input = $this->makeInput(
            programMeta: ['exitcode' => '1', 'output-truncated' => 'stdout'],
            compareExitcode: 43,
        );
        $verdict = $this->daemon->determineVerdict($input);
        $this->assertEquals(Verdict::RUN_ERROR, $verdict);
        $this->assertEquals('Non-zero exitcode 1', $this->callGetVerdictMessage($verdict, $input));
    }

    public function testStderrOnlyTruncationDoesNotTriggerOutputLimit(): void
    {
        $input = $this->makeInput(
            programMeta: ['output-truncated' => 'stderr', 'stderr-bytes' => '67108864'],
            compareExitcode: 43,
        );
        $verdict = $this->daemon->determineVerdict($input);
        $this->assertEquals(Verdict::WRONG_ANSWER, $verdict);
        $this->assertEquals('Wrong answer!', $this->callGetVerdictMessage($verdict, $input));
    }

    public function testInteractiveValidatorExitedFirstFalse(): void
    {
        $input = $this->makeInput(
            programMeta: ['time-result' => 'timelimit (soft)', 'exitcode' => '137'],
            compareMeta: ['validator-exited-first' => 'false', 'exitcode' => '43'],
            compareExitcode: 43,
            combinedRunCompare: true,
        );
        $verdict = $this->daemon->determineVerdict($input);
        $this->assertEquals(Verdict::TIMELIMIT, $verdict);
        $this->assertEquals('Timelimit exceeded.', $this->callGetVerdictMessage($verdict, $input));
    }

    public function testNoOutputInteractiveMode(): void
    {
        $input = $this->makeInput(
            compareExitcode: 43,
            combinedRunCompare: true,
            programOutSize: 0,
        );
        $verdict = $this->daemon->determineVerdict($input);
        $this->assertEquals(Verdict::WRONG_ANSWER, $verdict);
        $this->assertEquals('Wrong answer!', $this->callGetVerdictMessage($verdict, $input));
    }

    public function testInteractiveSegfaultOverride(): void
    {
        $input = $this->makeInput(
            programMeta: ['exitcode' => '139'],
            compareMeta: ['validator-exited-first' => 'true', 'exitcode' => '43'],
            compareExitcode: 43,
            combinedRunCompare: true,
        );
        $verdict = $this->daemon->determineVerdict($input);
        $this->assertEquals(Verdict::WRONG_ANSWER, $verdict);
        $this->assertEquals(
            'Non-zero exitcode 139, but validator exited first with WA. Wrong answer!',
            $this->callGetVerdictMessage($verdict, $input)
        );
    }

    public function testInteractiveNoOverrideWhenProgramSucceeded(): void
    {
        $input = $this->makeInput(
            programMeta: ['exitcode' => '0'],
            compareMeta: ['validator-exited-first' => 'true', 'exitcode' => '43'],
            compareExitcode: 43,
            combinedRunCompare: true,
        );
        $verdict = $this->daemon->determineVerdict($input);
        $this->assertEquals(Verdict::WRONG_ANSWER, $verdict);
        $this->assertEquals('Wrong answer!', $this->callGetVerdictMessage($verdict, $input));
    }
}
