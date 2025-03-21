<?php declare(strict_types=1);

namespace App\Command;

use App\Entity\Contest;
use App\Service\ScoreboardService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'domjudge:refresh-cache',
    description: 'Refreshes the scoreboard caches for all contests'
)]
class RefreshCacheCommand extends Command
{
    public function __construct(
        protected readonly EntityManagerInterface $em,
        protected readonly ScoreboardService $scoreboardService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new SymfonyStyle($input, $output);

        $contests = $this->em->getRepository(Contest::class)->findAll();
        foreach ($contests as $contest) {
            $this->scoreboardService->refreshCache($contest);
            $style->success("Refreshed cache for contest {$contest->getName()}.");
        }

        return Command::SUCCESS;
    }
}
