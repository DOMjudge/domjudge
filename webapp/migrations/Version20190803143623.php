<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20190803143623 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'split contest.public in contest.public and contest.open_to_all_teams';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<SQL
ALTER TABLE `contest`
  CHANGE COLUMN `public` `public` tinyint(1) UNSIGNED DEFAULT 1 NOT NULL COMMENT 'Is this contest visible for the public?',
  ADD    COLUMN `open_to_all_teams` tinyint(1) UNSIGNED DEFAULT 1 NOT NULL COMMENT 'Is this contest open to all teams?' AFTER `public`
SQL
        );
        $this->addSql("UPDATE `contest` SET `open_to_all_teams` = `public`");
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<SQL
ALTER TABLE `contest`
    DROP   COLUMN `open_to_all_teams`,
    CHANGE COLUMN `public` `public` tinyint(1) unsigned DEFAULT 1 COMMENT 'Is this contest visible for the public and non-associated teams?'
SQL
        );
    }
}
