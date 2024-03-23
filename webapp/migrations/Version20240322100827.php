<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240322100827 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add contest problemset table and type to contests.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE contest_problemset_content (cid INT UNSIGNED NOT NULL COMMENT \'Contest ID\', content LONGBLOB NOT NULL COMMENT \'Problemset document content(DC2Type:blobtext)\', PRIMARY KEY(cid)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'Stores contents of contest problemset documents\' ');
        $this->addSql('ALTER TABLE contest_problemset_content ADD CONSTRAINT FK_6680FE6A4B30D9C4 FOREIGN KEY (cid) REFERENCES contest (cid) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE contest ADD contest_problemset_type VARCHAR(4) DEFAULT NULL COMMENT \'File type of contest problemset document\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contest_problemset_content DROP FOREIGN KEY FK_6680FE6A4B30D9C4');
        $this->addSql('DROP TABLE contest_problemset_content');
        $this->addSql('ALTER TABLE contest DROP contest_problemset_type');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
