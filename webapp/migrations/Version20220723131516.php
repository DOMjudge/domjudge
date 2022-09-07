<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20220723131516 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add lock/unlock functionality to contests.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contest ADD is_locked TINYINT(1) DEFAULT 0 NOT NULL COMMENT \'Is this contest locked for modifications?\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contest DROP is_locked');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
