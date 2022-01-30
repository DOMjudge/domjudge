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
final class Version20200111104226 extends AbstractMigration implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    public function getDescription(): string
    {
        return 'remove unneeded columns from the configuration table';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP INDEX public ON configuration');
        $this->addSql('ALTER TABLE configuration DROP type, DROP public, DROP category, DROP description');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE configuration
    ADD type VARCHAR(32) DEFAULT \'NULL\' COLLATE utf8mb4_unicode_ci COMMENT \'Type of the value (metatype for use in the webinterface)\',
    ADD public TINYINT(1) DEFAULT \'0\' NOT NULL COMMENT \'Is this variable publicly visible?\',
    ADD category VARCHAR(32) DEFAULT \'Uncategorized\' NOT NULL COLLATE utf8mb4_unicode_ci COMMENT \'Option category of the configuration variable\',
    ADD description VARCHAR(255) DEFAULT \'NULL\' COLLATE utf8mb4_unicode_ci COMMENT \'Description for in the webinterface\'');
        $this->addSql('CREATE INDEX public ON configuration (public)');

        // We also need to add back the type, category,  public and description values
        $configService = $this->container->get(ConfigurationService::class);
        $specs         = $configService->getConfigSpecification();
        foreach ($specs as $name => $spec) {
            $this->addSql(
                'UPDATE configuration SET type = :type, category = :category, public = :public, description = :description WHERE name = :name',
                [
                    'name' => $name,
                    'type' => $spec['type'],
                    'category' => $spec['category'],
                    'public' => (int)$spec['public'],
                    'description' => $spec['description'],
                ]
            );
        }
    }
}
