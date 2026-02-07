<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add scoreDiffEpsilon column to contest table for configurable shadow score difference threshold.
 */
final class Version20260207100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add scoreDiffEpsilon column to contest table for configurable shadow score difference threshold.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contest ADD score_diff_epsilon DECIMAL(32, 9) DEFAULT \'0.0001\' NOT NULL COMMENT \'Minimum absolute score difference for shadow differences\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contest DROP score_diff_epsilon');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
