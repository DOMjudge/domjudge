<?php declare(strict_types=1);

namespace App\Command;

use Doctrine\Bundle\FixturesBundle\Command\LoadDataFixturesDoctrineCommand;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'domjudge:load-example-data',
    description: 'Load example data to get a sample DOMjudge installation up and running'
)]
readonly class LoadExampleDataCommand
{
    public function __construct(
        #[Autowire(service: 'doctrine.fixtures_load_command')]
        private LoadDataFixturesDoctrineCommand $loadDataFixturesDoctrineCommand
    ) {}

    /**
     * @throws Exception
     */
    public function __invoke(OutputInterface $output): int
    {
        $arguments = [
            '--group'  => ['example'],
            '--append' => null,
        ];

        return $this->loadDataFixturesDoctrineCommand->run(new ArrayInput($arguments), $output);
    }
}
