<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220408071147 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return false;
    }

    public function getDescription(): string
    {
        return 'Fix comment for team external ID.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE team CHANGE externalid externalid VARCHAR(255) DEFAULT NULL COLLATE `utf8mb4_bin` COMMENT \'Team ID in an external system\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE team CHANGE externalid externalid VARCHAR(255) DEFAULT NULL COLLATE `utf8mb4_bin` COMMENT \'Team affiliation ID in an external system\'');
    }
}
