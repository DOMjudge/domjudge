<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250517085220 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contest CHANGE opt_score_as_score_tiebreaker opt_score_as_score_tiebreaker TINYINT(1) DEFAULT 0 NOT NULL COMMENT \'Use optimization score ranking instead of exact match\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contest CHANGE opt_score_as_score_tiebreaker opt_score_as_score_tiebreaker TINYINT(1) DEFAULT 0 NOT NULL COMMENT \'Use objective-score–based ranking instead of exact match\'');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
