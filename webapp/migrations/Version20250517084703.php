<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250517084703 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contest ADD opt_score_as_score_tiebreaker TINYINT(1) DEFAULT 0 NOT NULL COMMENT \'Use objective-score–based ranking instead of exact match\', ADD opt_score_order VARCHAR(10) DEFAULT \'asc\' NOT NULL COMMENT \'Order to apply for objective score: asc(smaller-better) or desc(larger-better)\', DROP similarity_as_score_tiebreaker, DROP similarity_order');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contest ADD similarity_as_score_tiebreaker TINYINT(1) DEFAULT 0 NOT NULL COMMENT \'Use similarity-based scoring instead of exact match\', ADD similarity_order VARCHAR(10) DEFAULT \'asc\' NOT NULL COMMENT \'Order to apply for similarity-based scoring: asc or desc\', DROP opt_score_as_score_tiebreaker, DROP opt_score_order');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
