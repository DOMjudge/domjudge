<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20230918185221 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Extend type column to the same length as language id length';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<SQL
ALTER TABLE problem_attachment
    MODIFY type varchar(32) NOT NULL COMMENT 'File type of attachment';
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<SQL
ALTER TABLE problem_attachment
    MODIFY type varchar(4) NOT NULL COMMENT 'File type of attachment';
SQL);
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
