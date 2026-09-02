<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\API;

use App\Entity\Contest;
use App\Entity\InternalError;
use App\Entity\JudgeTask;
use App\Entity\Judgehost;
use App\Entity\Judging;
use App\Entity\JudgingRun;
use App\Entity\Problem;
use App\Entity\QueueTask;
use App\Entity\Submission;
use App\Entity\SubmissionSource;
use App\Entity\Team;
use App\Entity\Version;
use App\Service\SubmissionService;
use App\Tests\Unit\BaseTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Drives the judgehosts API the way a judgedaemon does: register, fetch work, report compile
 * output and run results, report an internal error, report tool versions.
 *
 * These endpoints take form-encoded parameters rather than JSON, so this class talks to the
 * client directly instead of through the JSON helpers in API\BaseTestCase.
 */
class JudgehostWorkflowTest extends BaseTestCase
{
    private const HOSTNAME = 'test-judgehost';

    private ?string $judgehostPassword = null;

    protected function setUp(): void
    {
        parent::setUp();

        // fetch-work hands out the globally highest-priority queue task, so a database that
        // already has queued work would hand these tests somebody else's job.
        $this->em()->getConnection()->executeStatement('DELETE FROM queuetask');
    }

    private function em(): EntityManagerInterface
    {
        // Fetched per call: the kernel is rebooted between requests, so an instance cached in
        // setUp() would belong to a stale container.
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    /**
     * An entity manager with an empty identity map: entities loaded before a request are stale
     * once the request has written through its own manager.
     */
    private function freshEm(): EntityManagerInterface
    {
        $em = $this->em();
        $em->clear();

        return $em;
    }

    /**
     * The judgehost user's password lives in etc/restapi.secret, as the fourth field of the
     * first non-comment line. This mirrors DataFixtures\DefaultData\UserFixture.
     */
    private function judgehostPassword(): string
    {
        if ($this->judgehostPassword !== null) {
            return $this->judgehostPassword;
        }

        $file = sprintf('%s/restapi.secret', static::getContainer()->getParameter('domjudge.etcdir'));
        foreach (file($file) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            $items = preg_split('/\s+/', $line);
            if (count($items) === 4) {
                return $this->judgehostPassword = $items[3];
            }
        }

        throw new Exception("Could not read the judgehost password from $file");
    }

    /**
     * @param array<string, string|int> $parameters
     */
    private function request(string $method, string $uri, array $parameters = [], int $expectedStatus = 200): mixed
    {
        $this->client->request($method, '/api' . $uri, $parameters, [], [
            'PHP_AUTH_USER' => 'judgehost',
            'PHP_AUTH_PW' => $this->judgehostPassword(),
        ]);

        $response = $this->client->getResponse();
        self::assertSame($expectedStatus, $response->getStatusCode(), (string)$response->getContent());

        return json_decode((string)$response->getContent(), true);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function registerJudgehost(string $hostname = self::HOSTNAME): array
    {
        return (array)$this->request('POST', '/judgehosts', ['hostname' => $hostname]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchWork(string $hostname = self::HOSTNAME): array
    {
        return (array)$this->request(
            'POST',
            '/judgehosts/fetch-work',
            ['hostname' => $hostname],
        );
    }

    private function addSubmission(string $team = 'DOMjudge', string $problem = 'hello'): Submission
    {
        $em = $this->em();
        $contest = $em->getRepository(Contest::class)->findOneBy(['shortname' => 'demo']);
        $teamEntity = $em->getRepository(Team::class)->findOneBy(['name' => $team]);
        $problemEntity = $em->getRepository(Problem::class)->findOneBy(['externalid' => $problem]);

        $message = null;
        return self::getContainer()->get(SubmissionService::class)->submitSolution(
            $teamEntity, null, $problemEntity, $contest, 'c',
            [new UploadedFile(__FILE__, 'foo.c', null, null, true)],
            SubmissionSource::UNKNOWN, null, null, null, null, null, $message
        );
    }

    /**
     * Register, submit and take the resulting batch of judge tasks.
     *
     * @return array<int, array<string, mixed>> the claimed judge tasks
     */
    private function claimWorkForOneSubmission(): array
    {
        $this->registerJudgehost();
        $this->addSubmission();

        $tasks = $this->fetchWork();
        self::assertNotEmpty($tasks, 'expected fetch-work to hand out judge tasks');
        // A real judge task carries type "judging_run"; "try_again" is the marker fetch-work
        // returns when it lost a race for the queue task.
        self::assertNotSame('try_again', $tasks[0]['type'] ?? null, 'fetch-work handed out no real work');

        return $tasks;
    }

    /**
     */
    private function reportRun(int $judgeTaskId, string $runResult): mixed
    {
        return $this->request(
            'POST',
            sprintf('/judgehosts/add-judging-run/%s/%d', self::HOSTNAME, $judgeTaskId),
            [
                'runresult' => $runResult,
                'runtime' => '0.01',
                'output_run' => base64_encode('run'),
                'output_diff' => base64_encode('diff'),
                'output_error' => base64_encode(''),
                'output_system' => base64_encode(''),
                'metadata' => base64_encode(''),
                // Mandatory, and a real judgedaemon always sends them.
                'start_time' => (string)(microtime(true) - 1),
                'end_time' => (string)microtime(true),
            ]
        );
    }

    /**
     * @param array<string, string|int> $parameters
     */
    private function updateJudging(int $judgeTaskId, array $parameters): void
    {
        // updateJudgingAction returns void, so Symfony answers 204 No Content.
        $this->request(
            'PUT',
            sprintf('/judgehosts/update-judging/%s/%d', self::HOSTNAME, $judgeTaskId),
            $parameters,
            204
        );
    }

    private function judgingForTask(int $judgeTaskId): Judging
    {
        $run = $this->freshEm()->getRepository(JudgingRun::class)->findOneBy(['judgetaskid' => $judgeTaskId]);
        self::assertNotNull($run, "no judging run for judgetask $judgeTaskId");

        return $run->getJudging();
    }

    public function testRegisterCreatesTheJudgehost(): void
    {
        self::assertNull($this->em()->getRepository(Judgehost::class)->findOneBy(['hostname' => self::HOSTNAME]));

        $unfinished = $this->registerJudgehost();
        self::assertSame([], $unfinished, 'a fresh judgehost has no unfinished judgings to hand back');

        $judgehost = $this->freshEm()->getRepository(Judgehost::class)->findOneBy(['hostname' => self::HOSTNAME]);
        self::assertNotNull($judgehost);
        self::assertFalse($judgehost->getHidden());
    }

    public function testFetchWorkClaimsTheTasksAndStartsTheJudging(): void
    {
        $tasks = $this->claimWorkForOneSubmission();

        $em = $this->freshEm();
        $judgehost = $em->getRepository(Judgehost::class)->findOneBy(['hostname' => self::HOSTNAME]);

        foreach ($tasks as $task) {
            $judgeTask = $em->getRepository(JudgeTask::class)->find($task['judgetaskid']);
            self::assertNotNull($judgeTask);
            self::assertSame(
                $judgehost->getJudgehostid(),
                $judgeTask->getJudgehost()?->getJudgehostid(),
                'a handed-out judge task must be claimed by the requesting judgehost'
            );
            self::assertNotNull($judgeTask->getStarttime(), 'a handed-out judge task must have a start time');
        }

        $judging = $this->judgingForTask((int)$tasks[0]['judgetaskid']);
        self::assertNotNull($judging->getStarttime(), 'handing out work must start the judging');

        $queueTask = $em->getRepository(QueueTask::class)->findOneBy(['judging' => $judging->getJudgingid()]);
        if ($queueTask !== null) {
            self::assertNotNull($queueTask->getStartTime(), 'the queue task must be marked as being worked on');
        }
    }

    public function testFetchWorkDoesNotHandTheSameTaskToTwoJudgehosts(): void
    {
        $tasks = $this->claimWorkForOneSubmission();
        $claimed = array_column($tasks, 'judgetaskid');

        $this->registerJudgehost('second-judgehost');
        $alsoClaimed = array_column($this->fetchWork('second-judgehost'), 'judgetaskid');

        self::assertSame(
            [],
            array_intersect($claimed, $alsoClaimed),
            'a judge task must never be handed to two judgehosts at once'
        );
    }

    public function testUpdateJudgingStoresCompileOutput(): void
    {
        $tasks = $this->claimWorkForOneSubmission();
        $judgeTaskId = (int)$tasks[0]['judgetaskid'];

        $this->updateJudging($judgeTaskId, [
            'compile_success' => 1,
            'output_compile' => base64_encode('compiling went fine'),
            'compile_metadata' => base64_encode('meta'),
        ]);

        $judging = $this->judgingForTask($judgeTaskId);
        self::assertSame('compiling went fine', $judging->getOutputCompile(true));
        self::assertNull($judging->getResult(), 'a successful compile must not decide the verdict');
    }

    public function testUpdateJudgingWithCompileErrorSetsTheVerdict(): void
    {
        $tasks = $this->claimWorkForOneSubmission();
        $judgeTaskId = (int)$tasks[0]['judgetaskid'];

        $this->updateJudging($judgeTaskId, [
            'compile_success' => 0,
            'output_compile' => base64_encode('syntax error'),
            'compile_metadata' => base64_encode('meta'),
        ]);

        $judging = $this->judgingForTask($judgeTaskId);
        self::assertSame(Judging::RESULT_COMPILER_ERROR, $judging->getResult());
        self::assertNotNull($judging->getEndtime(), 'a compile error finishes the judging');
        self::assertSame('syntax error', $judging->getOutputCompile(true));

        $remaining = $this->freshEm()->getRepository(JudgeTask::class)->findBy([
            'jobid' => $judging->getJudgingid(),
            'valid' => true,
        ]);
        self::assertSame([], $remaining, 'a compile error must invalidate the remaining judge tasks');
    }

    public function testJudgingIsCompletedOnceEveryRunIsReported(): void
    {
        $tasks = $this->claimWorkForOneSubmission();

        foreach ($tasks as $task) {
            $this->reportRun((int)$task['judgetaskid'], 'correct');
        }

        $judging = $this->judgingForTask((int)$tasks[0]['judgetaskid']);
        self::assertSame(Judging::RESULT_CORRECT, $judging->getResult());
        self::assertNotNull($judging->getEndtime());
    }

    public function testInternalErrorDisablesTheJudgehostAndGivesBackWork(): void
    {
        $tasks = $this->claimWorkForOneSubmission();
        $judgeTaskId = (int)$tasks[0]['judgetaskid'];
        $judging = $this->judgingForTask($judgeTaskId);

        $this->request('POST', '/judgehosts/internal-error', [
            'description' => 'runguard crashed',
            'judgehostlog' => base64_encode('a log'),
            'disabled' => json_encode(['kind' => 'judgehost', 'hostname' => self::HOSTNAME]),
            'judgetaskid' => $judgeTaskId,
            'hostname' => self::HOSTNAME,
            'cid' => $judging->getContest()->getCid(),
        ]);

        $em = $this->freshEm();
        $error = $em->getRepository(InternalError::class)->findOneBy(['description' => 'runguard crashed']);
        self::assertNotNull($error, 'an internal error must be recorded');
        self::assertSame('open', $error->getStatus());

        $judgehost = $em->getRepository(Judgehost::class)->findOneBy(['hostname' => self::HOSTNAME]);
        self::assertFalse($judgehost->getEnabled(), 'reporting a judgehost-kind internal error disables it');

        // The unfinished work must be released so another judgehost can pick it up.
        $stillOurs = $em->getRepository(JudgeTask::class)->findBy([
            'jobid' => $judging->getJudgingid(),
            'judgehost' => $judgehost,
            'valid' => true,
        ]);
        self::assertSame([], $stillOurs, 'unfinished judge tasks must be given back');
    }

    /**
     * An internal error blamed on a compile script takes every unclaimed judge task that uses
     * that script out of the queue and flags their judgings.
     */
    public function testInternalErrorForACompileScriptInvalidatesQueuedJudgeTasks(): void
    {
        $tasks = $this->claimWorkForOneSubmission();
        $judgeTaskId = (int)$tasks[0]['judgetaskid'];
        $compileScriptId = $tasks[0]['compile_script_id'];

        // A second submission in the same language gives us a judge task that uses the same
        // compile script and is still unclaimed, which is what this branch acts on.
        $queued = $this->addSubmission();
        $queuedJudgingId = $queued->getJudgings()->first()->getJudgingid();

        $this->request('POST', '/judgehosts/internal-error', [
            'description' => 'compile script is broken',
            'judgehostlog' => base64_encode('a log'),
            'disabled' => json_encode([
                'kind' => 'compile_script',
                'compile_script_id' => $compileScriptId,
            ]),
            'judgetaskid' => $judgeTaskId,
            'hostname' => self::HOSTNAME,
        ]);

        $em = $this->freshEm();

        $error = $em->getRepository(InternalError::class)
            ->findOneBy(['description' => 'compile script is broken']);
        self::assertNotNull($error, 'an internal error must be recorded');
        // The immutable executable is remapped to its mutable counterpart before recording.
        self::assertSame('executable', $error->getDisabled()['kind']);

        $stillQueued = $em->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM judgetask'
            . ' WHERE compile_script_id = :id AND judgehostid IS NULL AND valid = 1',
            ['id' => $compileScriptId]
        );
        self::assertSame(0, (int)$stillQueued, 'unclaimed judge tasks for a broken script must be invalidated');

        $queuedJudging = $em->getRepository(Judging::class)->find($queuedJudgingId);
        self::assertNotNull(
            $queuedJudging->getInternalError(),
            'the judging of an invalidated judge task must be linked to the internal error'
        );
    }

    public function testCheckVersionsRecordsTheReportedVersion(): void
    {
        $tasks = $this->claimWorkForOneSubmission();
        $judgeTaskId = (int)$tasks[0]['judgetaskid'];

        $this->request('PUT', sprintf('/judgehosts/check_versions/%d', $judgeTaskId), [
            'hostname' => self::HOSTNAME,
            'compiler' => base64_encode('gcc 14.1.0'),
            'runner' => base64_encode('gcc 14.1.0'),
        ]);

        $em = $this->freshEm();
        $judgeTask = $em->getRepository(JudgeTask::class)->find($judgeTaskId);
        $version = $judgeTask->getVersion();

        self::assertNotNull($version, 'the judge task must be linked to the reported version');
        self::assertTrue($version->getActive());
        self::assertSame('gcc 14.1.0', $version->getCompilerVersion());
        self::assertSame('gcc 14.1.0', $version->getRunnerVersion());
    }

    public function testCheckVersionsSupersedesAPreviousVersion(): void
    {
        $tasks = $this->claimWorkForOneSubmission();
        $judgeTaskId = (int)$tasks[0]['judgetaskid'];
        $url = sprintf('/judgehosts/check_versions/%d', $judgeTaskId);

        $this->request('PUT', $url, [
            'hostname' => self::HOSTNAME,
            'compiler' => base64_encode('gcc 13.0.0'),
        ]);
        $this->request('PUT', $url, [
            'hostname' => self::HOSTNAME,
            'compiler' => base64_encode('gcc 14.1.0'),
        ]);

        $em = $this->freshEm();
        $judgehost = $em->getRepository(Judgehost::class)->findOneBy(['hostname' => self::HOSTNAME]);
        $active = $em->getRepository(Version::class)->findBy(['judgehost' => $judgehost, 'active' => true]);

        self::assertCount(1, $active, 'exactly one version stays active per judgehost and language');
        self::assertSame('gcc 14.1.0', $active[0]->getCompilerVersion());
    }
}
