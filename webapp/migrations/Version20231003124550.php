<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20231003124550 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ranknumber column for sorting contests';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contest ADD COLUMN `ranknumber` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT \'Determines order of the contests\'');
        $this->addSql(<<<SQL
SET @rank = 0;
UPDATE contest
SET ranknumber = (@rank := @rank + 1)
ORDER BY cid;
SQL
        );
        $this->addSql('ALTER TABLE contest ADD CONSTRAINT `ranknumber_unique` UNIQUE (`ranknumber`)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contest DROP INDEX `ranknumber_unique`');
        $this->addSql('ALTER TABLE contest DROP COLUMN `ranknumber`');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
