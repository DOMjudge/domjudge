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
            `points_percentage` float unsigned NOT NULL DEFAULT '0' COMMENT 'Percentage of problem points this group is worth',
            `name` varchar(255) DEFAULT NULL COMMENT 'Which part of the problem this group tests',
            PRIMARY KEY (`testcasegroupid`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores testcase groups per problem'
        SQL
        );

        $this->addSql(<<<SQL
        ALTER TABLE `testcase` ADD COLUMN `testcasegroupid` int(4) unsigned DEFAULT NULL COMMENT 'Testcase group ID' AFTER `probid`
        SQL
        );

        $problems = $this->connection->fetchAllAssociative(<<<SQL
        SELECT DISTINCT p.`probid` FROM `problem` p
        JOIN `testcase` t ON p.`probid` = t.`probid`
        SQL
        );

        foreach ($problems as $problem) {
            $this->addSql(<<<SQL
            INSERT INTO `testcase_group` (`points_percentage`, `name`)
            VALUES (1, 'default')
            SQL
            );

            $this->addSql(<<<SQL
            UPDATE `testcase` SET `testcasegroupid` = LAST_INSERT_ID()
            WHERE `probid` = :problemId
            SQL, [
                'problemId' => $problem['probid']
            ]);
        }

        $this->addSql(<<<SQL
            ALTER TABLE `testcase` MODIFY `testcasegroupid` int(4) unsigned,
            ADD CONSTRAINT `testcase_ibfk` FOREIGN KEY (`testcasegroupid`) REFERENCES `testcase_group` (`testcasegroupid`);
            SQL
        );

        $this->addSql(<<<SQL
        ALTER TABLE `judging` ADD COLUMN `points_scored` float unsigned NOT NULL DEFAULT 0 COMMENT 'Points scored in this judging' AFTER `result`
        SQL
        );

        $this->addSql(<<<SQL
        ALTER TABLE `contestproblem` ADD COLUMN `partial_points_scoring` tinyint(1) unsigned DEFAULT NULL COMMENT 'Whether to score this problem partially; if set this overrides the global configuration setting' AFTER `lazy_eval_results`
        SQL
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<SQL
        ALTER TABLE `testcase` DROP CONSTRAINT `testcase_ibfk`
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

        $this->addSql(<<<SQL
        ALTER TABLE `contestproblem` DROP COLUMN `partial_points_scoring`
        SQL
        );
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
