<?php declare(strict_types=1);

namespace App\Migrations\Factory;

use App\Service\ConfigurationService;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\Version\MigrationFactory;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;

/**
 * This decorator is used to inject services into migrations that need them
 */
#[AsDecorator(decorates: 'doctrine.migrations.migrations_factory')]
readonly class MigrationFactoryDecorator implements MigrationFactory
{

    public function __construct(
        private MigrationFactory $migrationFactory,
        private ConfigurationService $configurationService,
    ) {
    }

    public function createVersion(string $migrationClassName): AbstractMigration
    {
        $instance = $this->migrationFactory->createVersion($migrationClassName);

        // Load the configuration service if the migration needs it
        if ($instance instanceof ConfigurationServiceAwareInterface) {
            $instance->setConfigurationService($this->configurationService);
        }

        return $instance;
    }
}
