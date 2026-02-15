<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260215191149 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make score field nullable in judging and external_judgement tables.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE judging MODIFY score DECIMAL(32, 9) DEFAULT NULL COMMENT \'Optional score for this run, e.g. for partial scoring\'');
        $this->addSql('ALTER TABLE external_judgement MODIFY score DECIMAL(32, 9) DEFAULT NULL COMMENT \'Optional score for this run, e.g. for partial scoring\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE judging MODIFY score DECIMAL(32, 9) NOT NULL DEFAULT \'0.000000000\' COMMENT \'Optional score for this run, e.g. for partial scoring\'');
        $this->addSql('ALTER TABLE external_judgement MODIFY score DECIMAL(32, 9) NOT NULL DEFAULT \'0.000000000\' COMMENT \'Optional score for this run, e.g. for partial scoring\'');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
