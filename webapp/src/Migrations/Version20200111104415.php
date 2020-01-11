<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Service\ConfigurationService;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200111104415 extends AbstractMigration implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    public function getDescription(): string
    {
        return 'remove configuration options with default values';
    }

    public function up(Schema $schema): void
    {
        $configService = $this->container->get(ConfigurationService::class);
        $specs         = $configService->getConfigSpecification();
        $allConfig     = $configService->all();

        foreach ($allConfig as $name => $value) {
            if ($value == ($specs[$name]['default_value'] ?? null)) {
                $this->addSql(
                    'DELETE FROM configuration WHERE name = :name',
                    ['name' => $name]
                );
            }
        }
    }

    public function down(Schema $schema): void
    {
        $configService = $this->container->get(ConfigurationService::class);
        $allConfig     = $configService->all();

        foreach ($allConfig as $name => $value) {
            $this->addSql(
                'INSERT INTO configuration (name, value) VALUES (:name, :value) ON DUPLICATE KEY UPDATE configid=configid',
                ['name' => $name, 'value' => json_encode($value, JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_SLASHES)]
            );
        }
    }
}
