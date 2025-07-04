<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240917113927 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adding executable bit to problem attachments.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE problem_attachment_content ADD is_executable TINYINT(1) DEFAULT 0 NOT NULL COMMENT \'Whether this file gets an executable bit.\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE problem_attachment_content DROP is_executable');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
