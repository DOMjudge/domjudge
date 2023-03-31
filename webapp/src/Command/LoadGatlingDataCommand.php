<?php declare(strict_types=1);

namespace App\Command;

use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'domjudge:load-gatling-data',
    description: 'Load the gatling data(for load testing)'
)]
class LoadGatlingDataCommand extends Command
{
    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $command   = $this->getApplication()->find('doctrine:fixtures:load');
        $arguments = [
            'command'  => 'doctrine:fixtures:load',
            '--group'  => ['gatling'],
            '--append' => null,
        ];

        return $command->run(new ArrayInput($arguments), $output);
    }
}
