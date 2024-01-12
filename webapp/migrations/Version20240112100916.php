<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240112100916 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Change room to location for teams.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE team CHANGE room location VARCHAR(255) DEFAULT NULL COMMENT \'Physical location of team\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE team CHANGE location room VARCHAR(255) DEFAULT NULL COMMENT \'Physical location of team\'');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
