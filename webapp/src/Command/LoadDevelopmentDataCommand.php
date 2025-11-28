<?php declare(strict_types=1);

namespace App\Command;

use Doctrine\Bundle\FixturesBundle\Command\LoadDataFixturesDoctrineCommand;
use Exception;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'domjudge:load-development-data',
    description: 'Load fixture data to verify unit tests.'
)]
readonly class LoadDevelopmentDataCommand
{
    public function __construct(
        #[Autowire(service: 'doctrine.fixtures_load_command')]
        private LoadDataFixturesDoctrineCommand $loadDataFixturesDoctrineCommand
    ) {}

    /**
     * @throws Exception
     */
    public function __invoke(
        #[Argument(description: 'The name of the Test fixture to load')]
        string $testFixture,
        OutputInterface $output
    ): int {
        $arguments = [
            '--group'  => [$testFixture],
            '--append' => null,
        ];

        return $this->loadDataFixturesDoctrineCommand->run(new ArrayInput($arguments), $output);
    }
}
