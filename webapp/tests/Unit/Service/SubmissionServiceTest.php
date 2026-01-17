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
        $this->addJudgingRun($judging, $testcase, 'correct', '25');

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
        $this->addJudgingRun($judging, $testcase, 'wrong-answer', '0');

        [$score, $result] = SubmissionService::maybeSetScoringResult($group, $judging);

        self::assertEquals('0.000000000', $score);
        self::assertEquals('wrong-answer', $result);
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
        $this->addJudgingRun($judging, $tc1, 'correct', '30');
        $this->addJudgingRun($judging, $tc2, 'correct', '70');

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
        $this->addJudgingRun($judging, $tc1, 'correct', '30');
        $this->addJudgingRun($judging, $tc2, 'wrong-answer', '0'); // Group 2 fails

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
        $this->addJudgingRun($judging, $tc1, 'correct', '30');

        $submissionService = new SubmissionService(
            $this->createMock(\Doctrine\ORM\EntityManagerInterface::class),
            $this->createMock(\Psr\Log\LoggerInterface::class),
            $this->createMock(\App\Service\DOMJudgeService::class),
            $this->createMock(\App\Service\ConfigurationService::class),
            $this->createMock(\App\Service\EventLogService::class),
            $this->createMock(\App\Service\ScoreboardService::class),
            $this->createMock(\Knp\Component\Pager\PaginatorInterface::class)
        );

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
        // Individual testcase scores are 0, but group has accept_score 20
        $this->addJudgingRun($judging, $tc1, 'correct', '0');
        $this->addJudgingRun($judging, $tc2, 'correct', '0');

        $submissionService = new SubmissionService(
            $this->createMock(\Doctrine\ORM\EntityManagerInterface::class),
            $this->createMock(\Psr\Log\LoggerInterface::class),
            $this->createMock(\App\Service\DOMJudgeService::class),
            $this->createMock(\App\Service\ConfigurationService::class),
            $this->createMock(\App\Service\EventLogService::class),
            $this->createMock(\App\Service\ScoreboardService::class),
            $this->createMock(\Knp\Component\Pager\PaginatorInterface::class)
        );

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
        self::assertEquals('results', $result['type']);
        self::assertEquals(['CORRECT'], $result['value']);
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
        self::assertEquals('results', $result['type']);
        self::assertEquals(['CORRECT', 'WRONG-ANSWER'], $result['value']);
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
        self::assertEquals('results', $result['type']);
        self::assertEquals(['CORRECT'], $result['value']);
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
        self::assertEquals('score', $result['type']);
        self::assertEquals(60.0, $result['value']);
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
        self::assertEquals('score', $result['type']);
        self::assertEquals(75.5, $result['value']);
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
     * Test parsing with both annotation types returns false.
     */
    public function testParseExpectedAnnotationMixedTags(): void
    {
        $source = "// @EXPECTED_RESULTS@: CORRECT\n// @EXPECTED_SCORE@: 60\nint main() {}";
        $result = SubmissionService::parseExpectedAnnotation($source, []);

        self::assertFalse($result);
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
        self::assertEquals('results', $result['type']);
        self::assertEquals(['CORRECT'], $result['value']);
    }

    // =========================================================================
    // Helper methods for creating test entities
    // =========================================================================

    private function createTestcaseGroup(
        string $name,
        TestcaseAggregationType $aggregationType,
        ?string $acceptScore = null,
        bool $onRejectContinue = true
    ): TestcaseGroup {
        $group = new TestcaseGroup();
        $group->setTestcaseGroupId($this->nextGroupId++);
        $group->setName($name);
        $group->setAggregationType($aggregationType);
        $group->setOnRejectContinue($onRejectContinue);
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

    private function addJudgingRun(Judging $judging, Testcase $testcase, ?string $result, string $score): JudgingRun
    {
        $run = new JudgingRun();
        $run->setJudging($judging);
        $run->setTestcase($testcase);
        // Only set runresult if not null (pending runs have null runresult)
        if ($result !== null) {
            $run->setRunresult($result);
        }
        // Set score as string to match database behavior (decimal column)
        $run->setScore($score);
        $run->setRuntime(0.1);
        $run->setEndtime(1000);
        $judging->addRun($run);
        return $run;
    }
}
