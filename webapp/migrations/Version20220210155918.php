<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220210155918 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return false;
    }

    public function getDescription() : string
    {
        return 'Add external and ICPC ID fields to team categories';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE team_category ADD externalid VARCHAR(255) DEFAULT NULL COLLATE `utf8mb4_bin` COMMENT \'Team category ID in an external system\' AFTER `categoryid`, ADD icpcid VARCHAR(255) DEFAULT NULL COLLATE `utf8mb4_bin` COMMENT \'External identifier from ICPC CMS\' AFTER `externalid`');
        $this->addSql('CREATE UNIQUE INDEX externalid ON team_category (externalid(190))');
        $this->addSql('UPDATE team_category SET externalid = categoryid');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP INDEX externalid ON team_category');
        $this->addSql('ALTER TABLE team_category DROP externalid, DROP icpcid');
    }
}
