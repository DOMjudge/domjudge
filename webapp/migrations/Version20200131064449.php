<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Configuration;
use App\Entity\TeamCategory;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

final class Version20200131064449 extends AbstractMigration implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    public function isTransactional(): bool
    {
        return false;
    }

    public function getDescription() : string
    {
        return 'replace configuration option registration_category_name with a boolean field for each category';
    }

    public function up(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        // Note: can't use ConfigurationService::get on 'registration_category_name' because the specification has been removed from db-config.yaml
        $registrationCategoryNameConfig = $this->connection->fetchAssociative('SELECT * FROM configuration WHERE name = :registration_category_name', ['registration_category_name' => 'registration_category_name']);
        if ($registrationCategoryNameConfig) {
            $registrationCategoryName = $registrationCategoryNameConfig['value'];
        } else {
            $registrationCategoryName = '';
        }

        $this->addSql('ALTER TABLE team_category ADD allow_self_registration TINYINT(1) DEFAULT \'0\' NOT NULL COMMENT \'Are self-registered teams allowed to choose this category?\'');

        if ($registrationCategoryName !== '') {
            $this->addSql(
                'UPDATE team_category SET allow_self_registration = 1 WHERE name = :name',
                ['name' => $registrationCategoryName]
            );
        }
        $this->addSql("DELETE FROM configuration WHERE name = 'registration_category_name'");
    }

    public function down(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $selfRegistrationCategories = $this->connection->fetchAllAssociative('SELECT * FROM team_category WHERE allow_self_registration = 1 ORDER BY sortorder');

        $this->warnIf(
            count($selfRegistrationCategories) > 1,
            sprintf('Team categories for self-registered teams were %s. Only first will be kept.',
                implode(', ', array_map(function($category) {
                    return $category['name'];
                }, $selfRegistrationCategories)))
        );

        $this->addSql(
            "INSERT INTO configuration (name, value) VALUES ('registration_category_name', :value)",
            ['value' => empty($selfRegistrationCategories) ? '""' : json_encode($selfRegistrationCategories[0]['name'])]
        );

        $this->addSql('ALTER TABLE team_category DROP allow_self_registration');
    }
}
