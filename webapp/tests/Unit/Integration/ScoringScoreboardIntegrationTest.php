<?php declare(strict_types=1);

namespace App\Tests\Unit\Integration;

use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\Judgehost;
use App\Entity\Judging;
use App\Entity\JudgingRun;
use App\Entity\Language;
use App\Entity\Problem;
use App\Entity\ScoreboardType;
use App\Entity\Submission;
use App\Entity\Team;
use App\Entity\TeamCategory;
use App\Entity\Testcase;
use App\Entity\TestcaseGroup;
use App\Entity\TestcaseAggregationType;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\ScoreboardService;
use App\Utils\Scoreboard\Scoreboard;
use App\Utils\Scoreboard\TeamScore;
use Doctrine\ORM\EntityManager;
use Exception;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests for scoring-type contests (partial scoring).
 *
 * These tests verify that the scoreboard correctly calculates and displays
 * scores for contests with ScoreboardType::SCORE instead of PASS_FAIL.
 */
class ScoringScoreboardIntegrationTest extends KernelTestCase
{
    final public const CONTEST_NAME = 'scoringtest';
    final public const NUM_PROBLEMS = 2;
    final public const NUM_TEAMS = 3;

    private DOMJudgeService $dj;

    private ScoreboardService $ss;

    private ?EntityManager $em;

    private ConfigurationService&MockObject $config;

    private array $configValues;

    private Contest $contest;

    private ?Judgehost $judgehost;

    /**
     * @var Problem[]
     */
    private ?array $problems = null;

    /**
     * @var Team[]
     */
    private ?array $teams = null;

    /**
     * @var TestcaseGroup[]
     */
    private array $testcaseGroups = [];

    /**
     * @var Testcase[]
     */
    private array $testcases = [];

    protected function setUp(): void
    {
        self::bootKernel();

        // Default configuration values:
        $this->configValues = [
            'verification_required'    => false,
            'compile_penalty'          => false,
            'penalty_time'             => 20,
            'score_in_seconds'         => false,
            'shadow_mode'              => 0,
            'show_teams_on_scoreboard' => 0,
            'submission_rate_limit'    => [],
        ];

        $this->config = $this->createMock(ConfigurationService::class);
        $this->config->expects($this->any())
            ->method('get')
            ->with($this->isType('string'))
            ->will($this->returnCallback($this->getConfig(...)));

        $this->dj = self::getContainer()->get(DOMJudgeService::class);
        $this->em = self::getContainer()->get('doctrine')->getManager();
        $this->ss = new ScoreboardService(
            $this->em, $this->dj, $this->config,
            self::getContainer()->get(LoggerInterface::class)
        );

        // Create a SCORING contest
        $this->contest = new Contest();
        $this->contest
            ->setExternalid(self::CONTEST_NAME)
            ->setName(self::CONTEST_NAME)
            ->setShortname(self::CONTEST_NAME)
            ->setStarttimeString(date('Y').'-01-01 10:00:00 Europe/Amsterdam')
            ->setActivatetimeString('-01:00')
            ->setEndtimeString('+02:00')
            ->setScoreboardType(ScoreboardType::SCORE);
        $this->em->persist($this->contest);

        $hostname = self::CONTEST_NAME.'-judgehost';
        $this->judgehost = $this->em->getRepository(Judgehost::class)
            ->findOneBy(['hostname' => $hostname]);
        if (!$this->judgehost) {
            $this->judgehost = new Judgehost();
            $this->judgehost
                ->setHostname($hostname);
            $this->em->persist($this->judgehost);
        }

        $category = $this->em->getRepository(TeamCategory::class)
            ->findOneBy(['sortorder' => 0]);

        for ($i = 0; $i < self::NUM_TEAMS; $i++) {
            $this->teams[$i] = new Team();
            $this->teams[$i]
                ->setName(self::CONTEST_NAME.' team '.$i)
                ->addCategory($category);
            $this->em->persist($this->teams[$i]);
        }

        // Create problems with testcase groups for scoring
        for ($i = 0; $i < self::NUM_PROBLEMS; $i++) {
            $letter = chr(ord('a') + $i);
            $this->problems[$i] = new Problem();
            $this->problems[$i]
                ->setName(self::CONTEST_NAME.' problem '.$letter)
                ->setTimelimit(5)
                ->setTypes([Problem::TYPE_SCORING]); // Mark as scoring problem

            // Create a parent testcase group with SUM aggregation
            $parentGroup = new TestcaseGroup();
            $parentGroup
                ->setName('data')
                ->setAggregationType(TestcaseAggregationType::SUM)
                ->setOnRejectContinue(true);
            $this->em->persist($parentGroup);
            $this->testcaseGroups["problem_{$i}_parent"] = $parentGroup;

            $this->problems[$i]->setParentTestcaseGroup($parentGroup);

            // Create child testcase groups with fixed scores
            for ($g = 0; $g < 3; $g++) {
                $childGroup = new TestcaseGroup();
                $childGroup
                    ->setName("group_{$g}")
                    ->setParent($parentGroup)
                    ->setAcceptScore((string)(($g + 1) * 10)) // 10, 20, 30 points
                    ->setAggregationType(TestcaseAggregationType::SUM);
                $this->em->persist($childGroup);
                $this->testcaseGroups["problem_{$i}_group_{$g}"] = $childGroup;

                // Create one testcase per group
                $testcase = new Testcase();
                $testcase
                    ->setProblem($this->problems[$i])
                    ->setRank($g + 1)
                    ->setDescription("Testcase group $g")
                    ->setTestcaseGroup($childGroup)
                    ->setMd5sumInput('d41d8cd98f00b204e9800998ecf8427e')
                    ->setMd5sumOutput('d41d8cd98f00b204e9800998ecf8427e');
                $this->em->persist($testcase);
                $this->testcases["problem_{$i}_tc_{$g}"] = $testcase;
            }

            $cp = new ContestProblem();
            $cp
                ->setShortname($letter)
                ->setProblem($this->problems[$i])
                ->setContest($this->contest);

            $this->contest->addProblem($cp);
            $this->problems[$i]->addContestProblem($cp);

            $this->em->persist($this->problems[$i]);
            $this->em->persist($cp);
        }

        $this->em->flush();
    }

    protected function tearDown(): void
    {
        // Preserve the data for inspection if a test failed.
        if (!$this->hasFailed()) {
            $this->em->remove($this->contest);
            $this->em->remove($this->judgehost);
            foreach ($this->teams as $team) {
                $this->em->remove($team);
            }
            foreach ($this->testcases as $testcase) {
                $this->em->remove($testcase);
            }
            foreach ($this->testcaseGroups as $group) {
                $this->em->remove($group);
            }
            foreach ($this->problems as $problem) {
                $this->em->remove($problem);
            }
        }

        $this->em->flush();

        parent::tearDown();

        $this->em->close();
        $this->em = null; // avoid memory leaks
    }

    public function testScoringScoreboardEmpty(): void
    {
        $this->recalcScoreCaches();

        // All teams should have 0 points with rank 1 (tie)
        $expected_scores = [
            ['team' => $this->teams[0], 'rank' => 1, 'score' => '0.000000000'],
            ['team' => $this->teams[1], 'rank' => 1, 'score' => '0.000000000'],
            ['team' => $this->teams[2], 'rank' => 1, 'score' => '0.000000000'],
        ];

        foreach ([false, true] as $jury) {
            $scoreboard = $this->ss->getScoreboard($this->contest, $jury);
            static::assertScoringScoresMatch($expected_scores, $scoreboard);
        }
    }

    public function testScoringScoreboardPartialScores(): void
    {
        $lang = $this->em->getRepository(Language::class)->findByExternalId('c');

        // Team 0: Gets 30 points on problem 0 (groups 0 and 1 correct = 10+20)
        $this->createScoringSubmission($lang, $this->problems[0], $this->teams[0], 10*60, [
            'problem_0_tc_0' => ['correct', '10'],
            'problem_0_tc_1' => ['correct', '20'],
            'problem_0_tc_2' => ['wrong-answer', '0'],
        ]);

        // Team 1: Gets 60 points on problem 0 (all correct = 10+20+30)
        $this->createScoringSubmission($lang, $this->problems[0], $this->teams[1], 15*60, [
            'problem_0_tc_0' => ['correct', '10'],
            'problem_0_tc_1' => ['correct', '20'],
            'problem_0_tc_2' => ['correct', '30'],
        ]);

        // Team 2: Gets 10 points on problem 0 (only group 0 correct)
        $this->createScoringSubmission($lang, $this->problems[0], $this->teams[2], 20*60, [
            'problem_0_tc_0' => ['correct', '10'],
            'problem_0_tc_1' => ['wrong-answer', '0'],
            'problem_0_tc_2' => ['wrong-answer', '0'],
        ]);

        $this->recalcScoreCaches();

        $expected_scores = [
            ['team' => $this->teams[1], 'rank' => 1, 'score' => '60.000000000'],
            ['team' => $this->teams[0], 'rank' => 2, 'score' => '30.000000000'],
            ['team' => $this->teams[2], 'rank' => 3, 'score' => '10.000000000'],
        ];

        foreach ([false, true] as $jury) {
            $scoreboard = $this->ss->getScoreboard($this->contest, $jury);
            static::assertScoringScoresMatch($expected_scores, $scoreboard);
        }
    }

    public function testScoringScoreboardMultipleSubmissions(): void
    {
        $lang = $this->em->getRepository(Language::class)->findByExternalId('c');

        // Team 0: First submission gets 10 points
        $this->createScoringSubmission($lang, $this->problems[0], $this->teams[0], 10*60, [
            'problem_0_tc_0' => ['correct', '10'],
            'problem_0_tc_1' => ['wrong-answer', '0'],
            'problem_0_tc_2' => ['wrong-answer', '0'],
        ]);

        // Team 0: Second submission gets 30 points (better score should be kept)
        $this->createScoringSubmission($lang, $this->problems[0], $this->teams[0], 20*60, [
            'problem_0_tc_0' => ['correct', '10'],
            'problem_0_tc_1' => ['correct', '20'],
            'problem_0_tc_2' => ['wrong-answer', '0'],
        ]);

        // Team 0: Third submission gets 20 points (worse than second, should be ignored)
        $this->createScoringSubmission($lang, $this->problems[0], $this->teams[0], 25*60, [
            'problem_0_tc_0' => ['correct', '10'],
            'problem_0_tc_1' => ['correct', '10'],
            'problem_0_tc_2' => ['wrong-answer', '0'],
        ]);

        $this->recalcScoreCaches();

        // The best score (30) should be used, not the latest (20)
        $scoreboard = $this->ss->getScoreboard($this->contest, true);
        $scores = $scoreboard->getScores();
        $team0Score = $scores[$this->teams[0]->getTeamid()];
        static::assertEquals('30.000000000', $team0Score->score);
    }

    public function testScoringScoreboardMultipleProblems(): void
    {
        $lang = $this->em->getRepository(Language::class)->findByExternalId('c');

        // Team 0: 30 points on problem 0, 60 points on problem 1 = 90 total
        $this->createScoringSubmission($lang, $this->problems[0], $this->teams[0], 10*60, [
            'problem_0_tc_0' => ['correct', '10'],
            'problem_0_tc_1' => ['correct', '20'],
            'problem_0_tc_2' => ['wrong-answer', '0'],
        ]);
        $this->createScoringSubmission($lang, $this->problems[1], $this->teams[0], 15*60, [
            'problem_1_tc_0' => ['correct', '10'],
            'problem_1_tc_1' => ['correct', '20'],
            'problem_1_tc_2' => ['correct', '30'],
        ]);

        // Team 1: 60 points on problem 0 only = 60 total
        $this->createScoringSubmission($lang, $this->problems[0], $this->teams[1], 12*60, [
            'problem_0_tc_0' => ['correct', '10'],
            'problem_0_tc_1' => ['correct', '20'],
            'problem_0_tc_2' => ['correct', '30'],
        ]);

        $this->recalcScoreCaches();

        $expected_scores = [
            ['team' => $this->teams[0], 'rank' => 1, 'score' => '90.000000000'],
            ['team' => $this->teams[1], 'rank' => 2, 'score' => '60.000000000'],
            ['team' => $this->teams[2], 'rank' => 3, 'score' => '0.000000000'],
        ];

        foreach ([false, true] as $jury) {
            $scoreboard = $this->ss->getScoreboard($this->contest, $jury);
            static::assertScoringScoresMatch($expected_scores, $scoreboard);
        }
    }

    public function testScoringScoreboardWithFreeze(): void
    {
        $this->contest->setFreezetimeString('+1:00:00');
        $lang = $this->em->getRepository(Language::class)->findByExternalId('c');

        // Team 0: Submission before freeze with 30 points
        $this->createScoringSubmission($lang, $this->problems[0], $this->teams[0], 30*60, [
            'problem_0_tc_0' => ['correct', '10'],
            'problem_0_tc_1' => ['correct', '20'],
            'problem_0_tc_2' => ['wrong-answer', '0'],
        ]);

        // Team 0: Submission after freeze with 60 points (should only show for jury)
        $this->createScoringSubmission($lang, $this->problems[0], $this->teams[0], 70*60, [
            'problem_0_tc_0' => ['correct', '10'],
            'problem_0_tc_1' => ['correct', '20'],
            'problem_0_tc_2' => ['correct', '30'],
        ]);

        $this->recalcScoreCaches();

        // Jury should see 60 points (best score including after freeze)
        $juryScoreboard = $this->ss->getScoreboard($this->contest, true);
        $juryScores = $juryScoreboard->getScores();
        static::assertEquals('60.000000000', $juryScores[$this->teams[0]->getTeamid()]->score);

        // Public should see 30 points (only before freeze)
        $publicScoreboard = $this->ss->getScoreboard($this->contest, false);
        $publicScores = $publicScoreboard->getScores();
        static::assertEquals('30.000000000', $publicScores[$this->teams[0]->getTeamid()]->score);
    }

    protected function assertScoringScoresMatch(array $expected_scores, Scoreboard $scoreboard): void
    {
        $scores = $scoreboard->getScores();

        foreach ($expected_scores as $row) {
            $team = $row['team'];
            $name = $team->getEffectiveName();
            $teamid = $team->getTeamid();

            if (!isset($scores[$teamid])) {
                static::fail("No score found for team '$name' (id: $teamid)");
            }

            $score = $scores[$teamid];
            static::assertInstanceOf(TeamScore::class, $score);

            static::assertEquals($row['rank'], $score->rank, "Rank for '$name'");
            static::assertEquals($row['score'], $score->score, "Score for '$name'");
        }
    }

    protected function recalcScoreCaches(): void
    {
        $this->em->flush();
        $this->ss->refreshCache($this->contest);
    }

    /**
     * Create a submission with judging runs that have specific scores.
     *
     * @param array<string, array{string, string}> $testcaseResults Map of testcase key to [result, score]
     */
    protected function createScoringSubmission(
        Language $language,
        Problem $problem,
        Team $team,
        float $contest_time_seconds,
        array $testcaseResults,
        ?string $score = null
    ): Submission {
        $cp = $this->em->getRepository(ContestProblem::class)->find(
            ['contest' => $this->contest, 'problem' => $problem]
        );
        $submittime = $this->contest->getStarttime() + $contest_time_seconds;

        $submission = new Submission();
        $submission
            ->setContestProblem($cp)
            ->setProblem($problem)
            ->setContest($this->contest)
            ->setLanguage($language)
            ->setTeam($team)
            ->setSubmittime($submittime);
        $this->em->persist($submission);

        // Calculate total score if not provided
        if ($score === null) {
            $totalScore = '0';
            foreach ($testcaseResults as $tcKey => [$result, $tcScore]) {
                if ($result === 'correct') {
                    $totalScore = bcadd($totalScore, $tcScore, 9);
                }
            }
            $score = $totalScore;
        }

        // Determine overall result
        $allCorrect = true;
        $firstIncorrect = null;
        foreach ($testcaseResults as $tcKey => [$result, $tcScore]) {
            if ($result !== 'correct') {
                $allCorrect = false;
                if ($firstIncorrect === null) {
                    $firstIncorrect = $result;
                }
            }
        }
        $overallResult = $allCorrect ? 'correct' : $firstIncorrect;

        $judging = new Judging();
        $judging
            ->setSubmission($submission)
            ->setContest($this->contest)
            ->setStarttime($submittime + 5)
            ->setEndtime($submittime + 10)
            ->setResult($overallResult)
            ->setScore($score)
            ->setValid(true);
        $this->em->persist($judging);

        $submission->addJudging($judging);

        // Create judging runs for each testcase
        $rank = 0;
        foreach ($testcaseResults as $tcKey => [$result, $tcScore]) {
            if (!isset($this->testcases[$tcKey])) {
                continue;
            }
            $testcase = $this->testcases[$tcKey];
            $rank++;

            $run = new JudgingRun();
            $run
                ->setJudging($judging)
                ->setTestcase($testcase)
                ->setRunresult($result)
                ->setScore($tcScore)
                ->setRuntime(0.1)
                ->setEndtime($submittime + 5 + $rank);
            $this->em->persist($run);

            $judging->addRun($run);
        }

        $this->em->flush();

        return $submission;
    }

    public function setConfig(string $name, mixed $value): void
    {
        $this->configValues[$name] = $value;
    }

    public function getConfig(string $name): mixed
    {
        if (!array_key_exists($name, $this->configValues)) {
            throw new Exception("No configuration value set for '$name'");
        }

        return $this->configValues[$name];
    }
}
