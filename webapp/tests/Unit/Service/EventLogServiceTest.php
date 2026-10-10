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
    #[DataProvider('provideApplyCcsVersionChanges')]
    public function testApplyCcsVersionChanges(
        CcsApiVersion $version,
        string $endpointType,
        array $data,
        array $expected
    ): void {
        $config = $this->createMock(ConfigurationService::class);
        $config->method('get')->with('ccs_api_version')->willReturn($version);
        $eventLogService = new EventLogService(
            $this->createMock(DOMJudgeService::class),
            $config,
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(LoggerInterface::class),
        );

        self::assertSame($expected, $eventLogService->applyCcsVersionChanges($endpointType, $data));
    }

    public static function provideApplyCcsVersionChanges(): Generator
    {
        $relTime = CcsApiVersion::Format_2026_01;
        $integer = CcsApiVersion::Format_2023_06;

        yield 'integer to reltime' => [$relTime, 'contests', ['id' => 'demo', 'penalty_time' => 20], ['id' => 'demo', 'penalty_time' => '0:20:00']];
        yield 'reltime stays reltime' => [$relTime, 'contests', ['id' => 'demo', 'penalty_time' => '0:20:00'], ['id' => 'demo', 'penalty_time' => '0:20:00']];
        yield 'reltime to integer' => [$integer, 'contests', ['id' => 'demo', 'penalty_time' => '0:20:00'], ['id' => 'demo', 'penalty_time' => 20]];
        yield 'integer stays integer' => [$integer, 'contests', ['id' => 'demo', 'penalty_time' => 20], ['id' => 'demo', 'penalty_time' => 20]];
        yield 'delete event, reltime' => [$relTime, 'contests', ['id' => 'demo'], ['id' => 'demo']];
        yield 'delete event, integer' => [$integer, 'contests', ['id' => 'demo'], ['id' => 'demo']];
        yield 'other endpoint' => [$relTime, 'teams', ['id' => '1', 'penalty_time' => 20], ['id' => '1', 'penalty_time' => 20]];
    }
}
