<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240319140330 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Convert default `null` to `0` when the global value should be picked.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE contestproblem SET lazy_eval_results = 0 WHERE lazy_eval_results IS NULL');
        $this->addSql('ALTER TABLE contestproblem CHANGE lazy_eval_results lazy_eval_results INT UNSIGNED NOT NULL COMMENT \'Whether to do lazy evaluation for this problem; if set this overrides the global configuration setting\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contestproblem CHANGE lazy_eval_results lazy_eval_results INT UNSIGNED DEFAULT NULL COMMENT \'Whether to do lazy evaluation for this problem; if set this overrides the global configuration setting\'');
        $this->addSql('UPDATE contestproblem SET lazy_eval_results = NULL WHERE lazy_eval_results = 0');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
