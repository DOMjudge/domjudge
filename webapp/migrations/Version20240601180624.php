<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240601180624 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Move data_source to shadow_mode';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('INSERT INTO configuration (name, value) SELECT \'shadow_mode\', 0 FROM configuration WHERE name = \'data_source\' AND value != \'2\'');
        $this->addSql('INSERT INTO configuration (name, value) SELECT \'shadow_mode\', 1 FROM configuration WHERE name = \'data_source\' AND value = \'2\'');
        $this->addSql('DELETE FROM configuration WHERE name = \'data_source\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('INSERT INTO configuration (name, value) SELECT \'data_source\', 1 FROM configuration WHERE name = \'shadow_mode\' AND value = 0');
        $this->addSql('INSERT INTO configuration (name, value) SELECT \'data_source\', 2 FROM configuration WHERE name = \'shadow_mode\' AND value = 1');
        $this->addSql('DELETE FROM configuration WHERE name = \'shadow_mode\'');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
