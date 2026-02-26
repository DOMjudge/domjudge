<?php declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Contest;
use App\Entity\Judging;
use App\Entity\JudgingRun;
use App\Entity\Problem;
use App\Entity\Testcase;
use App\Entity\TestcaseAggregationType;
use App\Entity\TestcaseGroup;
use App\Service\SubmissionService;
use Generator;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class SubmissionServiceTest extends KernelTestCase
{
    private int $nextGroupId = 1;

    protected function setUp(): void
    {
        parent::setUp();
        $this->nextGroupId = 1;
    }

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

    // =========================================================================
    // Tests for maybeSetScoringResult
    // =========================================================================

    /**
     * Test scoring with a simple group that has an accept score.
     * All testcases correct -> accept score is returned.
     */
    public function testMaybeSetScoringResultWithAcceptScoreAllCorrect(): void
    {
        $group = $this->createTestcaseGroup('group1', TestcaseAggregationType::SUM, acceptScore: '25');
        $problem = new Problem();
        $problem->setTimelimit(1)->setName('test');
        $testcase = $this->createTestcase($problem, $group, 1);

        $judging = $this->createJudging();
        $this->addJudgingRun($judging, $testcase, 'correct', null);

        [$score, $result] = SubmissionService::maybeSetScoringResult($group, $judging);

        self::assertEquals('25.000000000', $score);
        self::assertEquals('correct', $result);
    }

    /**
     * Test scoring with accept score when a testcase is wrong.
     * Any wrong testcase -> score is 0.
     */
    public function testMaybeSetScoringResultWithAcceptScoreWrongAnswer(): void
    {
        $group = $this->createTestcaseGroup('group1', TestcaseAggregationType::SUM, acceptScore: '25');
        $problem = new Problem();
        $problem->setTimelimit(1)->setName('test');
        $testcase = $this->createTestcase($problem, $group, 1);

        $judging = $this->createJudging();
        $this->addJudgingRun($judging, $testcase, 'wrong-answer', null);

        [$score, $result] = SubmissionService::maybeSetScoringResult($group, $judging);

        self::assertEquals('0.000000000', $score);
        self::assertEquals('wrong-answer', $result);
    }

    /**
     * Test that score.txt takes precedence over accept_score.
     */
    public function testMaybeSetScoringResultScoreTxtOverridesAcceptScore(): void
    {
        $group = $this->createTestcaseGroup('group1', TestcaseAggregationType::SUM, acceptScore: '25');
        $problem = new Problem();
        $problem->setTimelimit(1)->setName('test');
        $testcase = $this->createTestcase($problem, $group, 1);

        $judging = $this->createJudging();
        // score.txt produced a score of 15, should override accept_score of 25
        $this->addJudgingRun($judging, $testcase, 'correct', '15');

        [$score, $result] = SubmissionService::maybeSetScoringResult($group, $judging);

        self::assertEquals('15.000000000', $score);
        self::assertEquals('correct', $result);
    }

    /**
     * Test scoring with individual run scores using SUM aggregation.
     */
    public function testMaybeSetScoringResultSumAggregation(): void
    {
        $group = $this->createTestcaseGroup('group1', TestcaseAggregationType::SUM);
        $problem = new Problem();
        $problem->setTimelimit(1)->setName('test');
        $tc1 = $this->createTestcase($problem, $group, 1);
        $tc2 = $this->createTestcase($problem, $group, 2);
        $tc3 = $this->createTestcase($problem, $group, 3);

        $judging = $this->createJudging();
        $this->addJudgingRun($judging, $tc1, 'correct', '10');
        $this->addJudgingRun($judging, $tc2, 'correct', '20');
        $this->addJudgingRun($judging, $tc3, 'correct', '30');

        [$score, $result] = SubmissionService::maybeSetScoringResult($group, $judging);

        self::assertEquals('60.000000000', $score);
        self::assertEquals('correct', $result);
    }

    /**
     * Test scoring with MIN aggregation.
     */
    public function testMaybeSetScoringResultMinAggregation(): void
    {
        $group = $this->createTestcaseGroup('group1', TestcaseAggregationType::MIN);
        $problem = new Problem();
        $problem->setTimelimit(1)->setName('test');
        $tc1 = $this->createTestcase($problem, $group, 1);
        $tc2 = $this->createTestcase($problem, $group, 2);
        $tc3 = $this->createTestcase($problem, $group, 3);

        $judging = $this->createJudging();
        $this->addJudgingRun($judging, $tc1, 'correct', '10');
        $this->addJudgingRun($judging, $tc2, 'correct', '5');
        $this->addJudgingRun($judging, $tc3, 'correct', '30');

        [$score, $result] = SubmissionService::maybeSetScoringResult($group, $judging);

        self::assertEquals('5.000000000', $score);
        self::assertEquals('correct', $result);
    }

    /**
     * Test scoring with MAX aggregation.
     */
    public function testMaybeSetScoringResultMaxAggregation(): void
    {
        $group = $this->createTestcaseGroup('group1', TestcaseAggregationType::MAX);
        $problem = new Problem();
        $problem->setTimelimit(1)->setName('test');
        $tc1 = $this->createTestcase($problem, $group, 1);
        $tc2 = $this->createTestcase($problem, $group, 2);
        $tc3 = $this->createTestcase($problem, $group, 3);

        $judging = $this->createJudging();
        $this->addJudgingRun($judging, $tc1, 'correct', '10');
        $this->addJudgingRun($judging, $tc2, 'correct', '50');
        $this->addJudgingRun($judging, $tc3, 'correct', '30');

        [$score, $result] = SubmissionService::maybeSetScoringResult($group, $judging);

        self::assertEquals('50.000000000', $score);
        self::assertEquals('correct', $result);
    }

    /**
     * Test scoring with AVG aggregation.
     */
    public function testMaybeSetScoringResultAvgAggregation(): void
    {
        $group = $this->createTestcaseGroup('group1', TestcaseAggregationType::AVG);
        $problem = new Problem();
        $problem->setTimelimit(1)->setName('test');
        $tc1 = $this->createTestcase($problem, $group, 1);
        $tc2 = $this->createTestcase($problem, $group, 2);
        $tc3 = $this->createTestcase($problem, $group, 3);

        $judging = $this->createJudging();
        $this->addJudgingRun($judging, $tc1, 'correct', '10');
        $this->addJudgingRun($judging, $tc2, 'correct', '20');
        $this->addJudgingRun($judging, $tc3, 'correct', '30');

        [$score, $result] = SubmissionService::maybeSetScoringResult($group, $judging);

        // Average of 10, 20, 30 = 60/3 = 20
        self::assertEquals('20.000000000', $score);
        self::assertEquals('correct', $result);
    }

    /**
     * Test that pending results (null) return null score and result.
     */
    public function testMaybeSetScoringResultPendingResults(): void
    {
        $group = $this->createTestcaseGroup('group1', TestcaseAggregationType::SUM);
        $problem = new Problem();
        $problem->setTimelimit(1)->setName('test');
        $tc1 = $this->createTestcase($problem, $group, 1);
        $tc2 = $this->createTestcase($problem, $group, 2);

        $judging = $this->createJudging();
        $this->addJudgingRun($judging, $tc1, 'correct', '10');
        $this->addJudgingRun($judging, $tc2, null, '0'); // Pending

        [$score, $result] = SubmissionService::maybeSetScoringResult($group, $judging);

        self::assertNull($score);
        self::assertNull($result);
    }

    /**
     * Test with onRejectContinue = false, should return early on first wrong answer.
     */
    public function testMaybeSetScoringResultOnRejectStop(): void
    {
        $group = $this->createTestcaseGroup('group1', TestcaseAggregationType::SUM, onRejectContinue: false);
        $problem = new Problem();
        $problem->setTimelimit(1)->setName('test');
        $tc1 = $this->createTestcase($problem, $group, 1);
        $tc2 = $this->createTestcase($problem, $group, 2);

        $judging = $this->createJudging();
        $this->addJudgingRun($judging, $tc1, 'wrong-answer', '0');
        // tc2 has no run yet (would be pending), but since onRejectContinue=false,
        // we should get a result after the first wrong answer
        $this->addJudgingRun($judging, $tc2, null, '0');

        [$score, $result] = SubmissionService::maybeSetScoringResult($group, $judging);

        self::assertEquals('0.000000000', $score);
        self::assertEquals('wrong-answer', $result);
    }

    /**
     * Test with onRejectContinue = true, should continue even with wrong answers.
     */
    public function testMaybeSetScoringResultOnRejectContinue(): void
    {
        $group = $this->createTestcaseGroup('group1', TestcaseAggregationType::SUM, onRejectContinue: true);
        $problem = new Problem();
        $problem->setTimelimit(1)->setName('test');
        $tc1 = $this->createTestcase($problem, $group, 1);
        $tc2 = $this->createTestcase($problem, $group, 2);

        $judging = $this->createJudging();
        $this->addJudgingRun($judging, $tc1, 'wrong-answer', '0');
        $this->addJudgingRun($judging, $tc2, 'correct', '20');

        [$score, $result] = SubmissionService::maybeSetScoringResult($group, $judging);

        // Sum of individual scores (wrong answers contribute their score too)
        self::assertEquals('20.000000000', $score);
        self::assertEquals('wrong-answer', $result);
    }

    /**
     * Test with nested child groups using accept scores.
     */
    public function testMaybeSetScoringResultWithChildGroups(): void
    {
        // Parent group with SUM aggregation
        $parentGroup = $this->createTestcaseGroup('parent', TestcaseAggregationType::SUM);

        // Child group 1 with accept score of 30
        $childGroup1 = $this->createTestcaseGroup('child1', TestcaseAggregationType::SUM, acceptScore: '30');
        $childGroup1->setParent($parentGroup);
        $parentGroup->getChildren()->add($childGroup1);

        // Child group 2 with accept score of 70
        $childGroup2 = $this->createTestcaseGroup('child2', TestcaseAggregationType::SUM, acceptScore: '70');
        $childGroup2->setParent($parentGroup);
        $parentGroup->getChildren()->add($childGroup2);

        $problem = new Problem();
        $problem->setTimelimit(1)->setName('test');

        $tc1 = $this->createTestcase($problem, $childGroup1, 1);
        $tc2 = $this->createTestcase($problem, $childGroup2, 2);

        $judging = $this->createJudging();
        $this->addJudgingRun($judging, $tc1, 'correct', null);
        $this->addJudgingRun($judging, $tc2, 'correct', null);

        [$score, $result] = SubmissionService::maybeSetScoringResult($parentGroup, $judging);

        self::assertEquals('100.000000000', $score);
        self::assertEquals('correct', $result);
    }

    /**
     * Test with nested child groups where one group fails.
     */
    public function testMaybeSetScoringResultWithChildGroupsPartialFailure(): void
    {
        // Parent group with SUM aggregation
        $parentGroup = $this->createTestcaseGroup('parent', TestcaseAggregationType::SUM);

        // Child group 1 with accept score of 30
        $childGroup1 = $this->createTestcaseGroup('child1', TestcaseAggregationType::SUM, acceptScore: '30');
        $childGroup1->setParent($parentGroup);
        $parentGroup->getChildren()->add($childGroup1);

        // Child group 2 with accept score of 70
        $childGroup2 = $this->createTestcaseGroup('child2', TestcaseAggregationType::SUM, acceptScore: '70');
        $childGroup2->setParent($parentGroup);
        $parentGroup->getChildren()->add($childGroup2);

        $problem = new Problem();
        $problem->setTimelimit(1)->setName('test');

        $tc1 = $this->createTestcase($problem, $childGroup1, 1);
        $tc2 = $this->createTestcase($problem, $childGroup2, 2);

        $judging = $this->createJudging();
        $this->addJudgingRun($judging, $tc1, 'correct', null);
        $this->addJudgingRun($judging, $tc2, 'wrong-answer', null); // Group 2 fails

        [$score, $result] = SubmissionService::maybeSetScoringResult($parentGroup, $judging);

        // Only child group 1 contributes (30), child group 2 is wrong so contributes 0
        self::assertEquals('30.000000000', $score);
        self::assertEquals('wrong-answer', $result);
    }

    /**
     * Test getting the full scoring hierarchy.
     */
    public function testGetScoringHierarchy(): void
    {
        // Parent group with SUM aggregation
        $parentGroup = $this->createTestcaseGroup('parent', TestcaseAggregationType::SUM);

        // Child group 1 with accept score of 30
        $childGroup1 = $this->createTestcaseGroup('child1', TestcaseAggregationType::SUM, acceptScore: '30');
        $childGroup1->setParent($parentGroup);
        $parentGroup->getChildren()->add($childGroup1);

        $problem = new Problem();
        $problem->setTimelimit(1)->setName('test');
        $problem->setParentTestcaseGroup($parentGroup);

        $tc1 = $this->createTestcase($problem, $childGroup1, 1);
        $tc1->setOrigInputFilename('test-input');

        $judging = $this->createJudging();
        $this->addJudgingRun($judging, $tc1, 'correct', null);

        $submissionService = $this->createSubmissionService();

        $hierarchy = $submissionService->getScoringHierarchy($problem, $judging);

        self::assertNotNull($hierarchy);
        self::assertEquals('parent', $hierarchy['name']);
        self::assertEquals('30.000000000', $hierarchy['score']);
        self::assertCount(1, $hierarchy['children']);
        self::assertEquals('child1', $hierarchy['children'][0]['name']);
        self::assertEquals('30.000000000', $hierarchy['children'][0]['score']);
        self::assertCount(1, $hierarchy['children'][0]['testcases']);
        self::assertEquals(1, $hierarchy['children'][0]['testcases'][0]['rank']);
        self::assertEquals('test-input', $hierarchy['children'][0]['testcases'][0]['orig_input_filename']);
    }

    /**
     * Test getting the scoring hierarchy for a group with accept_score.
     */
    public function testGetScoringHierarchyWithAcceptScore(): void
    {
        $group = $this->createTestcaseGroup('group1', TestcaseAggregationType::SUM, acceptScore: '20');
        $problem = new Problem();
        $problem->setTimelimit(1)->setName('test');
        $problem->setParentTestcaseGroup($group);

        $tc1 = $this->createTestcase($problem, $group, 1);
        $tc2 = $this->createTestcase($problem, $group, 2);

        $judging = $this->createJudging();
        // No score.txt produced, group has accept_score 20
        $this->addJudgingRun($judging, $tc1, 'correct', null);
        $this->addJudgingRun($judging, $tc2, 'correct', null);

        $submissionService = $this->createSubmissionService();

        $hierarchy = $submissionService->getScoringHierarchy($problem, $judging);

        self::assertNotNull($hierarchy);
        self::assertEquals('20.000000000', $hierarchy['score']);
        self::assertEquals(['20.000000000'], $hierarchy['child_scores']);
        self::assertCount(2, $hierarchy['testcases']);
    }

    // =========================================================================
    // Tests for parseExpectedAnnotation
    // =========================================================================

    /**
     * Test parsing @EXPECTED_RESULTS@ tag with a single result.
     */
    public function testParseExpectedAnnotationWithExpectedResults(): void
    {
        $source = "// @EXPECTED_RESULTS@: CORRECT\nint main() {}";
        $result = SubmissionService::parseExpectedAnnotation($source, []);

        self::assertNotNull($result);
        self::assertNotFalse($result);
        self::assertEquals(['CORRECT'], $result['results']);
        self::assertNull($result['score']);
    }

    /**
     * Test parsing @EXPECTED_RESULTS@ tag with multiple results.
     */
    public function testParseExpectedAnnotationWithMultipleResults(): void
    {
        $source = "// @EXPECTED_RESULTS@: CORRECT, WRONG-ANSWER\nint main() {}";
        $result = SubmissionService::parseExpectedAnnotation($source, []);

        self::assertNotNull($result);
        self::assertNotFalse($result);
        self::assertEquals(['CORRECT', 'WRONG-ANSWER'], $result['results']);
        self::assertNull($result['score']);
    }

    /**
     * Test parsing @EXPECTED_SCORE@ tag with a result name (backwards compatible).
     */
    public function testParseExpectedAnnotationWithExpectedScoreResultName(): void
    {
        $source = "// @EXPECTED_SCORE@: CORRECT\nint main() {}";
        $result = SubmissionService::parseExpectedAnnotation($source, []);

        self::assertNotNull($result);
        self::assertNotFalse($result);
        self::assertEquals(['CORRECT'], $result['results']);
        self::assertNull($result['score']);
    }

    /**
     * Test parsing @EXPECTED_SCORE@ tag with a numeric score.
     */
    public function testParseExpectedAnnotationWithNumericScore(): void
    {
        $source = "// @EXPECTED_SCORE@: 60\nint main() {}";
        $result = SubmissionService::parseExpectedAnnotation($source, []);

        self::assertNotNull($result);
        self::assertNotFalse($result);
        self::assertNull($result['results']);
        self::assertEquals('60', $result['score']);
    }

    /**
     * Test parsing @EXPECTED_SCORE@ tag with a decimal score.
     */
    public function testParseExpectedAnnotationWithDecimalScore(): void
    {
        $source = "// @EXPECTED_SCORE@: 75.5\nint main() {}";
        $result = SubmissionService::parseExpectedAnnotation($source, []);

        self::assertNotNull($result);
        self::assertNotFalse($result);
        self::assertNull($result['results']);
        self::assertEquals('75.5', $result['score']);
    }

    /**
     * Test parsing with no annotation returns null.
     */
    public function testParseExpectedAnnotationNoAnnotation(): void
    {
        $source = "int main() { return 0; }";
        $result = SubmissionService::parseExpectedAnnotation($source, []);

        self::assertNull($result);
    }

    /**
     * Test parsing with duplicate annotations returns false.
     */
    public function testParseExpectedAnnotationDuplicate(): void
    {
        $source = "// @EXPECTED_RESULTS@: CORRECT\n// @EXPECTED_RESULTS@: WRONG-ANSWER\nint main() {}";
        $result = SubmissionService::parseExpectedAnnotation($source, []);

        self::assertFalse($result);
    }

    /**
     * Test parsing with both annotation types.
     */
    public function testParseExpectedAnnotationMixedTags(): void
    {
        $source = "// @EXPECTED_RESULTS@: CORRECT\n// @EXPECTED_SCORE@: 60\nint main() {}";
        $result = SubmissionService::parseExpectedAnnotation($source, []);

        self::assertNotNull($result);
        self::assertNotFalse($result);
        self::assertEquals(['CORRECT'], $result['results']);
        self::assertEquals('60', $result['score']);
    }

    /**
     * Test that results remap is applied.
     */
    public function testParseExpectedAnnotationWithRemap(): void
    {
        $source = "// @EXPECTED_RESULTS@: accepted\nint main() {}";
        $remap = ['accepted' => 'CORRECT'];
        $result = SubmissionService::parseExpectedAnnotation($source, $remap);

        self::assertNotNull($result);
        self::assertNotFalse($result);
        self::assertEquals(['CORRECT'], $result['results']);
        self::assertNull($result['score']);
    }

    public function testGetFinalResultEmptyArray(): void
    {
        $prios = ['correct' => 1, 'wrong-answer' => 99];
        self::assertNull(SubmissionService::getFinalResult([], $prios));
    }

    public function testGetFinalResultAllNull(): void
    {
        $prios = ['correct' => 1, 'wrong-answer' => 99];
        self::assertNull(SubmissionService::getFinalResult([null, null, null], $prios));
    }

    /**
     * Test getFinalResult returns determinate result when max priority is
     * reached even with pending (null) runs after it.
     */
    public function testGetFinalResultMaxPriorityBeforeNull(): void
    {
        $prios = ['correct' => 1, 'wrong-answer' => 99];
        // wrong-answer has max priority (99), so null after it doesn't matter
        self::assertSame('wrong-answer', SubmissionService::getFinalResult(['wrong-answer', null], $prios));
    }

    /**
     * Test getFinalResult returns null when a non-max-priority result
     * is followed by a pending (null) run.
     */
    public function testGetFinalResultLowPriorityBeforeNull(): void
    {
        $prios = ['correct' => 1, 'wrong-answer' => 99];
        // correct has low priority (1), so null after it means indeterminate
        self::assertNull(SubmissionService::getFinalResult(['correct', null], $prios));
    }

    public function testGetFinalResultSingleCorrect(): void
    {
        $prios = ['correct' => 1, 'wrong-answer' => 99];
        self::assertSame('correct', SubmissionService::getFinalResult(['correct'], $prios));
    }

    /**
     * Test getFinalResult picks first when multiple non-null
     * results share the same highest priority level.
     */
    public function testGetFinalResultSamePriorityPicksFirst(): void
    {
        $prios = ['timelimit' => 99, 'wrong-answer' => 99, 'correct' => 1];
        self::assertSame('timelimit', SubmissionService::getFinalResult(['timelimit', 'wrong-answer'], $prios));
        // Reversed run order: wrong-answer is seen first, same priority, so it wins.
        self::assertSame('wrong-answer', SubmissionService::getFinalResult(['wrong-answer', 'timelimit'], $prios));
    }

    /**
     * Test scoring with an empty leaf group (no runs at all).
     * An empty group with any aggregation should return score 0, result correct.
     */
    public function testMaybeSetScoringResultEmptyLeafGroup(): void
    {
        foreach ([
                     TestcaseAggregationType::SUM,
                     TestcaseAggregationType::MIN,
                     TestcaseAggregationType::MAX,
                     TestcaseAggregationType::AVG
                 ] as $aggregationType) {
            $group = $this->createTestcaseGroup('empty', $aggregationType);
            $judging = $this->createJudging();

            [$score, $result] = SubmissionService::maybeSetScoringResult($group, $judging);

            self::assertEquals('0.000000000', $score);
            self::assertEquals('correct', $result);
        }
    }

    /**
     * Test that a parent group with ignoreSample=true skips the data/sample child.
     */
    public function testMaybeSetScoringResultIgnoreSampleChild(): void
    {
        $parentGroup = $this->createTestcaseGroup('parent', TestcaseAggregationType::SUM, ignoreSample: true);

        // Sample child that should be ignored
        $sampleGroup = $this->createTestcaseGroup('data/sample', TestcaseAggregationType::SUM, acceptScore: '10');
        $sampleGroup->setParent($parentGroup);
        $parentGroup->getChildren()->add($sampleGroup);

        // Secret child
        $secretGroup = $this->createTestcaseGroup('data/secret', TestcaseAggregationType::SUM, acceptScore: '90');
        $secretGroup->setParent($parentGroup);
        $parentGroup->getChildren()->add($secretGroup);

        $problem = new Problem();
        $problem->setTimelimit(1)->setName('test');

        $tcSample = $this->createTestcase($problem, $sampleGroup, 1);
        $tcSecret = $this->createTestcase($problem, $secretGroup, 2);

        $judging = $this->createJudging();
        $this->addJudgingRun($judging, $tcSample, 'correct', '10');
        $this->addJudgingRun($judging, $tcSecret, 'correct', '90');

        [$score, $result] = SubmissionService::maybeSetScoringResult($parentGroup, $judging);

        // Sample child ignored; only the real child contributes.
        self::assertEquals('90.000000000', $score);
        self::assertEquals('correct', $result);
    }

    /**
     * Test that ignoreSample=false does NOT skip the data/sample child.
     */
    public function testMaybeSetScoringResultNoIgnoreSample(): void
    {
        $parentGroup = $this->createTestcaseGroup('parent', TestcaseAggregationType::SUM, ignoreSample: false);

        $sampleGroup = $this->createTestcaseGroup('data/sample', TestcaseAggregationType::SUM, acceptScore: '10');
        $sampleGroup->setParent($parentGroup);
        $parentGroup->getChildren()->add($sampleGroup);

        $secretGroup = $this->createTestcaseGroup('data/secret', TestcaseAggregationType::SUM, acceptScore: '90');
        $secretGroup->setParent($parentGroup);
        $parentGroup->getChildren()->add($secretGroup);

        $problem = new Problem();
        $problem->setTimelimit(1)->setName('test');

        $tcSample = $this->createTestcase($problem, $sampleGroup, 1);
        $tcReal = $this->createTestcase($problem, $secretGroup, 2);

        $judging = $this->createJudging();
        $this->addJudgingRun($judging, $tcSample, 'correct', '10');
        $this->addJudgingRun($judging, $tcReal, 'correct', '90');

        [$score, $result] = SubmissionService::maybeSetScoringResult($parentGroup, $judging);

        // Sample child IS counted; score is 100 (10+90).
        self::assertEquals('100.000000000', $score);
        self::assertEquals('correct', $result);
    }

    /**
     * Test SUM aggregation with a mix of correct and wrong scores.
     */
    public function testMaybeSetScoringResultSumWithPartialScores(): void
    {
        $group = $this->createTestcaseGroup('group1', TestcaseAggregationType::SUM, onRejectContinue: true);
        $problem = new Problem();
        $problem->setTimelimit(1)->setName('test');
        $tc1 = $this->createTestcase($problem, $group, 1);
        $tc2 = $this->createTestcase($problem, $group, 2);

        $judging = $this->createJudging();
        $this->addJudgingRun($judging, $tc1, 'correct', '15.5');
        $this->addJudgingRun($judging, $tc2, 'wrong-answer', '7.25');

        [$score, $result] = SubmissionService::maybeSetScoringResult($group, $judging);

        self::assertEquals('22.750000000', $score);
        self::assertEquals('wrong-answer', $result);
    }

    public function testMaybeSetScoringResultAvgDecimalScores(): void
    {
        $group = $this->createTestcaseGroup('group1', TestcaseAggregationType::AVG);
        $problem = new Problem();
        $problem->setTimelimit(1)->setName('test');
        $tc1 = $this->createTestcase($problem, $group, 1);
        $tc2 = $this->createTestcase($problem, $group, 2);

        $judging = $this->createJudging();
        $this->addJudgingRun($judging, $tc1, 'correct', '10');
        $this->addJudgingRun($judging, $tc2, 'correct', '11');

        [$score, $result] = SubmissionService::maybeSetScoringResult($group, $judging);

        // Average of 10 and 11 = 10.5
        self::assertEquals('10.500000000', $score);
        self::assertEquals('correct', $result);
    }

    public function testMaybeSetScoringResultMinAcrossChildGroups(): void
    {
        $parentGroup = $this->createTestcaseGroup('parent', TestcaseAggregationType::MIN);

        $child1 = $this->createTestcaseGroup('child1', TestcaseAggregationType::SUM, acceptScore: '80');
        $child1->setParent($parentGroup);
        $parentGroup->getChildren()->add($child1);

        $child2 = $this->createTestcaseGroup('child2', TestcaseAggregationType::SUM, acceptScore: '50');
        $child2->setParent($parentGroup);
        $parentGroup->getChildren()->add($child2);

        $problem = new Problem();
        $problem->setTimelimit(1)->setName('test');

        $tc1 = $this->createTestcase($problem, $child1, 1);
        $tc2 = $this->createTestcase($problem, $child2, 2);

        $judging = $this->createJudging();
        $this->addJudgingRun($judging, $tc1, 'correct', '80');
        $this->addJudgingRun($judging, $tc2, 'correct', '50');

        [$score, $result] = SubmissionService::maybeSetScoringResult($parentGroup, $judging);

        self::assertEquals('50.000000000', $score);
        self::assertEquals('correct', $result);
    }

    public function testMaybeSetScoringResultMaxAcrossChildGroups(): void
    {
        $parentGroup = $this->createTestcaseGroup('parent', TestcaseAggregationType::MAX);

        $child1 = $this->createTestcaseGroup('child1', TestcaseAggregationType::SUM, acceptScore: '30');
        $child1->setParent($parentGroup);
        $parentGroup->getChildren()->add($child1);

        $child2 = $this->createTestcaseGroup('child2', TestcaseAggregationType::SUM, acceptScore: '70');
        $child2->setParent($parentGroup);
        $parentGroup->getChildren()->add($child2);

        $problem = new Problem();
        $problem->setTimelimit(1)->setName('test');

        $tc1 = $this->createTestcase($problem, $child1, 1);
        $tc2 = $this->createTestcase($problem, $child2, 2);

        $judging = $this->createJudging();
        $this->addJudgingRun($judging, $tc1, 'correct', '30');
        $this->addJudgingRun($judging, $tc2, 'correct', '70');

        [$score, $result] = SubmissionService::maybeSetScoringResult($parentGroup, $judging);

        self::assertEquals('70.000000000', $score);
        self::assertEquals('correct', $result);
    }

    /**
     * Test onRejectContinue=false with pending run after a correct one.
     * All runs must be resolved, or we get null (since no rejection seen yet).
     */
    public function testMaybeSetScoringResultOnRejectStopPendingAfterCorrect(): void
    {
        $group = $this->createTestcaseGroup('group1', TestcaseAggregationType::SUM, onRejectContinue: false);
        $problem = new Problem();
        $problem->setTimelimit(1)->setName('test');
        $tc1 = $this->createTestcase($problem, $group, 1);
        $tc2 = $this->createTestcase($problem, $group, 2);

        $judging = $this->createJudging();
        $this->addJudgingRun($judging, $tc1, 'correct', '10');
        $this->addJudgingRun($judging, $tc2, null, '0');

        [$score, $result] = SubmissionService::maybeSetScoringResult($group, $judging);

        // No rejection yet, but pending results remain -> indeterminate
        self::assertNull($score);
        self::assertNull($result);
    }

    /**
     * Test onRejectContinue=false stops on rejection and returns the result
     * even when later runs are still pending.
     */
    public function testMaybeSetScoringResultOnRejectStopOnRejection(): void
    {
        $group = $this->createTestcaseGroup('group1', TestcaseAggregationType::SUM, onRejectContinue: false);
        $problem = new Problem();
        $problem->setTimelimit(1)->setName('test');
        $tc1 = $this->createTestcase($problem, $group, 1);
        $tc2 = $this->createTestcase($problem, $group, 2);

        $judging = $this->createJudging();
        $this->addJudgingRun($judging, $tc1, 'wrong-answer', '0');
        $this->addJudgingRun($judging, $tc2, null, '0'); // Pending

        [$score, $result] = SubmissionService::maybeSetScoringResult($group, $judging);

        // Rejection seen and onRejectContinue=false -> return early with result
        self::assertEquals('0.000000000', $score);
        self::assertEquals('wrong-answer', $result);
    }

    /**
     * Test onRejectContinue=true continues past a rejection and waits for
     * all pending results.
     */
    public function testMaybeSetScoringResultOnRejectContinuePastRejection(): void
    {
        $group = $this->createTestcaseGroup('group1', TestcaseAggregationType::SUM, onRejectContinue: true);
        $problem = new Problem();
        $problem->setTimelimit(1)->setName('test');
        $tc1 = $this->createTestcase($problem, $group, 1);
        $tc2 = $this->createTestcase($problem, $group, 2);

        $judging = $this->createJudging();
        $this->addJudgingRun($judging, $tc1, 'wrong-answer', '5');
        $this->addJudgingRun($judging, $tc2, null, '0'); // Pending

        [$score, $result] = SubmissionService::maybeSetScoringResult($group, $judging);

        // Rejection seen but onRejectContinue=true -> still indeterminate due to pending
        self::assertNull($score);
        self::assertNull($result);
    }

    /**
     * Test that a leaf group with acceptScore and no runs returns score 0, correct
     * (empty group with acceptScore).
     */
    public function testMaybeSetScoringResultAcceptScoreNoRuns(): void
    {
        $group = $this->createTestcaseGroup('empty-accept', TestcaseAggregationType::SUM, acceptScore: '50');
        $judging = $this->createJudging();

        [$score, $result] = SubmissionService::maybeSetScoringResult($group, $judging);

        // No runs -> $results is empty, SUM of nothing = 0, allCorrect stays true
        self::assertEquals('0.000000000', $score);
        self::assertEquals('correct', $result);
    }

    /**
     * Test parent group pending when a child group has pending results.
     */
    public function testMaybeSetScoringResultParentPendingChild(): void
    {
        $parentGroup = $this->createTestcaseGroup('parent', TestcaseAggregationType::SUM);

        $child1 = $this->createTestcaseGroup('child1', TestcaseAggregationType::SUM, acceptScore: '50');
        $child1->setParent($parentGroup);
        $parentGroup->getChildren()->add($child1);

        $child2 = $this->createTestcaseGroup('child2', TestcaseAggregationType::SUM, acceptScore: '50');
        $child2->setParent($parentGroup);
        $parentGroup->getChildren()->add($child2);

        $problem = new Problem();
        $problem->setTimelimit(1)->setName('test');

        $tc1 = $this->createTestcase($problem, $child1, 1);
        $tc2 = $this->createTestcase($problem, $child2, 2);

        $judging = $this->createJudging();
        $this->addJudgingRun($judging, $tc1, 'correct', '50');
        $this->addJudgingRun($judging, $tc2, null, '0'); // Pending

        [$score, $result] = SubmissionService::maybeSetScoringResult($parentGroup, $judging);

        self::assertNull($score);
        self::assertNull($result);
    }

    /**
     * @dataProvider provideNormalizeExpectedResult
     */
    public function testNormalizeExpectedResult(string $input, string $expected): void
    {
        self::assertSame($expected, SubmissionService::normalizeExpectedResult($input));
    }

    public function provideNormalizeExpectedResult(): Generator
    {
        // All 7 PROBLEM_RESULT_REMAP entries
        yield 'ACCEPTED -> CORRECT' => ['ACCEPTED', 'CORRECT'];
        yield 'WRONG_ANSWER -> WRONG-ANSWER' => ['WRONG_ANSWER', 'WRONG-ANSWER'];
        yield 'TIME_LIMIT_EXCEEDED -> TIMELIMIT' => ['TIME_LIMIT_EXCEEDED', 'TIMELIMIT'];
        yield 'RUN_TIME_ERROR -> RUN-ERROR' => ['RUN_TIME_ERROR', 'RUN-ERROR'];
        yield 'COMPILER_ERROR -> COMPILER-ERROR' => ['COMPILER_ERROR', 'COMPILER-ERROR'];
        yield 'NO_OUTPUT -> NO-OUTPUT' => ['NO_OUTPUT', 'NO-OUTPUT'];
        yield 'OUTPUT_LIMIT -> OUTPUT-LIMIT' => ['OUTPUT_LIMIT', 'OUTPUT-LIMIT'];

        // Case insensitivity (lowercased input gets uppercased first)
        yield 'lowercase accepted' => ['accepted', 'CORRECT'];
        yield 'mixed case Accepted' => ['Accepted', 'CORRECT'];
        yield 'lowercase wrong_answer' => ['wrong_answer', 'WRONG-ANSWER'];

        // Whitespace trimming
        yield 'leading/trailing whitespace' => ['  ACCEPTED  ', 'CORRECT'];
        yield 'whitespace around unknown' => ['  CORRECT  ', 'CORRECT'];

        // Already-normalized values pass through
        yield 'CORRECT passes through' => ['CORRECT', 'CORRECT'];
        yield 'WRONG-ANSWER passes through' => ['WRONG-ANSWER', 'WRONG-ANSWER'];
        yield 'TIMELIMIT passes through' => ['TIMELIMIT', 'TIMELIMIT'];

        // Unknown values pass through uppercased
        yield 'unknown value' => ['something-unknown', 'SOMETHING-UNKNOWN'];

        // Empty string
        yield 'empty string' => ['', ''];
    }

    public function testParseExpectedAnnotationCaseInsensitive(): void
    {
        $source = "// @expected_results@: CORRECT\nint main() {}";
        $result = SubmissionService::parseExpectedAnnotation($source, []);

        self::assertNotNull($result);
        self::assertNotFalse($result);
        self::assertEquals(['CORRECT'], $result['results']);
    }

    public function testParseExpectedAnnotationDuplicateNumericScore(): void
    {
        $source = "// @EXPECTED_SCORE@: 60\n// @EXPECTED_SCORE@: 80\nint main() {}";
        $result = SubmissionService::parseExpectedAnnotation($source, []);

        self::assertFalse($result);
    }

    public function testParseExpectedAnnotationWithNormalizedResults(): void
    {
        $source = "// @EXPECTED_RESULTS@: ACCEPTED, WRONG_ANSWER\nint main() {}";
        $result = SubmissionService::parseExpectedAnnotation($source, []);

        self::assertNotNull($result);
        self::assertNotFalse($result);
        self::assertEquals(['CORRECT', 'WRONG-ANSWER'], $result['results']);
    }

    /**
     * Test resultsRemap with multiple keys, only some matching.
     */
    public function testParseExpectedAnnotationPartialRemap(): void
    {
        $source = "// @EXPECTED_RESULTS@: CORRECT, WRONG-ANSWER\nint main() {}";
        $remap = [
            'correct' => 'accepted',
            'no-output' => 'wrong-answer', // should not apply
        ];
        $result = SubmissionService::parseExpectedAnnotation($source, $remap);

        self::assertNotNull($result);
        self::assertNotFalse($result);
        self::assertEquals(['ACCEPTED', 'WRONG-ANSWER'], $result['results']);
    }

    // =========================================================================
    // Helper methods for creating test entities
    // =========================================================================

    private function createTestcaseGroup(
        string $name,
        TestcaseAggregationType $aggregationType,
        ?string $acceptScore = null,
        bool $onRejectContinue = true,
        bool $ignoreSample = false,
    ): TestcaseGroup {
        $group = new TestcaseGroup();
        $group->setTestcaseGroupId($this->nextGroupId++);
        $group->setName($name);
        $group->setAggregationType($aggregationType);
        $group->setOnRejectContinue($onRejectContinue);
        $group->setIgnoreSample($ignoreSample);
        if ($acceptScore !== null) {
            $group->setAcceptScore($acceptScore);
        }
        return $group;
    }

    private function createTestcase(Problem $problem, TestcaseGroup $group, int $rank): Testcase
    {
        $testcase = new Testcase();
        $testcase->setProblem($problem);
        $testcase->setTestcaseGroup($group);
        $testcase->setRank($rank);
        $testcase->setMd5sumInput('d41d8cd98f00b204e9800998ecf8427e');
        $testcase->setMd5sumOutput('d41d8cd98f00b204e9800998ecf8427e');
        return $testcase;
    }

    private function createJudging(): Judging
    {
        $contest = new Contest();
        $contest->setExternalid('test');
        $contest->setName('test');
        $contest->setShortname('test');
        $contest->setStarttimeString('2025-01-01 00:00:00');

        $judging = new Judging();
        $judging->setContest($contest);
        $judging->setStarttime(1000);
        return $judging;
    }

    private function addJudgingRun(Judging $judging, Testcase $testcase, ?string $result, ?string $score): JudgingRun
    {
        $run = new JudgingRun();
        $run->setJudging($judging);
        $run->setTestcase($testcase);
        // Only set runresult if not null (pending runs have null runresult)
        if ($result !== null) {
            $run->setRunresult($result);
        }
        // Set score if provided (null means no score.txt was produced)
        if ($score !== null) {
            $run->setScore($score);
        }
        $run->setRuntime(0.1);
        $run->setEndtime(1000);
        $judging->addRun($run);
        return $run;
    }

    private function createSubmissionService(): SubmissionService
    {
        return new SubmissionService(
            $this->createMock(\Doctrine\ORM\EntityManagerInterface::class),
            $this->createMock(\Psr\Log\LoggerInterface::class),
            $this->createMock(\App\Service\DOMJudgeService::class),
            $this->createMock(\App\Service\ConfigurationService::class),
            $this->createMock(\App\Service\EventLogService::class),
            $this->createMock(\App\Service\ScoreboardService::class),
            $this->createMock(\Knp\Component\Pager\PaginatorInterface::class),
        );
    }
}
