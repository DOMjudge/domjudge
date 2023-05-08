<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230410144107 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add label to teams.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE team ADD label VARCHAR(255) DEFAULT NULL COLLATE `utf8mb4_bin` COMMENT \'Team label, for example the seat number\' AFTER icpcid');
        $this->addSql('CREATE UNIQUE INDEX label ON team (label)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX label ON team');
        $this->addSql('ALTER TABLE team DROP label');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
