<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260118153404 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'add allow_password_change to team_category';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE team_category ADD allow_password_change TINYINT(1) DEFAULT 0 NOT NULL COMMENT \'Are teams in this category allowed to change their own password?\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE team_category DROP allow_password_change');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
