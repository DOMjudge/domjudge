<?php declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\RefreshCacheCommand;
use App\Entity\Contest;
use App\Service\ScoreboardService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

class RefreshCacheCommandTest extends TestCase
{
    public function testLogsContestStartAndProgressBeforeCompletion(): void
    {
        $contest = (new Contest())->setName('Demo contest')->setExternalid('demo');
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findAll')->willReturn([$contest]);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->with(Contest::class)->willReturn($repo);

        $output = new BufferedOutput();
        $outputDuringRefresh = '';
        $scoreboardService = $this->createMock(ScoreboardService::class);
        $scoreboardService->expects(self::once())
            ->method('refreshCache')
            ->willReturnCallback(static function (Contest $c, callable $progressReporter) use (
                $output,
                &$outputDuringRefresh
            ): void {
                $outputDuringRefresh = $output->fetch();
                $progressReporter(50, 't1');
                $progressReporter(100, '');
            });

        $command = new RefreshCacheCommand($em, $scoreboardService);
        self::assertSame(Command::SUCCESS, $command(new SymfonyStyle(new ArrayInput([]), $output)));

        $remainingOutput = $output->fetch();
        self::assertStringContainsString('Refreshing cache for contest Demo contest (demo)...', $outputDuringRefresh);
        self::assertStringContainsString('100%', $remainingOutput);
        self::assertStringContainsString('Refreshed cache for contest Demo contest.', $remainingOutput);
    }
}
