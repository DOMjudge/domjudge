<?php declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Utils\CcsApiVersion;
use Doctrine\ORM\EntityManagerInterface;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class EventLogServiceTest extends TestCase
{
    #[DataProvider('provideUpgradeEventContent')]
    public function testUpgradeEventContent(
        CcsApiVersion $from,
        CcsApiVersion $to,
        string $endpointType,
        array $data,
        array $expected
    ): void {
        $eventLogService = new EventLogService(
            $this->createMock(DOMJudgeService::class),
            $this->createMock(ConfigurationService::class),
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(LoggerInterface::class),
        );

        self::assertSame($expected, $eventLogService->upgradeEventContent($endpointType, $data, $from, $to));
    }

    public static function provideUpgradeEventContent(): Generator
    {
        $relTime = CcsApiVersion::Format_2026_01;
        $integer = CcsApiVersion::Format_2020_03;

        yield 'integer to reltime' => [$integer, $relTime, 'contests', ['id' => 'demo', 'penalty_time' => 20], ['id' => 'demo', 'penalty_time' => '0:20:00']];
        yield 'integer stays integer' => [$integer, $integer, 'contests', ['id' => 'demo', 'penalty_time' => 20], ['id' => 'demo', 'penalty_time' => 20]];
        yield 'reltime stays reltime' => [$relTime, $relTime, 'contests', ['id' => 'demo', 'penalty_time' => '0:20:00'], ['id' => 'demo', 'penalty_time' => '0:20:00']];
        yield 'delete event' => [$integer, $relTime, 'contests', ['id' => 'demo'], ['id' => 'demo']];
        yield 'other endpoint' => [$integer, $relTime, 'teams', ['id' => '1', 'penalty_time' => 20], ['id' => '1', 'penalty_time' => 20]];
    }

    public function testContentVersion(): void
    {
        self::assertSame(CcsApiVersion::Format_2020_03, CcsApiVersion::Format_2020_03->getContentVersion());
        self::assertSame(CcsApiVersion::Format_2020_03, CcsApiVersion::Format_2023_06->getContentVersion());
        self::assertSame(CcsApiVersion::Format_2026_01, CcsApiVersion::Format_2026_01->getContentVersion());
    }
}
