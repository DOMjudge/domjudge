<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250706121413 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Update `event_feed_format` config to `ccs_api_version`';
    }

    public function up(Schema $schema): void
    {
        // Change key and values to the new format
        $this->addSql('UPDATE configuration SET name = \'ccs_api_version\' WHERE name = \'event_feed_format\' AND value = \'"2020-03"\'');
        $this->addSql('UPDATE configuration SET name = \'ccs_api_version\', value = \'"2023-06"\' WHERE name = \'event_feed_format\' AND value = \'"2022-07"\'');
    }

    public function down(Schema $schema): void
    {
        // Change key and values back to the old format
        $this->addSql('UPDATE configuration SET name = \'event_feed_format\' WHERE name = \'ccs_api_version\' AND value = \'"2020-03"\'');
        $this->addSql('UPDATE configuration SET name = \'event_feed_format\', value = \'"2022-07"\' WHERE name = \'ccs_api_version\' AND value = \'"2023-06"\'');
        // Delete it if we have a non-supported version
        $this->addSql('DELETE FROM configuration WHERE name = \'ccs_api_version\'');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
