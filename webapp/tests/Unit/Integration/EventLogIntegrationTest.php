<?php declare(strict_types=1);

namespace App\Tests\Unit\Integration;

use App\Entity\Clarification;
use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\Judging;
use App\Entity\JudgingRun;
use App\Entity\Language;
use App\Entity\Problem;
use App\Entity\Submission;
use App\Entity\Team;
use App\Entity\Testcase;
use App\Entity\User;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Service\ImportProblemService;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use ZipArchive;

class EventLogIntegrationTest extends KernelTestCase
{
    private TestHandler $logHandler;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        $this->logHandler = new TestHandler();
        /** @var Logger $logger */
        $logger = static::getContainer()->get(LoggerInterface::class);
        $logger->pushHandler($this->logHandler);
    }

    protected function tearDown(): void
    {
        /** @var Logger $logger */
        $logger = static::getContainer()->get(LoggerInterface::class);
        $logger->popHandler();
        parent::tearDown();
    }

    private function assertNoFallbackWarning(string $context = ''): void
    {
        self::assertFalse(
            $this->logHandler->hasWarningThatContains('fetchEndpointData falling back'),
            $context !== '' ? $context : 'Direct dispatch must be used, not internalApiRequest fallback'
        );
        self::assertFalse(
            $this->logHandler->hasWarningThatContains('fetchEndpointData route matching failed'),
            $context !== '' ? $context : 'Route matching must succeed'
        );
    }

    public function testLoggingEventDoesNotDetachManagedEntities(): void
    {
        $container = static::getContainer();

        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        /** @var EventLogService $eventLogService */
        $eventLogService = $container->get(EventLogService::class);

        $contest = $em->getRepository(Contest::class)->findOneBy(['shortname' => 'demo']);
        $team = $em->getRepository(Team::class)->findOneBy(['name' => 'Example teamname']);
        $problem = $em->getRepository(Problem::class)->findOneBy(['externalid' => 'hello']);

        self::assertNotNull($contest);
        self::assertNotNull($team);
        self::assertNotNull($problem);

        self::assertTrue($em->contains($contest), 'Contest should be managed before logging');
        self::assertTrue($em->contains($team), 'Team should be managed before logging');
        self::assertTrue($em->contains($problem), 'Problem should be managed before logging');

        // Log an update event for the team
        $eventLogService->log(
            'teams',
            $team->getTeamid(),
            EventLogService::ACTION_UPDATE,
            $contest->getCid()
        );

        // Previously, REST controller actions called $em->clear() during internal sub-requests,
        // detaching all managed entities. With direct dispatch + HINT_REFRESH, entities remain managed.
        self::assertTrue($em->contains($contest), 'Contest should remain managed after logging');
        self::assertTrue($em->contains($team), 'Team should remain managed after logging');
        self::assertTrue($em->contains($problem), 'Problem should remain managed after logging');
    }

    public function testFetchEndpointDataDirectly(): void
    {
        $container = static::getContainer();

        /** @var EventLogService $eventLogService */
        $eventLogService = $container->get(EventLogService::class);

        $this->logHandler->clear();
        $teamData = $eventLogService->fetchEndpointData('/contests/demo/teams');
        $this->assertNoFallbackWarning('Direct dispatch must be used for /contests/demo/teams');
        self::assertIsArray($teamData);
        self::assertNotEmpty($teamData);
        self::assertArrayHasKey('id', $teamData[0]);
        self::assertArrayHasKey('name', $teamData[0]);

        $this->logHandler->clear();
        $contestData = $eventLogService->fetchEndpointData('/contests/demo');
        $this->assertNoFallbackWarning('Direct dispatch must be used for /contests/demo');
        self::assertIsArray($contestData);
        self::assertSame('demo', $contestData['id']);

        $this->logHandler->clear();
        $problemData = $eventLogService->fetchEndpointData('/contests/demo/problems');
        $this->assertNoFallbackWarning('Direct dispatch must be used for /contests/demo/problems');
        self::assertIsArray($problemData);
        self::assertNotEmpty($problemData);
        self::assertArrayHasKey('ordinal', $problemData[0]);
    }

    public function testEndpointDataParityWithSubRequest(): void
    {
        $container = static::getContainer();

        /** @var EventLogService $eventLogService */
        $eventLogService = $container->get(EventLogService::class);
        /** @var DOMJudgeService $dj */
        $dj = $container->get(DOMJudgeService::class);

        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $adminUser = $em->getRepository(User::class)->findOneBy(['username' => 'admin']);
        self::assertNotNull($adminUser);

        // Seed non-empty submission, judgement, run, and clarification rows so parity is verified on real payloads.
        $contest = $em->getRepository(Contest::class)->findOneBy(['shortname' => 'demo']);
        $team = $em->getRepository(Team::class)->findOneBy(['name' => 'Example teamname']);
        $language = $em->getRepository(Language::class)->findOneBy(['externalid' => 'cpp']);
        /** @var ContestProblem $contestProblem */
        $contestProblem = $contest->getProblems()->first();
        /** @var Testcase $testcase */
        $testcase = $contestProblem->getProblem()->getTestcases()->first();
        $submittime = (float)$contest->getStarttime() + 60.0;

        $submission = (new Submission())
            ->setExternalid('parity-sub-1')
            ->setContest($contest)
            ->setTeam($team)
            ->setUser($adminUser)
            ->setContestProblem($contestProblem)
            ->setLanguage($language)
            ->setSubmittime($submittime);
        $judging = (new Judging())
            ->setContest($contest)
            ->setStarttime($submittime)
            ->setEndtime($submittime + 2.0)
            ->setValid(true)
            ->setSubmission($submission)
            ->setResult(Judging::RESULT_CORRECT);
        $judgingRun = (new JudgingRun())
            ->setJudging($judging)
            ->setTestcase($testcase)
            ->setStarttime($submittime)
            ->setEndtime($submittime + 1.0)
            ->setRunresult(Judging::RESULT_CORRECT)
            ->setRuntime(0.05);
        $clarification = (new Clarification())
            ->setExternalid('parity-clar-1')
            ->setContest($contest)
            ->setSubmittime($submittime)
            ->setSender($team)
            ->setProblem($contestProblem->getProblem())
            ->setBody('Parity test question?')
            ->setAnswered(false);

        $submission->addJudging($judging);
        $judging->addRun($judgingRun);
        $testcase->addJudgingRun($judgingRun);
        $em->persist($submission);
        $em->persist($judging);
        $em->persist($judgingRun);
        $em->persist($clarification);
        $em->flush();

        $endpoints = [
            '/contests/demo',
            '/contests/demo/teams',
            '/contests/demo/teams/' . $team->getExternalid(),
            '/contests/demo/problems',
            '/contests/demo/problems/hello',
            '/contests/demo/clarifications',
            '/contests/demo/clarifications/parity-clar-1',
            '/contests/demo/judgement-types',
            '/contests/demo/judgement-types/AC',
            '/contests/demo/groups',
            '/contests/demo/organizations',
            '/contests/demo/languages',
            '/contests/demo/accounts',
            '/contests/demo/accounts/' . $adminUser->getExternalid(),
            '/contests/demo/submissions',
            '/contests/demo/submissions/parity-sub-1',
            '/contests/demo/judgements',
            '/contests/demo/judgements/' . $judging->getJudgingid(),
            '/contests/demo/runs',
            '/contests/demo/runs/' . $judgingRun->getRunid(),
            '/contests/demo/awards',
        ];

        $dj->withAllRoles(function () use ($eventLogService, $dj, $endpoints): void {
            foreach ($endpoints as $url) {
                $this->logHandler->clear();
                $direct = $eventLogService->fetchEndpointData($url);
                $this->assertNoFallbackWarning("Direct dispatch must be used for $url, not internalApiRequest fallback");
                $subRaw = $dj->internalApiRequest($url);
                self::assertNotNull($subRaw, "Sub-request for $url should not return null");
                $sub = Utils::jsonDecode($subRaw);

                self::assertSame(
                    $sub,
                    $direct,
                    "Direct fetchEndpointData and internalApiRequest must be identical for $url"
                );
            }

            foreach (['/contests/demo/submissions', '/contests/demo/judgements', '/contests/demo/runs', '/contests/demo/clarifications'] as $nonEmptyUrl) {
                self::assertNotEmpty($eventLogService->fetchEndpointData($nonEmptyUrl), "$nonEmptyUrl must be non-empty in parity test");
            }

            // Invalid contest on accounts endpoint must return null just like other contest-scoped endpoints
            self::assertNull($eventLogService->fetchEndpointData('/contests/nonexistent-contest-xyz/accounts'));

            // Test strict mode parity
            $this->logHandler->clear();
            $directStrict = $eventLogService->fetchEndpointData('/contests/demo/teams', ['strict' => 1]);
            $this->assertNoFallbackWarning('Strict mode fetch must use direct dispatch');
            $subStrictRaw = $dj->internalApiRequest('/contests/demo/teams', Request::METHOD_GET, ['strict' => '1']);
            $subStrict = Utils::jsonDecode($subStrictRaw);
            self::assertSame($subStrict, $directStrict, 'Strict mode parity for teams must match');

            // Verify null fields are preserved in direct data (e.g. location in team data)
            $this->logHandler->clear();
            $teamData = $eventLogService->fetchEndpointData('/contests/demo/teams');
            $this->assertNoFallbackWarning('Direct dispatch must be used');
            self::assertNotEmpty($teamData);
            self::assertArrayHasKey('location', $teamData[0]);
            self::assertNull($teamData[0]['location'], 'Null field should be preserved as null, not omitted');
        }, $adminUser);
    }

    public function testFetchEndpointDataUnderPostContext(): void
    {
        $container = static::getContainer();

        /** @var EventLogService $eventLogService */
        $eventLogService = $container->get(EventLogService::class);
        /** @var RouterInterface $router */
        $router = $container->get('router');
        /** @var DOMJudgeService $dj */
        $dj = $container->get(DOMJudgeService::class);
        $em = $container->get(EntityManagerInterface::class);
        $adminUser = $em->getRepository(User::class)->findOneBy(['username' => 'admin']);

        // Simulate master request being a POST (as when judgehosts POST results or users submit)
        $router->getContext()->setMethod(Request::METHOD_POST);

        $dj->withAllRoles(function () use ($eventLogService): void {
            // Hot paths that have POST routes at the same path
            $this->logHandler->clear();
            $submissions = $eventLogService->fetchEndpointData('/contests/demo/submissions');
            $this->assertNoFallbackWarning('Submissions must use direct dispatch under POST context');
            self::assertIsArray($submissions);

            // Path with NO POST route (would throw MethodNotAllowedException if router method wasn't reset)
            $this->logHandler->clear();
            $judgements = $eventLogService->fetchEndpointData('/contests/demo/judgements');
            $this->assertNoFallbackWarning('Judgements must use direct dispatch under POST context');
            self::assertIsArray($judgements);

            $this->logHandler->clear();
            $problems = $eventLogService->fetchEndpointData('/contests/demo/problems');
            $this->assertNoFallbackWarning('Problems must use direct dispatch under POST context');
            self::assertIsArray($problems);
            self::assertNotEmpty($problems);
        }, $adminUser);

        // Verify the original router context method was restored in finally block
        self::assertSame('POST', $router->getContext()->getMethod());
    }

    public function testFetchEndpointDataFallsBackOnUnknownRoute(): void
    {
        $container = static::getContainer();
        /** @var EventLogService $eventLogService */
        $eventLogService = $container->get(EventLogService::class);

        $this->logHandler->clear();
        $eventLogService->fetchEndpointData('/contests/demo/nonexistent-route-xyz');
        self::assertTrue(
            $this->logHandler->hasWarningThatContains('fetchEndpointData falling back'),
            'Unknown route should trigger fallback warning'
        );
    }

    public function testFetchEndpointDataRefreshesEmptyFetchJoinedCollection(): void
    {
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        /** @var EventLogService $eventLogService */
        $eventLogService = $container->get(EventLogService::class);
        /** @var DOMJudgeService $dj */
        $dj = $container->get(DOMJudgeService::class);

        $adminUser = $em->getRepository(User::class)->findOneBy(['username' => 'admin']);
        $contest = $em->getRepository(Contest::class)->findOneBy(['shortname' => 'demo']);
        $team = $em->getRepository(Team::class)->findOneBy(['name' => 'Example teamname']);
        self::assertNotNull($adminUser);
        self::assertNotNull($contest);
        self::assertNotNull($team);

        $contest->setOpenToAllTeams(true);
        $em->flush();

        // Initialize the inverse-side collection on the managed Team entity while it is non-empty.
        $initialCategories = $team->getCategories()->toArray();
        self::assertNotEmpty($initialCategories, 'Team must start with at least one category');

        // Delete the owning TeamCategory rows (as Jury\TeamCategoryController::deleteAction does),
        // leaving $team->categories initialized in memory while the join table now has 0 rows.
        foreach ($initialCategories as $category) {
            $em->remove($category);
        }
        $em->flush();

        $dj->withAllRoles(function () use ($eventLogService, $team): void {
            $data = $eventLogService->fetchEndpointData('/contests/demo/teams/' . $team->getExternalid());
            self::assertNotNull($data);
            self::assertSame([], $data['group_ids'], 'Fetch-joined categories collection becoming empty must serialize as []');
        }, $adminUser);
    }

    public function testImportZippedProblemWithJurySolutionIntoDisabledContest(): void
    {
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        /** @var ImportProblemService $importProblemService */
        $importProblemService = $container->get(ImportProblemService::class);
        /** @var DOMJudgeService $dj */
        $dj = $container->get(DOMJudgeService::class);

        $adminUser = $em->getRepository(User::class)->findOneBy(['username' => 'admin']);
        $contest = $em->getRepository(Contest::class)->findOneBy(['shortname' => 'demo']);
        self::assertNotNull($adminUser);
        self::assertNotNull($contest);

        // Disable the contest: ProblemController::getContestId() throws NotFoundHttpException during
        // EventLogService::log('problem', ...), so ImportProblemService cannot rely on EventLogService
        // refreshing $problem->testcases as a side effect.
        $contest->setEnabled(false);
        $em->flush();

        $tmpZip = tempnam(sys_get_temp_dir(), 'dj-disabled-import-') . '.zip';
        $zip = new ZipArchive();
        self::assertSame(true, $zip->open($tmpZip, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $zip->addFromString('problem.yaml', "name: Disabled Contest Import Test\n");
        $zip->addFromString('domjudge-problem.ini', "short-name=DISIMP\ntimelimit=1\n");
        $zip->addFromString('data/secret/1.in', "1\n");
        $zip->addFromString('data/secret/1.ans', "42\n");
        $zip->addFromString('submissions/accepted/sol.c', "#include <stdio.h>\nint main(void) { puts(\"42\"); return 0; }\n");
        $zip->close();

        $reopenedZip = new ZipArchive();
        self::assertSame(true, $reopenedZip->open($tmpZip));

        try {
            $dj->withAllRoles(function () use ($importProblemService, $reopenedZip, $contest, $em): void {
                $messages = ['info' => [], 'warning' => [], 'danger' => []];
                $problem = $importProblemService->importZippedProblem(
                    $reopenedZip,
                    'disabledcontestprob.zip',
                    null,
                    $contest,
                    $messages
                );

                self::assertNotNull($problem, 'Problem import into disabled contest should succeed');
                self::assertSame([], $messages['danger'], 'Import into disabled contest must not produce danger messages');
                self::assertCount(1, $problem->getTestcases(), 'Imported problem must expose its testcase collection');

                $submissions = $em->getRepository(Submission::class)->findBy([
                    'contest' => $contest,
                    'problem' => $problem,
                ]);
                self::assertCount(1, $submissions, 'Jury reference solution must be submitted into disabled contest');
            }, $adminUser);
        } finally {
            $reopenedZip->close();
            @unlink($tmpZip);
        }
    }
}
