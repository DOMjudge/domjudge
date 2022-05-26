<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220408073801 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return false;
    }

    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user ADD externalid VARCHAR(255) DEFAULT NULL COLLATE `utf8mb4_bin` COMMENT \'User ID in an external system\' AFTER `userid`');
        $this->addSql('CREATE UNIQUE INDEX externalid ON user (externalid(190))');
        $this->addSql('UPDATE user SET externalid = username');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX externalid ON user');
        $this->addSql('ALTER TABLE user DROP externalid');
    }
}
