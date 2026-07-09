<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260306120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make judging_run.score nullable.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE judging_run CHANGE score score NUMERIC(32, 9) DEFAULT NULL COMMENT \'Optional score for this run, e.g. for partial scoring\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('UPDATE judging_run SET score = 0 WHERE score IS NULL');
        $this->addSql('ALTER TABLE judging_run CHANGE score score NUMERIC(32, 9) DEFAULT \'0.000000000\' NOT NULL COMMENT \'Optional score for this run, e.g. for partial scoring\'');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
