<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190803151406 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'change table structure to reflect Doctrine entities';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<SQL
ALTER TABLE `contest`
    CHANGE COLUMN `cid` `cid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Contest ID',
    CHANGE COLUMN `finalizetime` `finalizetime` decimal(32, 9) UNSIGNED DEFAULT NULL COMMENT 'Time when contest was finalized, null if not yet',
    CHANGE COLUMN `process_balloons` `process_balloons` tinyint(1) UNSIGNED DEFAULT 1 NOT NULL COMMENT 'Will balloons be processed for this contest?'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `team`
    CHANGE COLUMN `teamid` `teamid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Team ID'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `contestteam`
    DROP INDEX `cid`,
    DROP INDEX `teamid`,
    ADD INDEX `IDX_8328F8554B30D9C4` (`cid`),
    ADD INDEX `IDX_8328F8554DD6ABF3` (`teamid`)
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `team_unread`
    DROP INDEX `mesgid`,
    ADD INDEX `IDX_3272D5F4DD6ABF3` (`teamid`),
    ADD INDEX `IDX_3272D5F9E88E262` (`mesgid`)
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `team_affiliation`
    CHANGE COLUMN `affilid` `affilid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Team affiliation ID',
    CHANGE COLUMN `externalid` `externalid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Team affiliation ID in an external system'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `team_category`
    CHANGE COLUMN `categoryid` `categoryid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Team category ID',
    CHANGE COLUMN `sortorder` `sortorder` TINYINT(1) UNSIGNED DEFAULT 0 NOT NULL COMMENT 'Where to sort this category on the scoreboard(DC2Type:tinyint)'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `language`
    CHANGE COLUMN `langid` `langid` VARCHAR(32) NOT NULL COMMENT 'Language ID (string)',
    CHANGE COLUMN `extensions` `extensions` LONGTEXT DEFAULT NULL COMMENT 'List of recognized extensions (JSON encoded)(DC2Type:json)',
    ADD KEY `compile_script` (`compile_script`),
    ADD CONSTRAINT `language_ibfk_1` FOREIGN KEY (`compile_script`) REFERENCES `executable` (`execid`) ON DELETE SET NULL
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `problem`
    CHANGE COLUMN `probid` `probid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Problem ID',
    CHANGE COLUMN `externalid` `externalid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Problem ID in an external system, should be unique inside a single contest',
    ADD KEY `special_run` (`special_run`),
    ADD KEY `special_compare` (`special_compare`),
    ADD CONSTRAINT `problem_ibfk_1` FOREIGN KEY (`special_run`) REFERENCES `executable` (`execid`) ON DELETE SET NULL,
    ADD CONSTRAINT `problem_ibfk_2` FOREIGN KEY (`special_compare`) REFERENCES `executable` (`execid`) ON DELETE SET NULL
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `submission`
    CHANGE COLUMN `submitid` `submitid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Submission ID',
    CHANGE COLUMN `externalid` `externalid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Specifies ID of submission if imported from external CCS, e.g. Kattis',
    CHANGE COLUMN `expected_results` `expected_results` varchar(255) DEFAULT NULL COMMENT 'JSON encoded list of expected results - used to validate jury submissions(DC2Type:json)',
    ADD KEY `probid_2` (`cid`,`probid`),
    ADD CONSTRAINT `submission_ibfk_8` FOREIGN KEY (`cid`,`probid`) REFERENCES `contestproblem` (`cid`,`probid`) ON DELETE CASCADE
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `clarification`
    CHANGE COLUMN `clarid` `clarid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Clarification ID',
    CHANGE COLUMN `externalid` `externalid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Clarification ID in an external system, should be unique inside a single contest',
    ADD KEY `sender` (`sender`),
    ADD KEY `recipient` (`recipient`),
    ADD CONSTRAINT `clarification_ibfk_4` FOREIGN KEY (`sender`) REFERENCES `team` (`teamid`) ON DELETE CASCADE,
    ADD CONSTRAINT `clarification_ibfk_5` FOREIGN KEY (`recipient`) REFERENCES `team` (`teamid`) ON DELETE CASCADE
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `submission_file`
    CHANGE COLUMN `submitfileid` `submitfileid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Submission file ID',
    CHANGE COLUMN `sourcecode` `sourcecode` LONGBLOB NOT NULL COMMENT 'Full source code(DC2Type:blobtext)'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `configuration`
    CHANGE COLUMN `configid` `configid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Configuration ID',
    CHANGE COLUMN `value` `value` LONGTEXT NOT NULL COMMENT 'Content of the configuration variable (JSON encoded)(DC2Type:json)'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `testcase`
    CHANGE COLUMN `testcaseid` `testcaseid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Testcase ID',
    CHANGE COLUMN `rank` `rank` INT(4) UNSIGNED NOT NULL COMMENT 'Determines order of the testcases in judging'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `judgehost_restriction`
    CHANGE COLUMN `restrictionid` `restrictionid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Judgehost restriction ID',
    CHANGE COLUMN `restrictions` `restrictions` LONGTEXT DEFAULT NULL COMMENT 'JSON-encoded restrictions(DC2Type:json)'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `role`
    CHANGE COLUMN `roleid` `roleid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Role ID'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `internal_error`
    CHANGE COLUMN `errorid` `errorid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Internal error ID',
    CHANGE COLUMN `disabled` `disabled` TEXT NOT NULL COMMENT 'Disabled stuff, JSON-encoded(DC2Type:json)',
    CHANGE COLUMN `status` `status` ENUM('open', 'resolved', 'ignored') DEFAULT 'open' NOT NULL COMMENT 'Status of internal error(DC2Type:internal_error_status)'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `scorecache`
    CHANGE COLUMN `cid` `cid` int(4) UNSIGNED NOT NULL COMMENT 'Contest ID',
    CHANGE COLUMN `teamid` `teamid` int(4) UNSIGNED NOT NULL COMMENT 'Team ID',
    CHANGE COLUMN `probid` `probid` int(4) UNSIGNED NOT NULL COMMENT 'Problem ID',
    ADD KEY `cid` (`cid`),
    ADD KEY `teamid` (`teamid`),
    ADD KEY `probid` (`probid`),
    ADD CONSTRAINT `scorecache_ibfk_1` FOREIGN KEY (`cid`) REFERENCES `contest` (`cid`) ON DELETE CASCADE,
    ADD CONSTRAINT `scorecache_ibfk_2` FOREIGN KEY (`teamid`) REFERENCES `team` (`teamid`) ON DELETE CASCADE,
    ADD CONSTRAINT `scorecache_ibfk_3` FOREIGN KEY (`probid`) REFERENCES `problem` (`probid`) ON DELETE CASCADE
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `rankcache`
    CHANGE COLUMN `cid` `cid` int(4) UNSIGNED NOT NULL COMMENT 'Contest ID',
    CHANGE COLUMN `teamid` `teamid` int(4) UNSIGNED NOT NULL COMMENT 'Team ID',
    ADD KEY `cid` (`cid`),
    ADD KEY `teamid` (`teamid`),
    ADD CONSTRAINT `rankcache_ibfk_1` FOREIGN KEY (`cid`) REFERENCES `contest` (`cid`) ON DELETE CASCADE,
    ADD CONSTRAINT `rankcache_ibfk_2` FOREIGN KEY (`teamid`) REFERENCES `team` (`teamid`) ON DELETE CASCADE
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `event`
    CHANGE COLUMN `eventid` `eventid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Event ID',
    CHANGE COLUMN `content` `content` LONGBLOB NOT NULL COMMENT 'JSON encoded content of the change, as provided in the event feed(DC2Type:binaryjson)'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `user`
    CHANGE COLUMN `userid` `userid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'User ID'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `userrole`
    DROP INDEX `userid`,
    DROP INDEX `roleid`,
    ADD INDEX `IDX_F114F21BF132696E` (`userid`),
    ADD INDEX `IDX_F114F21B2D46D92A` (`roleid`)
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `balloon`
    CHANGE COLUMN `balloonid` `balloonid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Balloon ID'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `auditlog`
    CHANGE COLUMN `logid` `logid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Audit log ID'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `rejudging`
    CHANGE COLUMN `rejudgingid` `rejudgingid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Rejudging ID'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `judging`
    CHANGE COLUMN `judgingid` `judgingid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Judging ID'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `judging_run`
    CHANGE COLUMN `runid` `runid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Run ID'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `executable`
    CHANGE COLUMN `execid` `execid` VARCHAR(32) NOT NULL COMMENT 'Executable ID (string)'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `removed_interval`
    CHANGE COLUMN `intervalid` `intervalid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Removed interval ID'
SQL
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<SQL
ALTER TABLE `contest`
    CHANGE COLUMN `cid` `cid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
    CHANGE COLUMN `finalizetime` `finalizetime` decimal(32,9) DEFAULT NULL COMMENT 'Time when contest was finalized, null if not yet',
    CHANGE COLUMN `process_balloons` `process_balloons` tinyint(1) unsigned DEFAULT 1 COMMENT 'Will balloons be processed for this contest?'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `team`
    CHANGE COLUMN `teamid` `teamid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `contestteam`
    DROP INDEX `IDX_8328F8554B30D9C4`,
    DROP INDEX `IDX_8328F8554DD6ABF3`,
    ADD INDEX `cid` (`cid`),
    ADD INDEX `teamid` (`teamid`)
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `team_unread`
    DROP INDEX `IDX_3272D5F4DD6ABF3`,
    DROP INDEX `IDX_3272D5F9E88E262`,
    ADD INDEX `mesgid` (`mesgid`)
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `team_affiliation`
    CHANGE COLUMN `affilid` `affilid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
    CHANGE COLUMN `externalid` `externalid` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Team affiliation ID in an external system'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `team_category`
    CHANGE COLUMN `categoryid` `categoryid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
    CHANGE COLUMN `sortorder` `sortorder` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT 'Where to sort this category on the scoreboard'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `language`
    CHANGE COLUMN `langid` `langid` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Unique ID (string)',
    CHANGE COLUMN `extensions` `extensions` longtext COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'List of recognized extensions (JSON encoded)',
    DROP FOREIGN KEY `language_ibfk_1`,
    DROP INDEX `compile_script`
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `problem`
    CHANGE COLUMN `probid` `probid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
    CHANGE COLUMN `externalid` `externalid` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Problem ID in an external system, should be unique inside a single contest',
    DROP FOREIGN KEY `problem_ibfk_1`,
    DROP FOREIGN KEY `problem_ibfk_2`,
    DROP INDEX `special_run`,
    DROP INDEX `special_compare`
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `submission`
    CHANGE COLUMN `submitid` `submitid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
    CHANGE COLUMN `externalid` `externalid` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Specifies ID of submission if imported from external CCS, e.g. Kattis',
    CHANGE COLUMN `expected_results` `expected_results` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'JSON encoded list of expected results - used to validate jury submissions',
    DROP FOREIGN KEY `submission_ibfk_8`,
    DROP INDEX `probid_2`
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `clarification`
    CHANGE COLUMN `clarid` `clarid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
    CHANGE COLUMN `externalid` `externalid` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Clarification ID in an external system, should be unique inside a single contest',
    DROP FOREIGN KEY `clarification_ibfk_4`,
    DROP FOREIGN KEY `clarification_ibfk_5`,
    DROP INDEX `sender`,
    DROP INDEX `recipient`
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `submission_file`
    CHANGE COLUMN `submitfileid` `submitfileid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
    CHANGE COLUMN `sourcecode` `sourcecode` longblob NOT NULL COMMENT 'Full source code'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `configuration`
    CHANGE COLUMN `configid` `configid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
    CHANGE COLUMN `value` `value` longtext COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Content of the configuration variable (JSON encoded)'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `testcase`
    CHANGE COLUMN `testcaseid` `testcaseid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
    CHANGE COLUMN `rank` `rank` int(4) NOT NULL COMMENT 'Determines order of the testcases in judging'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `judgehost_restriction`
    CHANGE COLUMN `restrictionid` `restrictionid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
    CHANGE COLUMN `restrictions` `restrictions` longtext COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'JSON-encoded restrictions'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `role`
    CHANGE COLUMN `roleid` `roleid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `internal_error`
    CHANGE COLUMN `errorid` `errorid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
    CHANGE COLUMN `disabled` `disabled` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Disabled stuff, JSON-encoded',
    CHANGE COLUMN `status` `status` enum('open','resolved','ignored') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open' COMMENT 'Status of internal error'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `scorecache`
    CHANGE COLUMN `cid` `cid` int(4) unsigned NOT NULL COMMENT 'Contest ID',
    CHANGE COLUMN `teamid` `teamid` int(4) unsigned NOT NULL COMMENT 'Team ID',
    CHANGE COLUMN `probid` `probid` int(4) unsigned NOT NULL COMMENT 'Problem ID',
    DROP FOREIGN KEY `scorecache_ibfk_1`,
    DROP FOREIGN KEY `scorecache_ibfk_2`,
    DROP FOREIGN KEY `scorecache_ibfk_3`,
    DROP INDEX `cid`,
    DROP INDEX `teamid`,
    DROP INDEX `probid`
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `rankcache`
    CHANGE COLUMN `cid` `cid` int(4) unsigned NOT NULL COMMENT 'Contest ID',
    CHANGE COLUMN `teamid` `teamid` int(4) unsigned NOT NULL COMMENT 'Team ID',
    DROP FOREIGN KEY `rankcache_ibfk_1`,
    DROP FOREIGN KEY `rankcache_ibfk_2`,
    DROP INDEX `cid`,
    DROP INDEX `teamid`
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `event`
    CHANGE COLUMN `eventid` `eventid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
    CHANGE COLUMN `content` `content` longblob NOT NULL COMMENT 'JSON encoded content of the change, as provided in the event feed'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `user`
    CHANGE COLUMN `userid` `userid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `userrole`
    DROP INDEX `IDX_F114F21BF132696E`,
    DROP INDEX `IDX_F114F21B2D46D92A`,
    ADD INDEX `userid` (`userid`),
    ADD INDEX `roleid` (`roleid`)
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `balloon`
    CHANGE COLUMN `balloonid` `balloonid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `auditlog`
    CHANGE COLUMN `logid` `logid` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `rejudging`
    CHANGE COLUMN `rejudgingid` `rejudgingid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `judging`
    CHANGE COLUMN `judgingid` `judgingid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `judging_run`
    CHANGE COLUMN `runid` `runid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `executable`
    CHANGE COLUMN `execid` `execid` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Unique ID (string)'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `removed_interval`
    CHANGE COLUMN `intervalid` `intervalid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID'
SQL
        );
    }
}
