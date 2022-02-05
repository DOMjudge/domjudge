<?php declare(strict_types=1);

namespace App\Command;

use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class LoadGatlingDataCommand
 * @package App\Command
 */
class LoadGatlingDataCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('domjudge:load-gatling-data')
            ->setDescription('Load the gatling data(for load testing)');
    }

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
