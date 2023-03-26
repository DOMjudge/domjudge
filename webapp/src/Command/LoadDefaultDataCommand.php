<?php declare(strict_types=1);

namespace App\Command;

use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class LoadDefaultDataCommand extends Command
{
    protected static $defaultName = 'domjudge:load-default-data';
    protected static $defaultDescription = 'Load the data needed by all DOMjudge installations';

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $command   = $this->getApplication()->find('doctrine:fixtures:load');
        $arguments = [
            'command'  => 'doctrine:fixtures:load',
            '--group'  => ['default'],
            '--append' => null,
        ];

        return $command->run(new ArrayInput($arguments), $output);
    }
}
