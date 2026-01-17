<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add expected_score field to submission table for scoring problems verification.
 */
final class Version20260116075649 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add expected_score field to submission table for scoring problems verification.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE submission ADD expected_score NUMERIC(32, 9) DEFAULT NULL COMMENT \'Expected score for scoring problems - used to validate jury submissions\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE submission DROP expected_score');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
