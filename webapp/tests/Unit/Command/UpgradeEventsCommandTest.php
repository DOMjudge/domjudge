<?php declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Entity\Configuration;
use App\Entity\Contest;
use App\Entity\Event;
use App\Service\ConfigurationService;
use App\Tests\Unit\BaseTestCase;
use App\Utils\CcsApiVersion;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class UpgradeEventsCommandTest extends BaseTestCase
{
    protected CommandTester $commandTester;
    protected EntityManagerInterface $em;
    protected Contest $contest;

    protected function setUp(): void
    {
        parent::setUp();
        $app = new Application(self::$kernel);
        $this->commandTester = new CommandTester($app->find('domjudge:upgrade-events'));
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->contest = $this->em->getRepository(Contest::class)->findOneBy(['shortname' => 'demo']);

        $this->em->createQueryBuilder()->delete(Event::class, 'e')->getQuery()->execute();
    }

    public function testUpgrade(): void
    {
        $this->addEvent(CcsApiVersion::Format_2020_03, '100.000', 'contests', 'create', ['id' => 'demo', 'penalty_time' => 20]);
        $this->addEvent(CcsApiVersion::Format_2020_03, '101.000', 'teams', 'create', ['id' => 'team']);
        $this->addEvent(CcsApiVersion::Format_2020_03, '102.000', 'contests', 'update', ['id' => 'demo', 'penalty_time' => 25]);

        $this->withChangedConfiguration('ccs_api_version', '2023-06', function (): void {
            $this->commandTester->execute(['version' => '2026-01']);
            $this->commandTester->assertCommandIsSuccessful();

            $upgraded = array_map(
                fn(Event $event) => [$event->getEventtime(), $event->getEndpointtype(), $event->getAction(), $event->getContent()],
                $this->getEvents(CcsApiVersion::Format_2026_01)
            );
            self::assertEquals([
                ['100.000000000', 'contests', 'create', ['id' => 'demo', 'penalty_time' => '0:20:00']],
                ['101.000000000', 'teams', 'create', ['id' => 'team']],
                ['102.000000000', 'contests', 'update', ['id' => 'demo', 'penalty_time' => '0:25:00']],
            ], $upgraded);
            self::assertCount(3, $this->getEvents(CcsApiVersion::Format_2020_03));
            self::assertSame(CcsApiVersion::Format_2026_01, $this->getConfiguredVersion());
        });
    }

    public function testSwitchWithinContentVersion(): void
    {
        $this->addEvent(CcsApiVersion::Format_2020_03, '100.000', 'contests', 'create', ['id' => 'demo', 'penalty_time' => 20]);

        $this->withChangedConfiguration('ccs_api_version', '2020-03', function (): void {
            $this->commandTester->execute(['version' => '2023-06']);
            $this->commandTester->assertCommandIsSuccessful();

            self::assertCount(1, $this->em->getRepository(Event::class)->findAll());
            self::assertSame(CcsApiVersion::Format_2023_06, $this->getConfiguredVersion());
            self::assertStringContainsString('Events of contests demo are already in version 2023-06', $this->commandTester->getDisplay());
        });
    }

    public function testAlreadyUpgraded(): void
    {
        $this->addEvent(CcsApiVersion::Format_2026_01, '100.000', 'contests', 'create', ['id' => 'demo', 'penalty_time' => '0:20:00']);

        $this->withChangedConfiguration('ccs_api_version', '2026-01', function (): void {
            $this->commandTester->execute(['version' => '2026-01']);
            $this->commandTester->assertCommandIsSuccessful();

            $display = $this->commandTester->getDisplay();
            self::assertStringContainsString('Events of contests demo are already in version 2026-01', $display);
            self::assertStringContainsString('No events were upgraded', $display);
            self::assertCount(1, $this->em->getRepository(Event::class)->findAll());
        });
    }

    public function testMergesEventsWrittenAfterSwitching(): void
    {
        $this->addEvent(CcsApiVersion::Format_2020_03, '100.000', 'contests', 'create', ['id' => 'demo', 'penalty_time' => 20]);
        $this->addEvent(CcsApiVersion::Format_2020_03, '101.000', 'teams', 'create', ['id' => 'team1']);
        $this->addEvent(CcsApiVersion::Format_2020_03, '102.000', 'submissions', 'create', ['id' => 's1', 'time' => 1]);
        $this->addEvent(CcsApiVersion::Format_2020_03, '103.000', 'teams', 'delete', ['id' => 'team1']);
        $this->addEvent(CcsApiVersion::Format_2026_01, '200.000', 'contests', 'create', ['id' => 'demo', 'penalty_time' => '0:20:00']);
        $this->addEvent(CcsApiVersion::Format_2026_01, '201.000', 'teams', 'create', ['id' => 'team2']);
        $this->addEvent(CcsApiVersion::Format_2026_01, '202.000', 'submissions', 'create', ['id' => 's1', 'time' => 2]);
        $this->addEvent(CcsApiVersion::Format_2026_01, '203.000', 'teams', 'delete', ['id' => 'team1']);
        $this->addEvent(CcsApiVersion::Format_2026_01, '204.000', 'state', 'update', ['id' => '', 'started' => 'now']);

        $this->withChangedConfiguration('ccs_api_version', '2026-01', function (): void {
            $this->commandTester->execute([]);
            $this->commandTester->assertCommandIsSuccessful();
            self::assertStringContainsString('Contest demo: upgraded 4 events from version 2020-03 to 2026-01 and kept 3 events', $this->commandTester->getDisplay());

            $upgraded = array_map(
                fn(Event $event) => [$event->getEventtime(), $event->getEndpointtype(), $event->getEndpointid(), $event->getAction()],
                $this->getEvents(CcsApiVersion::Format_2026_01)
            );
            self::assertEquals([
                ['100.000000000', 'contests', 'demo', 'create'],
                ['101.000000000', 'teams', 'team1', 'create'],
                ['102.000000000', 'submissions', 's1', 'create'],
                ['103.000000000', 'teams', 'team1', 'delete'],
                ['201.000000000', 'teams', 'team2', 'create'],
                ['202.000000000', 'submissions', 's1', 'update'],
                ['204.000000000', 'state', '', 'update'],
            ], $upgraded);

            $tokens = array_map(fn(Event $event) => $event->getEventid(), $this->getEvents(CcsApiVersion::Format_2026_01));
            $this->commandTester->execute([]);
            $this->commandTester->assertCommandIsSuccessful();
            self::assertStringContainsString('Events of contests demo are already in version 2026-01', $this->commandTester->getDisplay());
            self::assertSame($tokens, array_map(fn(Event $event) => $event->getEventid(), $this->getEvents(CcsApiVersion::Format_2026_01)));
            self::assertCount(4, $this->getEvents(CcsApiVersion::Format_2020_03));
        });
    }

    public function testDoesNotStoreDefaultVersion(): void
    {
        $this->addEvent(CcsApiVersion::Format_2020_03, '100.000', 'contests', 'create', ['id' => 'demo', 'penalty_time' => 20]);

        $this->commandTester->execute([]);
        $this->commandTester->assertCommandIsSuccessful();

        self::assertCount(1, $this->getEvents(CcsApiVersion::Format_2026_01));
        self::assertNull($this->em->getRepository(Configuration::class)->findOneBy(['name' => 'ccs_api_version']));
    }

    public function testUnknownVersion(): void
    {
        $this->commandTester->execute(['version' => '2022-07']);
        self::assertSame(Command::FAILURE, $this->commandTester->getStatusCode());
        self::assertStringContainsString('Unknown version', $this->commandTester->getDisplay());
    }

    /**
     * @param array<string, mixed> $content
     */
    private function addEvent(CcsApiVersion $version, string $time, string $type, string $action, array $content): void
    {
        $event = (new Event())
            ->setContest($this->contest)
            ->setEventtime($time)
            ->setEndpointtype($type)
            ->setEndpointid($content['id'])
            ->setAction($action)
            ->setContent($content)
            ->setVersion($version);
        $this->em->persist($event);
        $this->em->flush();
    }

    /**
     * @return Event[]
     */
    private function getEvents(CcsApiVersion $version): array
    {
        $this->em->clear();
        return $this->em->getRepository(Event::class)->findBy(['version' => $version], ['eventid' => 'ASC']);
    }

    private function getConfiguredVersion(): CcsApiVersion
    {
        return self::getContainer()->get(ConfigurationService::class)->get('ccs_api_version');
    }
}
