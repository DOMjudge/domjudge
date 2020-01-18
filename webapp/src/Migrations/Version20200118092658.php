<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200118092658 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'rename team.externalid to team.icpcid';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP INDEX externalid ON team');
        $this->addSql('ALTER TABLE team CHANGE externalid icpcid VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_bin COMMENT \'Team ID in the ICPC system\'');
        $this->addSql('CREATE UNIQUE INDEX icpcid ON team (icpcid(190))');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP INDEX icpcid ON team');
        $this->addSql('ALTER TABLE team CHANGE icpcid externalid VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_bin COMMENT \'Team ID in an external system\'');
        $this->addSql('CREATE UNIQUE INDEX externalid ON team (externalid(190))');
    }
}
