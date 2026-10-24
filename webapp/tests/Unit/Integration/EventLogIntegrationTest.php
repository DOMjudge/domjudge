<?php declare(strict_types=1);

namespace App\Tests\Unit\Integration;

use App\Entity\Contest;
use App\Entity\Problem;
use App\Entity\Team;
use App\Entity\User;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;

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

        // Before Phase 1, internalApiRequest called $em->clear(), which detached all entities.
        // With HINT_REFRESH, the entities MUST remain managed in Doctrine's identity map.
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

        $em = $container->get(EntityManagerInterface::class);
        $adminUser = $em->getRepository(User::class)->findOneBy(['username' => 'admin']);

        $endpoints = [
            '/contests/demo',
            '/contests/demo/teams',
            '/contests/demo/problems',
            '/contests/demo/problems/hello',
            '/contests/demo/clarifications',
            '/contests/demo/judgement-types',
            '/contests/demo/judgement-types/AC',
            '/contests/demo/groups',
            '/contests/demo/organizations',
            '/contests/demo/languages',
            '/contests/demo/accounts',
            '/contests/demo/submissions',
            '/contests/demo/judgements',
            '/contests/demo/runs',
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
}
