<?php declare(strict_types=1);

namespace App\Tests\Unit\Integration;

use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\Judgehost;
use App\Entity\Judging;
use App\Entity\Language;
use App\Entity\Problem;
use App\Entity\Rejudging;
use App\Entity\Submission;
use App\Entity\Team;
use App\Entity\TeamCategory;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Service\ScoreboardService;
use App\Utils\Scoreboard\Scoreboard;
use App\Utils\Scoreboard\ScoreboardMatrixItem;
use App\Utils\Scoreboard\SingleTeamScoreboard;
use App\Utils\Scoreboard\TeamScore;
use App\Utils\Utils;
use Doctrine\ORM\EntityManager;
use Exception;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ScoreboardIntegrationTest extends KernelTestCase
{
    final public const CONTEST_NAME = 'scoretest';
    final public const NUM_PROBLEMS = 3;
    final public const NUM_TEAMS = 3;

    private DOMJudgeService $dj;

    private ScoreboardService $ss;

    private ?EntityManager $em;

    private ConfigurationService&MockObject $config;

    private array $configValues;

    private Contest $contest;

    private ?Judgehost $judgehost;

    private Rejudging $rejudging;

    /**
     * @var Problem[]
     */
    private ?array $problems = null;

    /**
     * @var Team[]
     */
    private ?array $teams = null;

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

        // Create a contest, problems and teams for which to test the
        // scoreboard. These get deleted again in tearDown().
        $this->contest = new Contest();
        $this->contest
            ->setExternalid(self::CONTEST_NAME)
            ->setName(self::CONTEST_NAME)
            ->setShortname(self::CONTEST_NAME)
            ->setStarttimeString(date('Y').'-01-01 10:00:00 Europe/Amsterdam')
            ->setActivatetimeString('-01:00')
            ->setEndtimeString('+02:00');
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

        $this->rejudging = new Rejudging();
        $this->rejudging
            ->setStarttime(Utils::now())
            ->setReason(self::CONTEST_NAME);
        $this->em->persist($this->rejudging);

        $category = $this->em->getRepository(TeamCategory::class)
            ->findOneBy(['sortorder' => 0]);

        for ($i=0; $i<self::NUM_TEAMS; $i++) {
            $this->teams[$i] = new Team();
            $this->teams[$i]
                ->setName(self::CONTEST_NAME.' team '.$i)
                ->setCategory($category);
            $this->em->persist($this->teams[$i]);
        }

        for ($i=0; $i<self::NUM_PROBLEMS; $i++) {
            $letter = chr(ord('a') + $i);
            $this->problems[$i] = new Problem();
            $this->problems[$i]
                ->setName(self::CONTEST_NAME.' problem '.$letter)
                ->setTimelimit(5);

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
            $this->em->remove($this->rejudging);
            foreach ($this->teams as $team) {
                $this->em->remove($team);
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

    public function testScoreboardsEmpty(): void
    {
        $this->recalcScoreCaches();

        $expected_scores = [
            [ 'team' => $this->teams[0], 'rank' => 1, 'solved' => 0, 'time' => 0 ],
            [ 'team' => $this->teams[1], 'rank' => 1, 'solved' => 0, 'time' => 0 ],
        ];

        foreach ([ false, true ] as $jury) {
            $scoreboard = $this->ss->getScoreboard($this->contest, $jury);
            static::assertScoresMatch($expected_scores, $scoreboard);
            static::assertFTSMatch([], $scoreboard);
        }
    }

    public function testScoreboardsNoFreeze(): void
    {
        $this->contest->setFreezetimeString(null);
        $this->createDefaultSubmissions();
        $this->recalcScoreCaches();

        $expected_scores = [
            [ 'team' => $this->teams[0], 'rank' => 2, 'solved' => 0, 'time' => 0 ],
            [ 'team' => $this->teams[1], 'rank' => 1, 'solved' => 2, 'time' => 161 ],
        ];

        $expected_fts = [
            // problems[0] has earlier unjudged solution by teams[0]
            [ 'problem' => $this->problems[1], 'team' => $this->teams[1] ],
            // problems[2] solution by teams[1] is invalid
        ];

        foreach ([ false, true ] as $jury) {
            $scoreboard = $this->ss->getScoreboard($this->contest, $jury);
            static::assertScoresMatch($expected_scores, $scoreboard);
            static::assertFTSMatch($expected_fts, $scoreboard);
        }
    }

    public function testScoreboardJuryFreeze(): void
    {
        $this->createDefaultSubmissions();
        // Scoreboard cache is recalculated below for each freeze time.

        $expected_scores = [
            [ 'team' => $this->teams[0], 'rank' => 2, 'solved' => 0, 'time' => 0 ],
            [ 'team' => $this->teams[1], 'rank' => 1, 'solved' => 2, 'time' => 161 ],
        ];

        $expected_fts = [
            // problems[0] has earlier unjudged solution by teams[0]
            [ 'problem' => $this->problems[1], 'team' => $this->teams[1] ],
            // problems[2] solution by teams[1] is invalid
        ];

        // Jury scoreboard should not depend on freeze, so test a couple.
        foreach ([ '+0:30:00', '+1:00:00', '+1:20:00' ] as $freeze) {
            $this->contest->setFreezetimeString($freeze);
            $this->recalcScoreCaches();

            $scoreboard = $this->ss->getScoreboard($this->contest, true);
            static::assertScoresMatch($expected_scores, $scoreboard);
            static::assertFTSMatch($expected_fts, $scoreboard);
        }
    }

    public function testScoreboardPublicFreeze(): void
    {
        $this->contest->setFreezetimeString('+1:10:00');
        $this->createDefaultSubmissions();
        $this->recalcScoreCaches();

        $expected_scores = [
            [ 'team' => $this->teams[0], 'rank' => 2, 'solved' => 0, 'time' => 0 ],
            [ 'team' => $this->teams[1], 'rank' => 1, 'solved' => 1, 'time' => 69 ],
        ];

        $expected_fts = [
            // problems[0] has earlier unjudged solution by teams[0]
            // problems[1] solution by teams[1] is after freeze
            // problems[2] solution by teams[1] is invalid
        ];

        $scoreboard = $this->ss->getScoreboard($this->contest, false);
        static::assertScoresMatch($expected_scores, $scoreboard);
        static::assertFTSMatch($expected_fts, $scoreboard);
    }

    public function testScoreboardReproducible(): void
    {
        $this->createDefaultSubmissions();

        $this->recalcScoreCaches();
        $first = $this->ss->getScoreboard($this->contest, false);

        $this->recalcScoreCaches();
        $second = $this->ss->getScoreboard($this->contest, false);

        # Using assertSame would be better, but doesn't work with objects.
        static::assertEquals($first, $second, 'Repeated scoreboard is equal');
    }

    public function testTeamScoreboardFreezeFTS(): void
    {
        $this->contest->setFreezetimeString('+1:10:00');
        $this->createDefaultSubmissions();
        $this->recalcScoreCaches();

        $expected_fts = [
            // problems[0] has earlier unjudged solution by teams[0]
            // problems[1] solution by teams[1] is after freeze
            // problems[2] solution by teams[1] is invalid
        ];

        $team = $this->teams[1];

        $scoreboard = $this->ss->getTeamScoreboard($this->contest, $team->getTeamid(), false);

        static::assertInstanceOf(SingleTeamScoreboard::class, $scoreboard);
        static::assertFTSMatch($expected_fts, $scoreboard);
    }

    public function testOneSingleFTS(): void
    {
        $lang = $this->em->getRepository(Language::class)->find('c');

        $team = $this->teams[0];
        $this->createSubmission($lang, $this->problems[0], $team, 53*60+15.053, 'correct', true)
            ->getJudgings()[0]->setRejudging($this->rejudging);
        $this->createSubmission($lang, $this->problems[0], $team, 53*60+57.240, null);
        $this->createSubmission($lang, $this->problems[0], $team, 53*60+59.841, 'wrong-answer');
        $this->createSubmission($lang, $this->problems[1], $team, 61*60+00.000, 'correct', true);

        $team = $this->teams[1];
        $this->createSubmission($lang, $this->problems[0], $team, 53*60+15.054, 'correct', true);
        $this->createSubmission($lang, $this->problems[1], $team, 59*60+59.999, 'correct');

        $this->em->flush();

        $expected_fts = [
            [ 'problem' => $this->problems[0], 'team' => $this->teams[0] ],
            [ 'problem' => $this->problems[1], 'team' => $this->teams[1] ],
        ];

        foreach ([ false, true ] as $jury) {
            foreach ([ null, '+1:00:00', '+1:20:00' ] as $freeze) {
                $this->contest->setFreezetimeString($freeze);
                $this->recalcScoreCaches();

                $scoreboard = $this->ss->getScoreboard($this->contest, $jury);
                static::assertFTSMatch($expected_fts, $scoreboard);
            }
        }
    }

    public function testFTSwithVerificationRequired(): void
    {
        $lang = $this->em->getRepository(Language::class)->find('c');

        $team = $this->teams[0];
        $this->createSubmission($lang, $this->problems[0], $team, 53*60+15.053, 'correct', true)
            ->getJudgings()[0]->setRejudging($this->rejudging);
        $this->createSubmission($lang, $this->problems[0], $team, 53*60+57.240, null);
        $this->createSubmission($lang, $this->problems[0], $team, 53*60+59.841, 'wrong-answer');
        $this->createSubmission($lang, $this->problems[1], $team, 61*60+00.000, 'correct', true);

        $team = $this->teams[1];
        $this->createSubmission($lang, $this->problems[0], $team, 53*60+15.054, 'wrong-answer', true);
        $this->createSubmission($lang, $this->problems[1], $team, 59*60+59.999, 'correct');

        $this->setConfig('verification_required', true);

        $this->em->flush();

        $expected_fts = [
            [ 'problem' => $this->problems[0], 'team' => $this->teams[0] ],
            // problems[1] is solved by teams[0], but teams[1] has an earlier
            // unverified submission.
        ];

        foreach ([ false, true ] as $jury) {
            foreach ([ null, '+1:00:00', '+1:20:00' ] as $freeze) {
                $this->contest->setFreezetimeString($freeze);
                $this->recalcScoreCaches();

                $scoreboard = $this->ss->getScoreboard($this->contest, $jury);
                static::assertFTSMatch($expected_fts, $scoreboard);
            }
        }
    }

    public function testFTSwithQueuedRejudging(): void
    {
        $lang = $this->em->getRepository(Language::class)->find('c');

        $team = $this->teams[0];
        $this->createSubmission($lang, $this->problems[0], $team, 53 * 60 + 15.053, 'wrong-answer')
            ->setRejudging($this->rejudging);

        $this->createSubmission($lang, $this->problems[0], $team, 55*60+59.841, 'correct');

        $team = $this->teams[1];
        $this->createSubmission($lang, $this->problems[0], $team, 54*60+15.054, 'correct');

        $this->em->flush();
        $this->recalcScoreCaches();

        $expected_fts = [
            [ 'problem' => $this->problems[0], 'team' => $this->teams[1] ],
        ];

        foreach ([ false, true ] as $jury) {
            $scoreboard = $this->ss->getScoreboard($this->contest, $jury);
            static::assertFTSMatch($expected_fts, $scoreboard);
        }
    }

    protected function assertScoresMatch(array $expected_scores, Scoreboard $scoreboard): void
    {
        $scores = $scoreboard->getScores();

        foreach ($expected_scores as $row) {
            $team = $row['team'];
            $name = $team->getEffectiveName();

            $score = $scores[$team->getTeamid()];
            static::assertInstanceOf(TeamScore::class, $score);

            static::assertEquals($row['rank'], $score->rank, "Rank for '$name'");
            static::assertEquals($row['solved'], $score->numPoints, "# solved for '$name'");
            static::assertEquals($row['time'], $score->totalTime, "Total time for '$name'");
        }
    }

    protected function assertFTSMatch(array $expected_fts, Scoreboard $scoreboard): void
    {
        $matrix = $scoreboard->getMatrix();
        $teams = [];
        $probs = [];
        foreach ($scoreboard->getTeamsInDescendingOrder() as $team) {
            $teams[$team->getTeamid()] = $team;
        }
        foreach ($scoreboard->getProblems() as $prob) {
            $probs[$prob->getProbid()] = $prob;
        }

        $fts_probid2teamid = [];
        foreach ($expected_fts as $row) {
            $fts_probid2teamid[$row['problem']->getProbid()] = $row['team']->getTeamid();
        }

        foreach ($matrix as $teamid => $row) {
            $teamname = $teams[$teamid]->getEffectiveName();
            foreach ($row as $probid => $item) {
                $probname = $probs[$probid]->getShortname();

                static::assertInstanceOf(ScoreboardMatrixItem::class, $item);

                $expected = (@$fts_probid2teamid[$probid] === $teamid);
                static::assertEquals($expected, $item->isFirst,
                                    "Check FTS matches for team $teamname, problem $probname");
            }
        }
    }

    protected function recalcScoreCaches(): void
    {
        $this->em->flush();
        $this->ss->refreshCache($this->contest);
    }

    protected function createDefaultSubmissions(): void
    {
        $lang = $this->em->getRepository(Language::class)->find('cpp');

        $team = $this->teams[0];
        $this->createSubmission($lang, $this->problems[0], $team, 53*60+15.053, 'no-output');
        $this->createSubmission($lang, $this->problems[0], $team, 53*60+57.240, null);

        $team = $this->teams[1];
        $this->createSubmission($lang, $this->problems[0], $team, 61*60+00.000, 'compiler-error');
        $this->createSubmission($lang, $this->problems[0], $team, 69*60+00.000, 'correct');
        $this->createSubmission($lang, $this->problems[0], $team, 72*60+07.824, 'wrong-answer');
        $this->createSubmission($lang, $this->problems[1], $team, 72*60+39.733, 'wrong-answer');
        $this->createSubmission($lang, $this->problems[1], $team, 72*60+59.999, 'correct');
        $this->createSubmission($lang, $this->problems[1], $team, 79*60+00.000, 'wrong-answer');
        $this->createSubmission($lang, $this->problems[2], $team, 84*60+42, null);
        $this->createSubmission($lang, $this->problems[2], $team, 85*60+42, 'correct')
            ->setValid(false);

        // No submissions for $this->teams[2]
    }

    protected function createSubmission(
        Language $language,
        Problem $problem,
        Team $team,
        float $contest_time_seconds,
        ?string $verdict,
        bool $verified = false
    ): Submission {
        $cp = $this->em->getRepository(ContestProblem::class)->find(
            [ 'contest' => $this->contest, 'problem' => $problem ]
        );
        $submittime = $this->contest->getStarttime()+$contest_time_seconds;

        $submission = new Submission();
        $submission
            ->setContestProblem($cp)
            ->setLanguage($language)
            ->setTeam($team)
            ->setSubmittime($submittime);
        $this->em->persist($submission);

        if ($verdict!==null) {
            $judging = new Judging();
            $judging
                ->setSubmission($submission)
                ->setContest($this->contest)
                ->setStarttime($submittime + 5)
                ->setEndtime($submittime + 10)
                ->setResult($verdict);

            if ($verified) {
                $judging
                    ->setVerified(true)
                    ->setJuryMember(self::CONTEST_NAME.'-auto-verifier');
            }
            $this->em->persist($judging);

            $submission->addJudging($judging);
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
