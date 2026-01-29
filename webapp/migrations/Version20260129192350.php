<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260129192350 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Use judgements from external source by default.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contest CHANGE external_source_use_judgements external_source_use_judgements TINYINT(1) DEFAULT 1 NOT NULL COMMENT \'Use external judgements for results and scoring?\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contest CHANGE external_source_use_judgements external_source_use_judgements TINYINT(1) DEFAULT 0 NOT NULL COMMENT \'Use external judgements for results and scoring?\'');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
