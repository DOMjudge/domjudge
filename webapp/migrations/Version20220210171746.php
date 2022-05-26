<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220210171746 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return false;
    }

    public function getDescription() : string
    {
        return 'Add external ID field to teams';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP INDEX icpcid ON team');
        $this->addSql('ALTER TABLE team ADD externalid VARCHAR(255) DEFAULT NULL COLLATE `utf8mb4_bin` COMMENT \'Team affiliation ID in an external system\' AFTER `teamid`');
        $this->addSql('CREATE UNIQUE INDEX externalid ON team (externalid(190))');
        $this->addSql('UPDATE team SET externalid = teamid');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP INDEX externalid ON team');
        $this->addSql('ALTER TABLE team DROP externalid');
        $this->addSql('CREATE UNIQUE INDEX icpcid ON team (icpcid(190))');
    }
}
