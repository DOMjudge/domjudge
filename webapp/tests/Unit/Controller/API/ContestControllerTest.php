<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\API;

use App\DataFixtures\Test\DemoPreStartContestFixture;
use App\Entity\Contest;
use App\Entity\Event;
use App\Tests\StatementHook\StatementHookMiddleware;
use App\Utils\CcsApiVersion;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;

class ContestControllerTest extends BaseTestCase
{
    protected function tearDown(): void
    {
        StatementHookMiddleware::reset();

        parent::tearDown();
    }
    protected ?string $apiEndpoint = 'contests';

    protected array $expectedObjects = [
        'demo' => [
            'formal_name'                => 'Demo contest',
            'penalty_time'               => '0:20:00',
            // 'start_time'                 => '2021-01-01T11:00:00+00:00',
            // 'end_time'                   => '2024-01-01T16:00:00+00:00',
            'duration'                   => '5:00:00.000',
            'scoreboard_freeze_duration' => '1:00:00.000',
            'id'                         => 'demo',
            'name'                       => 'Demo contest',
            'shortname'                  => 'demo',
            'banner'                     => null,
        ],
    ];

    protected array $expectedAbsent = ['4242', 'nonexistent'];

    protected ?string $objectClassForExternalId = Contest::class;

    /**
     * Test that a contest that is activated but not yet started is visible in the list action for a team user.
     */
    public function testListShowsActivatedButNotStartedContest(): void
    {
        $this->loadFixture(DemoPreStartContestFixture::class);

        $url = $this->helperGetEndpointURL($this->apiEndpoint);
        // Use 'demo' user which has team role
        $objects = $this->verifyApiJsonResponse('GET', $url, 200, 'demo');

        self::assertIsArray($objects);
        self::assertNotEmpty($objects, 'Contest list should not be empty');

        // Find the demo contest in the response
        $foundContest = null;
        foreach ($objects as $contest) {
            if ($contest['shortname'] === 'demo') {
                $foundContest = $contest;
                break;
            }
        }

        self::assertNotNull($foundContest, 'Demo contest should be visible after activation even before start');
        self::assertSame('Demo contest', $foundContest['formal_name']);
    }

    /**
     * Test that a contest that is activated but not yet started is visible in the single action for a team user.
     */
    public function testSingleShowsActivatedButNotStartedContest(): void
    {
        $this->loadFixture(DemoPreStartContestFixture::class);

        $contestId = $this->resolveEntityId(Contest::class, '1');
        $url = $this->helperGetEndpointURL($this->apiEndpoint, $contestId);
        // Use 'demo' user which has team role
        $contest = $this->verifyApiJsonResponse('GET', $url, 200, 'demo');

        self::assertIsArray($contest);
        self::assertSame('Demo contest', $contest['formal_name']);
        self::assertSame('demo', $contest['shortname']);
    }

    public function testEventFeedOnlyContainsEventsOfConfiguredVersion(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $contest = $em->getRepository(Contest::class)->findOneBy(['shortname' => 'demo']);
        $events = [];
        foreach ([CcsApiVersion::Format_2020_03, CcsApiVersion::Format_2026_01] as $version) {
            $event = (new Event())
                ->setContest($contest)
                ->setEventtime(Utils::now())
                ->setEndpointtype('teams')
                ->setEndpointid('team-' . $version->value)
                ->setAction('create')
                ->setContent(['id' => 'team-' . $version->value])
                ->setVersion($version);
            $em->persist($event);
            $em->flush();
            $events[$version->value] = $event->getEventid();
        }

        $url = '/contests/' . $this->getDemoContestId() . '/event-feed?stream=false&types=teams';
        $feed = $this->verifyApiResponse('GET', $url, 200, 'admin', attachment: true);
        $tokens = array_map(
            fn(string $line) => json_decode($line, true)['token'],
            array_filter(explode("\n", $feed))
        );
        self::assertContains((string)$events['2026-01'], $tokens);
        self::assertNotContains((string)$events['2020-03'], $tokens);

        $this->verifyApiResponse('GET', $url . '&since_token=' . $events['2026-01'], 200, 'admin', attachment: true);
        $this->verifyApiResponse('GET', $url . '&since_token=' . $events['2020-03'], 400, 'admin');
    }

    #[DataProvider('provideProblemLimitsInStrictEventFeed')]
    public function testProblemLimitsInStrictEventFeed(string $ccsApiVersion, bool $expectLimits): void
    {
        $this->withChangedConfiguration('ccs_api_version', $ccsApiVersion, function () use ($expectLimits): void {
            $url = '/contests/' . $this->getDemoContestId() . '/event-feed?stream=false&types=problems&strict=true';
            $feed = $this->verifyApiResponse('GET', $url, 200, 'admin', attachment: true);
            $problems = array_map(
                fn(string $line) => json_decode($line, true)['data'],
                array_filter(explode("\n", $feed))
            );
            self::assertNotEmpty($problems);
            foreach ($problems as $problem) {
                foreach (['memory_limit', 'output_limit', 'code_limit'] as $field) {
                    self::assertSame($expectLimits, array_key_exists($field, $problem), $field);
                }
            }
        });
    }

    public static function provideProblemLimitsInStrictEventFeed(): Generator
    {
        yield ['2023-06', false];
        yield ['2026-01', true];
    }

    public function testEventFeedFiltersOtherContest(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $demoContest = $em->getRepository(Contest::class)->findOneBy(['shortname' => 'demo']);

        $otherContest = (new Contest())
            ->setExternalid('other')
            ->setName('Other contest')
            ->setShortname('other')
            ->setStarttimeString('2021-07-17 16:09:00 Europe/Amsterdam')
            ->setEndtimeString('2021-07-17 16:11:00 Europe/Amsterdam');
        $em->persist($otherContest);
        $em->flush();

        $connection = $em->getConnection();
        $nextId = (int)$connection->fetchOne('SELECT COALESCE(MAX(eventid), 0) FROM event');
        $now = sprintf('%.3f', Utils::now());
        $version = CcsApiVersion::Format_2026_01->getContentVersion()->value;

        // Insert interleaved events for both contests with explicit contiguous IDs so
        // rolled-back AUTO_INCREMENT drift does not create an unrelated gap.
        $demoIds = [];
        $otherIds = [];
        for ($i = 1; $i <= 20; $i++) {
            $otherEventId = ++$nextId;
            $connection->insert('event', [
                'eventid' => $otherEventId,
                'eventtime' => $now,
                'cid' => $otherContest->getCid(),
                'endpointtype' => 'clarifications',
                'endpointid' => 'other-clar-' . $i,
                'action' => 'create',
                'content' => json_encode(['id' => 'other-clar-' . $i], JSON_THROW_ON_ERROR),
                'version' => $version,
            ]);
            $otherIds[] = (string)$otherEventId;

            $demoEventId = ++$nextId;
            $connection->insert('event', [
                'eventid' => $demoEventId,
                'eventtime' => $now,
                'cid' => $demoContest->getCid(),
                'endpointtype' => 'clarifications',
                'endpointid' => 'demo-clar-' . $i,
                'action' => 'create',
                'content' => json_encode(['id' => 'demo-clar-' . $i], JSON_THROW_ON_ERROR),
                'version' => $version,
            ]);
            $demoIds[] = (string)$demoEventId;
        }

        $url = '/contests/' . $this->getDemoContestId() . '/event-feed?stream=false&types=clarifications';
        $feed = $this->verifyApiResponse('GET', $url, 200, 'admin', attachment: true);
        $events = array_values(array_map(
            static fn(string $line) => json_decode($line, true, 512, JSON_THROW_ON_ERROR),
            array_filter(explode("\n", $feed))
        ));

        $tokens = array_column($events, 'token');
        $sortedTokens = $tokens;
        sort($sortedTokens, SORT_NUMERIC);
        self::assertSame($sortedTokens, $tokens);

        self::assertSame($demoIds, array_values(array_intersect($demoIds, $tokens)));
        self::assertSame([], array_values(array_intersect($otherIds, $tokens)));

        // Resuming from a demo token mid-way returns only the remaining demo events.
        $sinceToken = $demoIds[9];
        $tailFeed = $this->verifyApiResponse('GET', $url . '&since_token=' . $sinceToken, 200, 'admin', attachment: true);
        $tailTokens = array_map(
            static fn(string $line) => json_decode($line, true, 512, JSON_THROW_ON_ERROR)['token'],
            array_values(array_filter(explode("\n", $tailFeed)))
        );
        self::assertSame(array_slice($demoIds, 10), $tailTokens);

        // Using a token belonging to the other contest is rejected with 400.
        $this->verifyApiResponse('GET', $url . '&since_token=' . $otherIds[0], 400, 'admin');
    }

    public function testEventFeedSkipsMissingEventIdGap(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $demoContest = $em->getRepository(Contest::class)->findOneBy(['shortname' => 'demo']);
        $connection = $em->getConnection();

        $baseId = (int)$connection->fetchOne('SELECT COALESCE(MAX(eventid), 0) FROM event');
        $now = sprintf('%.3f', Utils::now());
        $version = CcsApiVersion::Format_2026_01->getContentVersion()->value;

        // Leave a gap at $baseId + 2 and $baseId + 3 between two events of the contest.
        foreach ([$baseId + 1, $baseId + 4] as $eventId) {
            $connection->insert('event', [
                'eventid' => $eventId,
                'eventtime' => $now,
                'cid' => $demoContest->getCid(),
                'endpointtype' => 'clarifications',
                'endpointid' => 'gap-clar-' . $eventId,
                'action' => 'create',
                'content' => json_encode(['id' => 'gap-clar-' . $eventId], JSON_THROW_ON_ERROR),
                'version' => $version,
            ]);
        }

        $url = '/contests/' . $this->getDemoContestId() . '/event-feed?stream=false&types=clarifications&since_token=' . ($baseId + 1);
        $start = microtime(true);
        $feed = $this->verifyApiResponse('GET', $url, 200, 'admin', attachment: true);
        $elapsed = microtime(true) - $start;
        $tokens = array_map(
            static fn(string $line) => json_decode($line, true, 512, JSON_THROW_ON_ERROR)['token'],
            array_values(array_filter(explode("\n", $feed)))
        );

        self::assertSame([(string)($baseId + 4)], $tokens);
        self::assertGreaterThanOrEqual(0.9, $elapsed);
    }

    public function testEventFeedEmitsEventCommittedDuringGapWait(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $demoContest = $em->getRepository(Contest::class)->findOneBy(['shortname' => 'demo']);
        $connection = $em->getConnection();

        $baseId = (int)$connection->fetchOne('SELECT COALESCE(MAX(eventid), 0) FROM event');
        $now = sprintf('%.3f', Utils::now());
        $version = CcsApiVersion::Format_2026_01->getContentVersion()->value;
        $row = static fn(int $id): array => [
            'eventid' => $id,
            'eventtime' => $now,
            'cid' => $demoContest->getCid(),
            'endpointtype' => 'clarifications',
            'endpointid' => 'wait-fill-' . $id,
            'action' => 'create',
            'content' => json_encode(['id' => 'wait-fill-' . $id], JSON_THROW_ON_ERROR),
            'version' => $version,
        ];

        $connection->insert('event', $row($baseId + 1));
        $connection->insert('event', $row($baseId + 3));

        $polledAgain = false;
        StatementHookMiddleware::once(
            'SELECT COUNT(*) FROM event WHERE eventid BETWEEN ',
            static function () use ($connection, $row, $baseId, &$polledAgain): void {
                $connection->insert('event', $row($baseId + 2));
                StatementHookMiddleware::once(
                    'SELECT COUNT(*) FROM event WHERE eventid BETWEEN ',
                    static function () use (&$polledAgain): void {
                        $polledAgain = true;
                    }
                );
            }
        );

        $url = '/contests/' . $this->getDemoContestId() . '/event-feed?stream=false&types=clarifications&since_token=' . ($baseId + 1);
        $feed = $this->verifyApiResponse('GET', $url, 200, 'admin', attachment: true);
        $tokens = array_map(
            static fn(string $line) => json_decode($line, true, 512, JSON_THROW_ON_ERROR)['token'],
            array_values(array_filter(explode("\n", $feed)))
        );

        self::assertSame([(string)($baseId + 2), (string)($baseId + 3)], $tokens);
        self::assertFalse($polledAgain);
    }

    public function testEventFeedEmitsEventCommittedBetweenQueryAndGapCheck(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $demoContest = $em->getRepository(Contest::class)->findOneBy(['shortname' => 'demo']);
        $connection = $em->getConnection();

        $baseId = (int)$connection->fetchOne('SELECT COALESCE(MAX(eventid), 0) FROM event');
        $now = sprintf('%.3f', Utils::now());
        $version = CcsApiVersion::Format_2026_01->getContentVersion()->value;
        $row = static fn(int $id): array => [
            'eventid' => $id,
            'eventtime' => $now,
            'cid' => $demoContest->getCid(),
            'endpointtype' => 'clarifications',
            'endpointid' => 'race-' . $id,
            'action' => 'create',
            'content' => json_encode(['id' => 'race-' . $id], JSON_THROW_ON_ERROR),
            'version' => $version,
        ];

        $connection->insert('event', $row($baseId + 1));
        $connection->insert('event', $row($baseId + 3));

        // An earlier transaction holding $baseId + 2 commits right after the event query.
        StatementHookMiddleware::once(
            'SELECT COUNT(*) FROM event WHERE eventid > ',
            static fn() => $connection->insert('event', $row($baseId + 2))
        );

        $url = '/contests/' . $this->getDemoContestId() . '/event-feed?stream=false&types=clarifications&since_token=' . ($baseId + 1);
        $feed = $this->verifyApiResponse('GET', $url, 200, 'admin', attachment: true);
        $tokens = array_map(
            static fn(string $line) => json_decode($line, true, 512, JSON_THROW_ON_ERROR)['token'],
            array_values(array_filter(explode("\n", $feed)))
        );

        self::assertSame([(string)($baseId + 2), (string)($baseId + 3)], $tokens);
    }
}
