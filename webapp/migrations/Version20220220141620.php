<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220220141620 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return false;
    }

    public function getDescription(): string
    {
        return 'Add external contest source table';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE external_contest_source (extsourceid INT UNSIGNED AUTO_INCREMENT NOT NULL COMMENT \'External contest source ID\', cid INT UNSIGNED DEFAULT NULL COMMENT \'Contest ID\', enabled TINYINT(1) DEFAULT 1 NOT NULL COMMENT \'Is this contest source currently enabled?\', type VARCHAR(255) NOT NULL COMMENT \'Type of this contest source\', source VARCHAR(255) NOT NULL COMMENT \'Source for this contest\', username VARCHAR(255) DEFAULT NULL COMMENT \'Username for this source, if any\', password VARCHAR(255) DEFAULT NULL COMMENT \'Password for this source, if any\', last_event_id VARCHAR(255) DEFAULT NULL COMMENT \'Last encountered event ID, if any\', last_poll_time NUMERIC(32, 9) UNSIGNED DEFAULT NULL COMMENT \'Time of last poll by event feed reader\', INDEX IDX_7B5AB21F4B30D9C4 (cid), PRIMARY KEY(extsourceid)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'Sources for external contests\' ');
        $this->addSql('ALTER TABLE external_contest_source ADD CONSTRAINT FK_7B5AB21F4B30D9C4 FOREIGN KEY (cid) REFERENCES contest (cid) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE external_contest_source');
    }
}
