<?php declare(strict_types=1);

namespace App\Command;

use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class LoadExampleDataCommand extends Command
{
    protected static $defaultName = 'domjudge:load-example-data';
    protected static $defaultDescription = 'Load example data to get a sample DOMjudge installation up and running';

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $command   = $this->getApplication()->find('doctrine:fixtures:load');
        $arguments = [
            'command'  => 'doctrine:fixtures:load',
            '--group'  => ['example'],
            '--append' => null,
        ];

        return $command->run(new ArrayInput($arguments), $output);
    }
}
