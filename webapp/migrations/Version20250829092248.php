<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use finfo;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250829092248 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add mime type to problem attachment';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE problem_attachment ADD mime_type VARCHAR(255) NOT NULL COMMENT \'Mime type of attachment\'');

        // Load existing attachments
        $attachments = $this->connection->fetchAllAssociative('SELECT attachmentid, content FROM problem_attachment INNER JOIN problem_attachment_content USING (attachmentid)');
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        foreach ($attachments as $attachment) {
            $mime = $finfo->buffer($attachment['content']);
            $mime = explode(';', $mime)[0];
            $this->addSql("UPDATE problem_attachment SET mime_type = :mime WHERE attachmentid = :attachmentid", ['mime' => $mime, 'attachmentid' => $attachment['attachmentid']]);
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE problem_attachment DROP mime_type');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
