<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201113094653 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return false;
    }

    public function getDescription() : string
    {
        return 'add problem attachment tables';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE problem_attachment_content (attachmentid INT UNSIGNED NOT NULL COMMENT \'Attachment ID\', content LONGBLOB NOT NULL COMMENT \'Attachment content(DC2Type:blobtext)\', PRIMARY KEY(attachmentid)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'Stores contents of problem attachments\' ');
        $this->addSql('CREATE TABLE problem_attachment (attachmentid INT UNSIGNED AUTO_INCREMENT NOT NULL COMMENT \'Attachment ID\', probid INT UNSIGNED DEFAULT NULL COMMENT \'Problem ID\', name VARCHAR(255) NOT NULL COMMENT \'Filename of attachment\', type VARCHAR(4) NOT NULL COMMENT \'File type of attachment\', INDEX IDX_317299FEEF049279 (probid), INDEX name (attachmentid, name(190)), PRIMARY KEY(attachmentid)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'Attachments belonging to problems\' ');
        $this->addSql('ALTER TABLE problem_attachment_content ADD CONSTRAINT FK_C097D9BF4707030C FOREIGN KEY (attachmentid) REFERENCES problem_attachment (attachmentid) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE problem_attachment ADD CONSTRAINT FK_317299FEEF049279 FOREIGN KEY (probid) REFERENCES problem (probid) ON DELETE CASCADE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE problem_attachment_content DROP FOREIGN KEY FK_C097D9BF4707030C');
        $this->addSql('DROP TABLE problem_attachment_content');
        $this->addSql('DROP TABLE problem_attachment');
    }
}
