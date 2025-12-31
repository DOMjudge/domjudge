<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Move shadow mode properties from external_contest_source to contest and remove shadow_mode configuration.
 */
final class Version20260106214943 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Move shadow mode properties from external_contest_source to contest and remove shadow_mode configuration.';
    }

    public function up(Schema $schema): void
    {
        // Add shadow mode columns to contest table
        $this->addSql(<<<SQL
            ALTER TABLE contest
                ADD external_source_enabled TINYINT(1) DEFAULT 0 NOT NULL COMMENT 'Is shadow mode enabled for this contest?',
                ADD external_source_type VARCHAR(255) DEFAULT NULL COMMENT 'Type of the external source',
                ADD external_source_source VARCHAR(255) DEFAULT NULL COMMENT 'Source for external contest data',
                ADD external_source_username VARCHAR(255) DEFAULT NULL COMMENT 'Username for external source, if any',
                ADD external_source_password VARCHAR(255) DEFAULT NULL COMMENT 'Password for external source, if any',
                ADD external_source_last_event_id VARCHAR(255) DEFAULT NULL COMMENT 'Last encountered event ID from external source, if any',
                ADD external_source_last_poll_time NUMERIC(32, 9) UNSIGNED DEFAULT NULL COMMENT 'Time of last poll by event feed reader',
                ADD external_source_last_http_code SMALLINT UNSIGNED DEFAULT NULL COMMENT 'Last HTTP code received by event feed reader'
            SQL
        );

        // Migrate data from external_contest_source to contest
        $this->addSql('UPDATE contest c
            INNER JOIN external_contest_source ecs ON c.cid = ecs.cid
            SET c.external_source_enabled = 1,
                c.external_source_type = ecs.type,
                c.external_source_source = ecs.source,
                c.external_source_username = ecs.username,
                c.external_source_password = ecs.password,
                c.external_source_last_event_id = ecs.last_event_id,
                c.external_source_last_poll_time = ecs.last_poll_time,
                c.external_source_last_http_code = ecs.last_httpcode');

        // Update external_source_warning to reference contest instead of external_contest_source
        $this->addSql('ALTER TABLE external_source_warning ADD cid INT UNSIGNED DEFAULT NULL COMMENT \'Contest ID\'');
        $this->addSql('UPDATE external_source_warning w
            INNER JOIN external_contest_source ecs ON w.extsourceid = ecs.extsourceid
            SET w.cid = ecs.cid');

        // Drop old foreign key and index
        $this->addSql('ALTER TABLE external_source_warning DROP FOREIGN KEY `FK_18F83C481C667D08`');
        $this->addSql('DROP INDEX IDX_18F83C481C667D08 ON external_source_warning');
        $this->addSql('DROP INDEX hash ON external_source_warning');
        $this->addSql('ALTER TABLE external_source_warning DROP extsourceid');

        // Add new foreign key and indexes
        $this->addSql('ALTER TABLE external_source_warning ADD CONSTRAINT FK_18F83C484B30D9C4 FOREIGN KEY (cid) REFERENCES contest (cid) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_18F83C484B30D9C4 ON external_source_warning (cid)');
        $this->addSql('CREATE UNIQUE INDEX hash ON external_source_warning (cid, hash(190))');

        // Drop old table
        $this->addSql('ALTER TABLE external_contest_source DROP FOREIGN KEY `FK_7B5AB21F4B30D9C4`');
        $this->addSql('DROP TABLE external_contest_source');

        // Remove shadow_mode configuration (now per-contest)
        $this->addSql("DELETE FROM configuration WHERE name = 'shadow_mode'");
    }

    public function down(Schema $schema): void
    {
        // Recreate external_contest_source table
        $this->addSql(<<<SQL
            CREATE TABLE external_contest_source (
                extsourceid INT UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'External contest source ID',
                cid INT UNSIGNED DEFAULT NULL COMMENT 'Contest ID',
                type VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci` COMMENT 'Type of this contest source',
                source VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci` COMMENT 'Source for this contest',
                username VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci` COMMENT 'Username for this source, if any',
                password VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci` COMMENT 'Password for this source, if any',
                last_event_id VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci` COMMENT 'Last encountered event ID, if any',
                last_poll_time NUMERIC(32, 9) UNSIGNED DEFAULT NULL COMMENT 'Time of last poll by event feed reader',
                last_httpcode SMALLINT UNSIGNED DEFAULT NULL COMMENT 'Last HTTP code received by event feed reader',
                UNIQUE INDEX cid (cid),
                PRIMARY KEY (extsourceid)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = 'Sources for external contests'
            SQL
        );
        $this->addSql('ALTER TABLE external_contest_source ADD CONSTRAINT `FK_7B5AB21F4B30D9C4` FOREIGN KEY (cid) REFERENCES contest (cid) ON DELETE CASCADE');

        // Migrate data back from contest to external_contest_source
        $this->addSql('INSERT INTO external_contest_source (cid, type, source, username, password, last_event_id, last_poll_time, last_httpcode)
            SELECT cid, external_source_type, external_source_source, external_source_username, external_source_password, external_source_last_event_id, external_source_last_poll_time, external_source_last_http_code
            FROM contest WHERE external_source_enabled = 1');

        // Remove columns from contest
        $this->addSql(<<<SQL
            ALTER TABLE contest
                DROP external_source_enabled,
                DROP external_source_type,
                DROP external_source_source,
                DROP external_source_username,
                DROP external_source_password,
                DROP external_source_last_event_id,
                DROP external_source_last_poll_time,
                DROP external_source_last_http_code
            SQL
        );

        // Update external_source_warning back to external_contest_source
        $this->addSql('ALTER TABLE external_source_warning DROP FOREIGN KEY FK_18F83C484B30D9C4');
        $this->addSql('DROP INDEX IDX_18F83C484B30D9C4 ON external_source_warning');
        $this->addSql('DROP INDEX hash ON external_source_warning');
        $this->addSql('ALTER TABLE external_source_warning ADD extsourceid INT UNSIGNED DEFAULT NULL COMMENT \'External contest source ID\'');
        $this->addSql('UPDATE external_source_warning w
            INNER JOIN external_contest_source ecs ON w.cid = ecs.cid
            SET w.extsourceid = ecs.extsourceid');
        $this->addSql('ALTER TABLE external_source_warning DROP cid');
        $this->addSql('ALTER TABLE external_source_warning ADD CONSTRAINT `FK_18F83C481C667D08` FOREIGN KEY (extsourceid) REFERENCES external_contest_source (extsourceid) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_18F83C481C667D08 ON external_source_warning (extsourceid)');
        $this->addSql('CREATE UNIQUE INDEX hash ON external_source_warning (extsourceid, hash(190))');

        // Restore shadow_mode configuration, set to 1 if any contest has external source enabled
        $this->addSql("INSERT INTO configuration (name, value) SELECT 'shadow_mode', IF(EXISTS(SELECT 1 FROM external_contest_source), 1, 0)");
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
