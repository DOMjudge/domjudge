<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250804192300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add scoring support for external judgement/run tables';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE external_judgement ADD score NUMERIC(32, 9) DEFAULT \'0.000000000\' NOT NULL COMMENT \'Optional score for this run, e.g. for partial scoring\'');
        $this->addSql('ALTER TABLE external_run ADD score NUMERIC(32, 9) DEFAULT \'0.000000000\' NOT NULL COMMENT \'Optional score for this run, e.g. for partial scoring\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE external_run DROP score');
        $this->addSql('ALTER TABLE external_judgement DROP score');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
