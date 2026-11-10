<?php declare(strict_types=1);

namespace App\Command;

use App\Entity\Contest;
use App\Service\ScoreboardService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'domjudge:refresh-cache',
    description: 'Refreshes the scoreboard caches for all contests'
)]
readonly class RefreshCacheCommand
{
    public function __construct(
        protected EntityManagerInterface $em,
        protected ScoreboardService $scoreboardService,
    ) {
    }

    public function __invoke(SymfonyStyle $style): int
    {
        $contests = $this->em->getRepository(Contest::class)->findAll();
        if (!$contests) {
            $style->info('No contests found.');
            return Command::SUCCESS;
        }
        foreach ($contests as $contest) {
            $style->writeln("Refreshing cache for contest {$contest->getName()} ({$contest->getExternalid()})...");
            $progressBar = $style->createProgressBar(100);
            $progressBar->start();
            $this->scoreboardService->refreshCache($contest, $progressBar->setProgress(...));
            $progressBar->finish();
            $style->newLine(2);
            $style->success("Refreshed cache for contest {$contest->getName()}.");
        }
        return Command::SUCCESS;
    }
}
