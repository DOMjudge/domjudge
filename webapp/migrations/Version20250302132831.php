<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250302132831 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add explicit scoring type.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contest ADD scoreboard_type VARCHAR(255) DEFAULT \'pass-fail\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contest DROP scoreboard_type');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
