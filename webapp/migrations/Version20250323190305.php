<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250323190305 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add problem types';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE problem ADD types INT NOT NULL COMMENT \'Bitset of problem types, default is pass-fail.\'');
        $this->addSql('UPDATE problem SET types = 1');
        $this->addSql('UPDATE problem SET types = 5 WHERE is_multipass_problem = 1');
        $this->addSql('UPDATE problem SET types = 9 WHERE combined_run_compare = 1');
        $this->addSql('UPDATE problem SET types = 13 WHERE combined_run_compare = 1 AND is_multipass_problem = 1');
        $this->addSql('ALTER TABLE problem DROP combined_run_compare, DROP is_multipass_problem');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE problem ADD combined_run_compare TINYINT(1) DEFAULT 0 NOT NULL COMMENT \'Use the exit code of the run script to compute the verdict\', ADD is_multipass_problem TINYINT(1) DEFAULT 0 NOT NULL COMMENT \'Whether this problem is a multi-pass problem.\'');
        $this->addSql('UPDATE problem SET combined_run_compare = 1 WHERE types = 9 OR types = 13');
        $this->addSql('UPDATE problem SET is_multipass_problem = 1 WHERE types = 5 OR types = 13');
        $this->addSql('ALTER TABLE problem DROP types');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
