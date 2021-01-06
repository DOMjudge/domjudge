<?php declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\SubmissionService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;


class SubmissionServiceTest extends KernelTestCase
{
    /**
     * @dataProvider provideRunResults
     */
    public function testGetFinalResult(array $runresults, array $resultsPrio, ?string $result) {
       $this->assertSame($result, SubmissionService::getFinalResult($runresults, $resultsPrio));
    }

    public function provideRunResults() : \Generator {
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
        yield [['correct', 'wrong-answer', NULL], $defaultPrios, 'wrong-answer'];
        yield [['correct', NULL, 'wrong-answer'], $defaultPrios, NULL];
        yield [['correct', NULL, 'correct'], $defaultPrios, NULL];
        yield [['correct', 'wrong-answer', 'timelimit'], $defaultPrios, 'wrong-answer'];

        $modifiedPrios = $defaultPrios;
        $modifiedPrios['wrong-answer'] = 70;
        yield [['correct', 'wrong-answer', NULL], $modifiedPrios, NULL];
        yield [['correct', NULL, 'wrong-answer'], $defaultPrios, NULL];
        yield [['correct', NULL, 'correct'], $modifiedPrios, NULL];
        yield [['correct', 'wrong-answer', 'timelimit'], $modifiedPrios, 'timelimit'];
        yield [['correct', 'output-limit', 'timelimit'], $modifiedPrios, 'output-limit'];
        yield [['correct', 'output-limit', NULL], $modifiedPrios, 'output-limit'];
        yield [['correct', NULL, 'output-limit'], $modifiedPrios, NULL];
    }
}
