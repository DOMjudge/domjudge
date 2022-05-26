<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220220155814 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return false;
    }

    public function getDescription(): string
    {
        return 'Add external source warning table';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE external_source_warning (extwarningid INT UNSIGNED AUTO_INCREMENT NOT NULL COMMENT \'External source warning ID\', extsourceid INT UNSIGNED DEFAULT NULL COMMENT \'External contest source ID\', last_event_id VARCHAR(255) NOT NULL COMMENT \'Last event ID this warning happened at\', time NUMERIC(32, 9) UNSIGNED NOT NULL COMMENT \'Time this warning happened last\', entity_type VARCHAR(255) NOT NULL COMMENT \'Type of the entity for this warning\', entity_id VARCHAR(255) NOT NULL COMMENT \'ID of the entity for this warning\', type VARCHAR(255) NOT NULL COMMENT \'Type of this warning\', hash VARCHAR(255) NOT NULL COMMENT \'Hash of this warning. Unique within the source.\', content LONGTEXT NOT NULL COMMENT \'JSON encoded content of the warning. Type-specific.(DC2Type:json)\', INDEX IDX_18F83C481C667D08 (extsourceid), UNIQUE INDEX hash (extsourceid, hash(190)), PRIMARY KEY(extwarningid)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'Warnings for external sources\' ');
        $this->addSql('ALTER TABLE external_source_warning ADD CONSTRAINT FK_18F83C481C667D08 FOREIGN KEY (extsourceid) REFERENCES external_contest_source (extsourceid) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE external_source_warning');
    }
}
