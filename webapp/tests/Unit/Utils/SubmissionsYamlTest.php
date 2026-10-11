<?php declare(strict_types=1);

namespace App\Tests\Unit\Utils;

use App\Utils\SubmissionsYaml;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SubmissionsYamlTest extends TestCase
{
    /**
     * @param string[]|null $expected
     */
    #[DataProvider('provideDefaultResults')]
    public function testDefaultDirectories(string $path, ?array $expected): void
    {
        $messages = [];
        $config = new SubmissionsYaml(null, $messages);
        self::assertEqualsCanonicalizing($expected, $config->forSubmission($path, $messages)['results']);
        self::assertSame([], $messages);
    }

    public static function provideDefaultResults(): Generator
    {
        yield ['accepted/sol.py', ['CORRECT']];
        yield ['wrong_answer/sol.py', ['WRONG-ANSWER']];
        yield ['time_limit_exceeded/sol.py', ['TIMELIMIT']];
        yield ['run_time_error/sol.py', ['RUN-ERROR']];
        yield ['brute_force/sol.py', ['RUN-ERROR', 'TIMELIMIT']];
        yield ['rejected/sol.py', ['WRONG-ANSWER', 'TIMELIMIT', 'RUN-ERROR']];
        yield ['accepted/multifile', ['CORRECT']];
        yield ['mixed/sol.py', null];
        yield ['accepted_but_not_really/sol.py', null];
    }

    /**
     * @param string[]|null $expected
     */
    #[DataProvider('provideConfiguredResults')]
    public function testConfiguredResults(string $yaml, string $path, ?array $expected): void
    {
        $messages = [];
        $config = new SubmissionsYaml($yaml, $messages);
        self::assertEqualsCanonicalizing($expected, $config->forSubmission($path, $messages)['results']);
        self::assertSame([], $messages);
    }

    public static function provideConfiguredResults(): Generator
    {
        yield 'custom directory' => ["mixed:\n  permitted: [AC, WA]\n", 'mixed/sol.py', ['CORRECT', 'WRONG-ANSWER']];
        yield 'custom directory with required' => ["mixed:\n  permitted: [AC, WA, TLE]\n  required: [WA, TLE]\n",
            'mixed/sol.py', ['WRONG-ANSWER', 'TIMELIMIT']];
        yield 'directory glob' => ["mixed/*:\n  permitted: [AC, WA]\n", 'mixed/sol.py', ['CORRECT', 'WRONG-ANSWER']];
        yield 'single file' => ["mixed/sol.py:\n  required: [TLE]\n", 'mixed/sol.py', ['WRONG-ANSWER', 'TIMELIMIT', 'RUN-ERROR']];
        yield 'other file' => ["mixed/other.py:\n  required: [TLE]\n", 'mixed/sol.py', null];
        yield 'brace expansion' => ["mixed/{a,sol}.py:\n  permitted: [AC]\n", 'mixed/sol.py', ['CORRECT']];
        yield 'nested brace expansion' => ["'{x,mixed/{a,sol}}.py':\n  permitted: [AC]\n", 'mixed/sol.py', ['CORRECT']];
        yield 'star does not cross directories' => ["'*.py':\n  permitted: [AC]\n", 'mixed/sol.py', null];
        yield 'star within name' => ["'*/sol.*':\n  permitted: [AC]\n", 'mixed/sol.py', ['CORRECT']];
        yield 'override default' => ["time_limit_exceeded:\n  permitted: [AC, WA, TLE]\n",
            'time_limit_exceeded/sol.py', ['WRONG-ANSWER', 'TIMELIMIT']];
        yield 'glob adds to default' => ["time_limit_exceeded/*:\n  permitted: [AC, WA, TLE]\n",
            'time_limit_exceeded/sol.py', ['TIMELIMIT']];
        yield 'rules intersect' => ["rejected/*:\n  permitted: [AC, WA]\n", 'rejected/sol.py', ['WRONG-ANSWER']];
        yield 'upper time limit' => ["mixed:\n  use_for_time_limit: upper\n", 'mixed/sol.py',
            ['WRONG-ANSWER', 'TIMELIMIT', 'RUN-ERROR']];
        yield 'lower time limit' => ["mixed:\n  use_for_time_limit: lower\n", 'mixed/sol.py',
            ['CORRECT', 'WRONG-ANSWER', 'RUN-ERROR']];
        yield 'metadata only' => ["mixed:\n  authors: Someone\n  model_solution: true\n", 'mixed/sol.py', null];
        yield 'empty file' => ['', 'accepted/sol.py', ['CORRECT']];
    }

    public function testImpossibleRequirements(): void
    {
        $messages = [];
        $config = new SubmissionsYaml("accepted/*:\n  required: [WA]\n", $messages);
        self::assertNull($config->forSubmission('accepted/sol.py', $messages)['results']);
        self::assertCount(1, $messages['danger']);
    }

    public function testOtherSettings(): void
    {
        $messages = [];
        $yaml = <<<YAML
accepted/sol.py:
  language: python3
  entrypoint: sol
  score: 42
YAML;
        $config = new SubmissionsYaml($yaml, $messages);
        $meta = $config->forSubmission('accepted/sol.py', $messages);
        self::assertSame('python3', $meta['language']);
        self::assertSame('sol', $meta['entrypoint']);
        self::assertSame(42.0, $meta['score']);
        self::assertSame([], $messages);
    }

    public function testConflictingSettings(): void
    {
        $messages = [];
        $config = new SubmissionsYaml("accepted:\n  language: python3\naccepted/*:\n  language: pypy3\n", $messages);
        self::assertSame('python3', $config->forSubmission('accepted/sol.py', $messages)['language']);
        self::assertCount(1, $messages['warning']);
    }

    #[DataProvider('provideUnsupported')]
    public function testUnsupported(string $yaml, string $type, string $expectedMessage): void
    {
        $messages = [];
        $config = new SubmissionsYaml($yaml, $messages);
        $config->forSubmission('accepted/sol.py', $messages);
        self::assertCount(1, $messages[$type]);
        self::assertStringContainsString($expectedMessage, $messages[$type][0]);
    }

    public static function provideUnsupported(): Generator
    {
        yield 'test data group' => ["accepted:\n  secret/group1:\n    permitted: [AC]\n", 'warning', 'test data group'];
        yield 'score range' => ["accepted:\n  score: [10, 20]\n", 'warning', 'single number'];
        yield 'unknown verdict' => ["accepted:\n  permitted: [MLE]\n", 'danger', 'MLE'];
        yield 'invalid yaml' => ["accepted: [\n", 'danger', 'submissions.yaml'];
        yield 'not a map' => ["- accepted\n", 'danger', 'not a map'];
    }

    /**
     * @param string[] $expected
     */
    #[DataProvider('provideBraces')]
    public function testExpandBraces(string $pattern, array $expected): void
    {
        self::assertSame($expected, SubmissionsYaml::expandBraces($pattern));
    }

    public static function provideBraces(): Generator
    {
        yield ['a.py', ['a.py']];
        yield ['{a,b}.py', ['a.py', 'b.py']];
        yield ['x/{a,b}.{py,cpp}', ['x/a.py', 'x/a.cpp', 'x/b.py', 'x/b.cpp']];
        yield ['{a,{b,c}d}', ['a', 'bd', 'cd']];
        yield ['{a,b', ['{a,b']];
    }
}
