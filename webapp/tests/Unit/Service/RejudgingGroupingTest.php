<?php declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\AuthorizedUserService;
use App\Service\BalloonService;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Service\RejudgingService;
use App\Service\ScoreboardService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class RejudgingGroupingTest extends TestCase
{
    private function createService(): RejudgingService
    {
        $em = $this->createMock(EntityManagerInterface::class);
        return new RejudgingService(
            $this->createMock(AuthorizedUserService::class),
            $em,
            $this->createMock(DOMJudgeService::class),
            $this->createMock(ScoreboardService::class),
            $this->createMock(EventLogService::class),
            new BalloonService($em, $this->createMock(ConfigurationService::class)),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function makeRow(
        int $rejudgingId,
        ?int $repeatRejudgingId,
        string $startTime,
        string $startUser,
        bool $active = false,
    ): array {
        return [
            'rejudgingid' => $rejudgingId,
            'repeat_rejudgingid' => $repeatRejudgingId,
            'active' => $active,
            'data' => [
                'rejudgingid' => ['value' => $rejudgingId],
                'starttime' => ['value' => $startTime],
                'startuser' => ['value' => $startUser],
            ],
        ];
    }

    public function testGroupRepeatedRejudgingsUsesRootRejudging(): void
    {
        $service = $this->createService();

        // createRejudging() sets repeat_rejudgingid = rejudgingid on the first run,
        // and points all subsequent runs to that first rejudgingid.
        $rows = [
            $this->makeRow(7, 5, '13:30', 'judgehost-user', active: true),
            $this->makeRow(6, 5, '13:27', 'judgehost-user'),
            $this->makeRow(5, 5, '13:24', 'admin'),
        ];

        $grouped = $service->groupRepeatedRejudgings($rows);

        self::assertCount(1, $grouped);
        $header = $grouped[0];
        self::assertTrue($header['is_group_parent']);
        self::assertSame(5, $header['group_id']);
        self::assertSame(3, $header['child_count']);
        self::assertTrue($header['expanded']);
        self::assertSame('5…7', $header['data']['rejudgingid']['value']);
        self::assertSame(7, $header['data']['rejudgingid']['sortvalue']);
        self::assertSame('13:24', $header['data']['starttime']['value']);
        self::assertSame('admin', $header['data']['startuser']['value']);
    }

    public function testGroupRepeatedRejudgingsFallsBackToOldestWhenRootMissing(): void
    {
        $service = $this->createService();

        $rows = [
            $this->makeRow(7, 5, '13:30', 'judgehost-user'),
            $this->makeRow(6, 5, '13:27', 'first-available-user'),
        ];

        $grouped = $service->groupRepeatedRejudgings($rows);

        self::assertCount(1, $grouped);
        $header = $grouped[0];
        self::assertSame('6…7', $header['data']['rejudgingid']['value']);
        self::assertSame('13:27', $header['data']['starttime']['value']);
        self::assertSame('first-available-user', $header['data']['startuser']['value']);
    }
}
