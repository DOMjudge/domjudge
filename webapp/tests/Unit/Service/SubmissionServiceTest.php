<?php declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\SubmissionService;
use Generator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class SubmissionServiceTest extends KernelTestCase
{
    /**
     * @dataProvider provideRunResults
     */
    public function testGetFinalResult(array $runresults, array $resultsPrio, ?string $result): void
    {
        self::assertSame($result, SubmissionService::getFinalResult($runresults, $resultsPrio));
    }

    public function provideRunResults(): Generator
    {
        $defaultPrios = [
            'memory-limit' => 99,
            'output-limit' => 99,
            'run-error' => 99,
            'timelimit' => 99,
            'wrong-answer' => 99,
            'no-output' => 99,
            'correct' => 1,
        ];
        yield [['correct'], $defaultPrios, 'correct'];
        yield [['wrong-answer'], $defaultPrios, 'wrong-answer'];
        yield [['correct', 'wrong-answer', null], $defaultPrios, 'wrong-answer'];
        yield [['correct', null, 'wrong-answer'], $defaultPrios, null];
        yield [['correct', null, 'correct'], $defaultPrios, null];
        yield [['correct', 'wrong-answer', 'timelimit'], $defaultPrios, 'wrong-answer'];

        $modifiedPrios = $defaultPrios;
        $modifiedPrios['wrong-answer'] = 70;
        yield [['correct', 'wrong-answer', null], $modifiedPrios, null];
        yield [['correct', null, 'wrong-answer'], $modifiedPrios, null];
        yield [['correct', null, 'correct'], $modifiedPrios, null];
        yield [['correct', 'wrong-answer', 'timelimit'], $modifiedPrios, 'timelimit'];
        yield [['correct', 'output-limit', 'timelimit'], $modifiedPrios, 'output-limit'];
        yield [['correct', 'output-limit', null], $modifiedPrios, 'output-limit'];
        yield [['correct', null, 'output-limit'], $modifiedPrios, null];
    }
}
