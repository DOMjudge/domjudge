<?php declare(strict_types=1);

namespace App\Tests\Unit\Integration;

use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\JudgeTask;
use App\Entity\Problem;
use App\Entity\QueueTask;
use App\Entity\Team;
use App\Entity\TeamCategory;
use App\Entity\Testcase;
use App\Entity\TestcaseContent;
use App\Entity\User;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Service\ScoreboardService;
use App\Service\SubmissionService;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\FrameworkBundle\Test\TestBrowserToken;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class QueuetaskIntegrationTest extends KernelTestCase
{
    public const CONTEST_NAME = 'queuetest';
    public const NUM_PROBLEMS = 3;
    public const NUM_TEAMS = 3;

    private SubmissionService $submissionService;
    private ScoreboardService $scoreboardService;
    private ?EntityManagerInterface $em;

    /**
     * @var ConfigurationService|MockObject
     */
    private $config;
    private array $configValues;
    private Contest $contest;

    /** @var Problem[] */
    private array $problems;

    /** @var Team[] */
    private array $teams;

    protected function setUp(): void
    {
        self::bootKernel(['debug' => 0]);

        // Default configuration values:
        $this->configValues = [
            'verification_required' => false,
            'compile_penalty' => false,
            'penalty_time' => 20,
            'score_in_seconds' => false,
            'data_source' => 0,
            'sourcefiles_limit' => 1,
            'sourcesize_limit' => 1024*256,
        ];

        $this->config = $this->createMock(ConfigurationService::class);
        $this->config->expects($this->any())
            ->method('get')
            ->with($this->isType('string'))
            ->will($this->returnCallback([$this, 'getConfig']));

        $dj = self::getContainer()->get(DOMJudgeService::class);
        $this->em = self::getContainer()->get('doctrine')->getManager();

        $this->scoreboardService = new ScoreboardService(
            $this->em, $dj, $this->config,
            self::getContainer()->get(LoggerInterface::class),
            self::getContainer()->get(EventLogService::class)
        );
        $this->submissionService = new SubmissionService(
            $this->em,
            self::getContainer()->get(LoggerInterface::class),
            $dj,
            $this->config,
            self::getContainer()->get(EventLogService::class),
            $this->scoreboardService
        );

        // Create a contest, problems and teams for which to test the
        // scoreboard. These get deleted again in tearDown().
        $this->contest = new Contest();
        $this->contest
            ->setExternalid(self::CONTEST_NAME)
            ->setName(self::CONTEST_NAME)
            ->setShortname(self::CONTEST_NAME)
            ->setStarttimeString(date('Y') . '-01-01 10:00:00 Europe/Amsterdam')
            ->setActivatetimeString('-01:00')
            ->setEndtimeString('+02:00');
        $this->em->persist($this->contest);
        $this->em->flush();
        $this->em->refresh($this->contest);

        $category = $this->em->getRepository(TeamCategory::class)
            ->findOneBy(['sortorder' => 0]);
        for ($i = 0; $i < self::NUM_TEAMS; $i++) {
            $this->teams[$i] = new Team();
            $this->teams[$i]
                ->setName(self::CONTEST_NAME . ' team ' . $i)
                ->setCategory($category);
            $this->em->persist($this->teams[$i]);
        }
        $this->em->flush();

        for ($i = 0; $i < self::NUM_PROBLEMS; $i++) {
            $letter = chr(ord('a') + $i);
            $this->problems[$i] = new Problem();
            $this->problems[$i]
                ->setName(self::CONTEST_NAME . ' problem ' . $letter)
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

            for ($j = 0; $j < 12; $j++) {
                $tcContent = new TestcaseContent();
                $tcContent->setInput('nobody')
                    ->setOutput('cares');
                $this->em->persist($tcContent);
                $tc = new Testcase();
                $tc->setContent($tcContent)
                    ->setDescription('desc')
                    ->setMd5sumInput('abcd')
                    ->setMd5sumOutput('abcde')
                    ->setProblem($this->problems[$i])
                    ->setRank($j);
                $this->em->persist($tc);
                $this->problems[$i]->addTestcase($tc);
            }
        }
        $this->em->flush();

        // We need *some* user token set because some of these tests initialize static events,
        // which needs a user to check permissions.
        // Using the TestBrowserToken is the easiest way to do this.
        $user  = $this->em->getRepository(User::class)->findAll()[0];
        $token = new TestBrowserToken([], $user, 'main');
        if (method_exists($token, 'setAuthenticated')) {
            $token->setAuthenticated(true, false);
        }
        self::getContainer()->get('security.untracked_token_storage')->setToken($token);
    }

    protected function tearDown(): void
    {
        // Preserve the data for inspection if a test failed.
        if (!$this->hasFailed()) {
            // We need to reload the data here as they might become detached in the eventlog function.
            foreach ($this->teams as $team) {
                $team = $this->em->getRepository(Team::class)->find($team->getTeamid());
                $this->em->remove($team);
            }
            foreach ($this->problems as $problem) {
                $problem = $this->em->getRepository(Problem::class)->find($problem->getProbid());
                $this->em->remove($problem);
            }
            $contest = $this->em->getRepository(Contest::class)->find($this->contest);
            $this->em->remove($contest);
        }
        $this->em->flush();

        parent::tearDown();

        $this->em->close();
        $this->em = null; // avoid memory leaks
    }

    private function submit($time, ?Team $team = null, ?Problem $problem = null, string $source = 'team page'): QueueTask
    {
        $contest = $this->em->getRepository(Contest::class)->find($this->contest->getCid());
        $team = $team ?? $this->teams[0];
        $team = $this->em->getRepository(Team::class)->find($team->getTeamid());
        $problem = $problem ?? $this->problems[0];
        $problem = $this->em->getRepository(Problem::class)->find($problem->getProbid());
        $submission = $this->submissionService->submitSolution(
            $team, null, $problem, $contest, 'c',
            [new UploadedFile(__FILE__, "foo.c", null, null, true)], $source,
            null, null, null, null, $time, $message);
        self::assertNotNull($submission, 'from submitSolution: ' . $message);

        $judging = $submission->getJudgings()->get(0);
        self::assertNotNull($judging);

        $queuetask = $this->em->getRepository(QueueTask::class)->findOneBy(['jobid' => $judging->getJudgingid()]);
        self::assertNotNull($queuetask);

        return $queuetask;
    }

    public function testNormalSubmissions(): void
    {
        $time = Utils::now();

        // The first submission of a team (submitted "now") gets a priority of "~now".
        $firstTask = $this->submit($time);
        self::assertEquals((int)$time, $firstTask->getTeamPriority());

        // The second submission of a team (also submitted "now") gets a penalty of 60s.
        $secondTask = $this->submit($time);
        self::assertEquals((int)$time+60, $secondTask->getTeamPriority());

        // Move clock by 5s.
        $time += 5;
        // The first submission of a second team is based on the current time.
        $secondTeamFirstTask = $this->submit($time, $this->teams[1]);
        self::assertEquals((int)$time, $secondTeamFirstTask->getTeamPriority());

        // Move clock by another 5s.
        $time += 5;
        // The second submission of the second team gets a penalty of 60s.
        $secondTeamSecondTask = $this->submit($time, $this->teams[1]);
        self::assertEquals((int)$time+60, $secondTeamSecondTask->getTeamPriority());

        // Now, move clock 121s.
        $time += 121;
        // The third submission of the second team doesn't get a penalty. While there are still the first two
        // submissions in the queue of that team, they didn't submit in a while so this one doesn't need to be penalized
        // anymore.
        $secondTeamThirdTask = $this->submit($time, $this->teams[1]);
        self::assertEquals((int)$time, $secondTeamThirdTask->getTeamPriority());

        // Same time, different problem. Second gets penalty.
        $thirdTeamFirstTask = $this->submit($time, $this->teams[2], $this->problems[0]);
        $thirdTeamSecondTask = $this->submit($time, $this->teams[2], $this->problems[1]);
        self::assertEquals((int)$time, $thirdTeamFirstTask->getTeamPriority());
        self::assertEquals((int)$time+60, $thirdTeamSecondTask->getTeamPriority());

        // Move clock by 5s and assume these two submissions are judged. Submit again without penalty.
        $time += 5;
        $this->em->remove($thirdTeamSecondTask);
        // This needs to be reloaded because of eventlog stuff happening in one of the earlier submits.
        $thirdTeamFirstTask = $this->em->getRepository(QueueTask::class)->find($thirdTeamFirstTask->getQueueTaskid());
        $this->em->remove($thirdTeamFirstTask);
        $this->em->flush();
        $thirdTeamThirdTask = $this->submit($time, $this->teams[2]);
        self::assertEquals((int)$time, $thirdTeamThirdTask->getTeamPriority());
    }

    public function testRogueTeam(): void
    {
        $time = Utils::now();
        $startTimeAsInt = (int)$time;

        // One team submitting every five seconds, another every 90s. The first behavior is punishable,
        // the second is fine. This test is basically also the worst case for the team since we don't make progress on
        // their submissions at all.
        $normalTeamPrios = [];
        $rogueTeamPrios = [];
        for ($i = 0; $i <= 180; $i+=5, $time+=5) {
            $rogueTeamPrios[] = ($this->submit($time, $this->teams[0])->getTeamPriority() - $startTimeAsInt);
            if (($i%90) == 0) {
                $normalTeamPrios[] = ($this->submit($time, $this->teams[1])->getTeamPriority() - $startTimeAsInt);
            }
        }
        self::assertEquals([
            // The first 12 submissions happen within one minute, so each gets an extra minute penalty (on top of the 5s
            // that are spaced out by).
             0,
             5  +  1*60,
             10 +  2*60,
             15 +  3*60,
             20 +  4*60,
             25 +  5*60,
             30 +  6*60,
             35 +  7*60,
             40 +  8*60,
             45 +  9*60,
             50 + 10*60,
             55 + 11*60,
             60 + 12*60,
            // The next submission happens at the 65s mark, so when the first one fell out of the window that we care
            // about. So, the counter is not increased.
             65 + 12*60,
            // Then the counter increases again for a while since the submissions are penalized.
             70 + 13*60,
             75 + 14*60,
             80 + 15*60,
             85 + 16*60,
             90 + 17*60,
             95 + 18*60,
            100 + 19*60,
            105 + 20*60,
            110 + 21*60,
            115 + 22*60,
            120 + 23*60,
            125 + 24*60,
            // Now the second submission, placed at the virtual 65s mark falls out of the window.
            130 + 24*60,
            // Afterwards we increase it again.
            135 + 25*60,
            140 + 26*60,
            145 + 27*60,
            150 + 28*60,
            155 + 29*60,
            160 + 30*60,
            165 + 31*60,
            170 + 32*60,
            175 + 33*60,
            180 + 34*60,
        ], $rogueTeamPrios);
        // Other teams are not affected by this behavior.
        self::assertEquals([0, 90, 180], $normalTeamPrios);
    }

    public function testSubmittingAsInTheComment(): void
    {
        // See comment in DOMjudgeService.php where we describe the queue behavior.
        $time = Utils::now();

        // Submit three times.
        $first = $this->submit($time);
        self::assertEquals((int)$time, $first->getTeamPriority());
        $second = $this->submit($time);
        self::assertEquals((int)$time+60, $second->getTeamPriority());
        $third = $this->submit($time);
        self::assertEquals((int)$time+120, $third->getTeamPriority());

        // Judge the first two after 5s.
        $time += 5;
        $this->em->remove($this->em->getRepository(QueueTask::class)->find($first->getQueueTaskid()));
        $this->em->remove($this->em->getRepository(QueueTask::class)->find($second->getQueueTaskid()));
        $this->em->flush();

        // And submit again. In theory, this would get $time+60 since there is only submission of that team in the
        // queue, but we want to judge them in order, so we hit the special case.
        $fourth = $this->submit($time);
        self::assertEquals($third->getTeamPriority()+1, $fourth->getTeamPriority());
    }

    public function testPriorities(): void
    {
        $time = Utils::now();

        $normal = $this->submit($time, $this->teams[0], null, 'team page');
        self::assertEquals((int)$time, $normal->getTeamPriority());
        self::assertEquals(JudgeTask::PRIORITY_DEFAULT, $normal->getPriority());

        $api = $this->submit($time, $this->teams[1], null, 'api');
        self::assertEquals((int)$time, $api->getTeamPriority());
        self::assertEquals(JudgeTask::PRIORITY_DEFAULT, $api->getPriority());

        $problem_import = $this->submit($time, $this->teams[2], null, 'problem import');
        self::assertEquals((int)$time, $problem_import->getTeamPriority());
        self::assertEquals(JudgeTask::PRIORITY_LOW, $problem_import->getPriority());
    }

    public function setConfig(string $name, $value): void
    {
        $this->configValues[$name] = $value;
    }

    public function getConfig(string $name)
    {
        if (!array_key_exists($name, $this->configValues)) {
            throw new \Exception("No configuration value set for '$name'");
        }

        return $this->configValues[$name];
    }
}
