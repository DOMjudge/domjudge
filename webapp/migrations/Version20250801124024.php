<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250801124024 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add category types (bitset), css_class field, and make sortorder nullable for TeamCategory';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<SQL
            ALTER TABLE team_category
            ADD types INT NOT NULL DEFAULT 1 COMMENT 'Bitmask of category types, default is scoring.' AFTER name,
            CHANGE sortorder sortorder TINYINT UNSIGNED DEFAULT NULL COMMENT 'Where to sort this category on the scoreboard',
            ADD css_class VARCHAR(255) DEFAULT NULL COMMENT 'CSS class to apply to scoreboard rows (only for TYPE_CSS_CLASS)' AFTER allow_self_registration
            SQL);

        // Now update the types for existing categories based on whether the color is set
        // 7 = scoring + background + badge top
        // 5 = scoring + badge top
        $this->addSql('UPDATE team_category SET types = 7 WHERE color IS NOT NULL');
        $this->addSql('UPDATE team_category SET types = 5 WHERE color IS NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<SQL
            ALTER TABLE team_category
            DROP types,
            DROP css_class,
            CHANGE sortorder sortorder TINYINT UNSIGNED DEFAULT 0 NOT NULL COMMENT 'Where to sort this category on the scoreboard'
            SQL);
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
