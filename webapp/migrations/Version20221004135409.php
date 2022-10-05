<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20221004135409 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow super lazy evaluation where nothing is judged unless requested.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contestproblem CHANGE lazy_eval_results lazy_eval_results INT UNSIGNED DEFAULT NULL COMMENT \'Whether to do lazy evaluation for this problem; if set this overrides the global configuration setting\'');
        $this->addSql('UPDATE contestproblem SET lazy_eval_results=2 WHERE lazy_eval_results=0');
        $this->addSql('UPDATE configuration SET value=2 WHERE value=0 AND name="lazy_eval_results"');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('UPDATE configuration SET value=0 WHERE value=2 AND name="lazy_eval_results"');
        $this->addSql('UPDATE contestproblem SET lazy_eval_results=0 WHERE lazy_eval_results=2');
        $this->addSql('ALTER TABLE contestproblem CHANGE lazy_eval_results lazy_eval_results TINYINT(1) DEFAULT NULL COMMENT \'Whether to do lazy evaluation for this problem; if set this overrides the global configuration setting\'');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
