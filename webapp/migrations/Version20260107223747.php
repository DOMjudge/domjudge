<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add external_source_use_judgements field to contest to control whether external judgements are used for scoring.
 */
final class Version20260107223747 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add external_source_use_judgements field to contest to control whether external judgements are used for scoring.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contest ADD external_source_use_judgements TINYINT(1) DEFAULT 0 NOT NULL COMMENT \'Use external judgements for results and scoring?\' AFTER external_source_enabled');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contest DROP external_source_use_judgements');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
