<?php declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\Process;

#[AsCommand(
    name: 'adminer:compile',
    description: 'Compile adminer in vendor',
)]
class AdminerCompileCommand
{
    public function __construct(
        #[Autowire('%domjudge.vendordir%')]
        private readonly string $vendorDir
    ) {
    }

    public function __invoke(OutputInterface $output): int
    {
        $process = new Process(
            ['php', 'compile.php', 'mysql'],
            $this->vendorDir . '/vrana/adminer'
        );
        $process->run();

        if (!$process->isSuccessful()) {
            $output->writeln('<error>Failed to compile adminer:</error>');
            $output->writeln($process->getErrorOutput());
            return Command::FAILURE;
        }

        $output->writeln('<info>Adminer compiled successfully</info>');
        return Command::SUCCESS;
    }
}
