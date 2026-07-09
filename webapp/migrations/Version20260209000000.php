<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260209000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add shadow_compare_by_score column to contest table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contest ADD shadow_compare_by_score TINYINT(1) DEFAULT 0 NOT NULL COMMENT \'For shadow mode, compare by score only (ignore verdict if scores match)?\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contest DROP shadow_compare_by_score');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
