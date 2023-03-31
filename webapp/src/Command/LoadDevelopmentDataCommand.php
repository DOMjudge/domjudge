<?php declare(strict_types=1);

namespace App\Command;

use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'domjudge:load-development-data',
    description: 'Load fixture data to verify unit tests.'
)]
class LoadDevelopmentDataCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument(
                'TestFixture',
                InputArgument::REQUIRED,
                'The name of the Test fixture to load'
            );
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $command   = $this->getApplication()->find('doctrine:fixtures:load');
        $testDataName = $input->getArgument('TestFixture');
        $arguments = [
            'command'  => 'doctrine:fixtures:load',
            '--group'  => [$testDataName],
            '--append' => null,
        ];

        return $command->run(new ArrayInput($arguments), $output);
    }
}
