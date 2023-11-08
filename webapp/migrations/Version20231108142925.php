<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20231108142925 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<SQL
        CREATE TABLE `testcase_group` (
            `testcasegroupid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
            `probid` int(4) unsigned NOT NULL COMMENT 'Corresponding problem ID',
            `points_percentage` float unsigned NOT NULL DEFAULT '0' COMMENT 'Percentage of problem points this group is worth',
            `name` varchar(255) DEFAULT NULL COMMENT 'Which part of the problem this group tests',
            PRIMARY KEY (`testcasegroupid`),
            KEY `probid` (`probid`),
            CONSTRAINT `testcase_group_ibfk_1` FOREIGN KEY (`probid`) REFERENCES `problem` (`probid`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores testcase groups per problem'
        SQL
        );

        $this->addSql(<<<SQL
        ALTER TABLE `testcase` ADD COLUMN `testcasegroupid` int(4) unsigned DEFAULT NULL COMMENT 'Testcase group ID' AFTER `probid`
        SQL
        );
        $this->addSql(<<<SQL
        ALTER TABLE `testcase` ADD CONSTRAINT `testcase_ibfk_2` FOREIGN KEY (`testcasegroupid`) REFERENCES `testcase_group` (`testcasegroupid`) ON DELETE CASCADE
        SQL
        );

        $this->addSql(<<<SQL
        ALTER TABLE `judging` ADD COLUMN `points_scored` float unsigned DEFAULT NULL COMMENT 'Points scored in this judging' AFTER `result`
        SQL
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<SQL
        ALTER TABLE `testcase` DROP CONSTRAINT `testcase_ibfk_2`
        SQL
        );

        $this->addSql(<<<SQL
        ALTER TABLE `testcase` DROP COLUMN `testcasegroupid`
        SQL
        );

        $this->addSql(<<<SQL
        DROP TABLE `testcase_group`
        SQL
        );

        $this->addSql(<<<SQL
        ALTER TABLE `judging` DROP COLUMN `points_scored`
        SQL
        );
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
