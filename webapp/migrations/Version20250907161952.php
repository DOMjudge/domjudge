<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250907161952 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add submission source to submission info.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE submission ADD source VARCHAR(255) NOT NULL DEFAULT \'unknown\' COMMENT \'Where did we receive this submission from?\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE submission DROP source');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
