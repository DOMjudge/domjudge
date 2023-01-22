<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230122162836 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop enabled from external contest sources and make cid unique';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE external_contest_source DROP enabled, DROP INDEX IDX_7B5AB21F4B30D9C4, ADD UNIQUE INDEX cid (cid)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE external_contest_source ADD enabled TINYINT(1) DEFAULT 1 NOT NULL COMMENT \'Is this contest source currently enabled?\', DROP INDEX cid, ADD INDEX IDX_7B5AB21F4B30D9C4 (cid)');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
