<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20190803151406 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Change table structure to reflect Doctrine entities.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        // We'll add some foreign key constraints later. First remove broken
        // references so that adding those constraints won't fail.
        $this->addSql(<<<SQL
UPDATE `language` l
    LEFT JOIN `executable` e ON (l.compile_script = e.execid)
    SET `compile_script` = NULL
    WHERE l.compile_script IS NOT NULL AND e.execid IS NULL;
SQL
        );
        $this->addSql(<<<SQL
UPDATE `problem` p
    LEFT JOIN `executable` e ON (p.special_run = e.execid)
    SET `special_run` = NULL
    WHERE p.special_run IS NOT NULL AND e.execid IS NULL;
SQL
        );
        $this->addSql(<<<SQL
UPDATE `problem` p
    LEFT JOIN `executable` e ON (p.special_compare = e.execid)
    SET `special_compare` = NULL
    WHERE p.special_compare IS NOT NULL AND e.execid IS NULL;
SQL
        );
        $this->addSql(<<<SQL
DELETE s FROM `submission` s
    LEFT JOIN `contestproblem` cp ON (s.cid = cp.cid AND s.probid = cp.probid)
    WHERE cp.cid IS NULL;
SQL
        );
        $this->addSql(<<<SQL
DELETE c FROM `clarification` c
    LEFT JOIN `team` t ON (c.sender = t.teamid)
    WHERE c.sender IS NOT NULL AND t.teamid IS NULL;
SQL
        );
        $this->addSql(<<<SQL
DELETE c FROM `clarification` c
    LEFT JOIN `team` t ON (c.recipient = t.teamid)
    WHERE c.recipient IS NOT NULL AND t.teamid IS NULL;
SQL
        );
        $this->addSql('TRUNCATE TABLE `scorecache`');
        $this->addSql('TRUNCATE TABLE `rankcache`');

        $this->addSql(<<<SQL
ALTER TABLE `contest`
    MODIFY COLUMN `cid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Contest ID',
    MODIFY COLUMN `finalizetime` decimal(32, 9) UNSIGNED DEFAULT NULL COMMENT 'Time when contest was finalized, null if not yet',
    MODIFY COLUMN `process_balloons` tinyint(1) UNSIGNED DEFAULT 1 NOT NULL COMMENT 'Will balloons be processed for this contest?'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `team`
    MODIFY COLUMN `teamid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Team ID'
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
    MODIFY COLUMN `affilid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Team affiliation ID',
    MODIFY COLUMN `externalid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Team affiliation ID in an external system'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `team_category`
    MODIFY COLUMN `categoryid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Team category ID',
    MODIFY COLUMN `sortorder` TINYINT(1) UNSIGNED DEFAULT 0 NOT NULL COMMENT 'Where to sort this category on the scoreboard(DC2Type:tinyint)'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `language`
    MODIFY COLUMN `langid` VARCHAR(32) NOT NULL COMMENT 'Language ID (string)',
    MODIFY COLUMN `extensions` LONGTEXT DEFAULT NULL COMMENT 'List of recognized extensions (JSON encoded)(DC2Type:json)',
    ADD KEY `compile_script` (`compile_script`),
    ADD CONSTRAINT `language_ibfk_1` FOREIGN KEY (`compile_script`) REFERENCES `executable` (`execid`) ON DELETE SET NULL
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `problem`
    MODIFY COLUMN `probid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Problem ID',
    MODIFY COLUMN `externalid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Problem ID in an external system, should be unique inside a single contest',
    ADD KEY `special_run` (`special_run`),
    ADD KEY `special_compare` (`special_compare`),
    ADD CONSTRAINT `problem_ibfk_1` FOREIGN KEY (`special_run`) REFERENCES `executable` (`execid`) ON DELETE SET NULL,
    ADD CONSTRAINT `problem_ibfk_2` FOREIGN KEY (`special_compare`) REFERENCES `executable` (`execid`) ON DELETE SET NULL
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `submission`
    MODIFY COLUMN `submitid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Submission ID',
    MODIFY COLUMN `externalid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Specifies ID of submission if imported from external CCS, e.g. Kattis',
    MODIFY COLUMN `expected_results` varchar(255) DEFAULT NULL COMMENT 'JSON encoded list of expected results - used to validate jury submissions(DC2Type:json)',
    ADD KEY `probid_2` (`cid`,`probid`),
    ADD CONSTRAINT `submission_ibfk_8` FOREIGN KEY (`cid`,`probid`) REFERENCES `contestproblem` (`cid`,`probid`) ON DELETE CASCADE
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `clarification`
    MODIFY COLUMN `clarid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Clarification ID',
    MODIFY COLUMN `externalid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Clarification ID in an external system, should be unique inside a single contest',
    ADD KEY `sender` (`sender`),
    ADD KEY `recipient` (`recipient`),
    ADD CONSTRAINT `clarification_ibfk_4` FOREIGN KEY (`sender`) REFERENCES `team` (`teamid`) ON DELETE CASCADE,
    ADD CONSTRAINT `clarification_ibfk_5` FOREIGN KEY (`recipient`) REFERENCES `team` (`teamid`) ON DELETE CASCADE
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `submission_file`
    MODIFY COLUMN `submitfileid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Submission file ID',
    MODIFY COLUMN `sourcecode` LONGBLOB NOT NULL COMMENT 'Full source code(DC2Type:blobtext)'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `configuration`
    MODIFY COLUMN `configid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Configuration ID',
    MODIFY COLUMN `value` LONGTEXT NOT NULL COMMENT 'Content of the configuration variable (JSON encoded)(DC2Type:json)'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `testcase`
    MODIFY COLUMN `testcaseid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Testcase ID',
    MODIFY COLUMN `rank` INT(4) UNSIGNED NOT NULL COMMENT 'Determines order of the testcases in judging'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `judgehost_restriction`
    MODIFY COLUMN `restrictionid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Judgehost restriction ID',
    MODIFY COLUMN `restrictions` LONGTEXT DEFAULT NULL COMMENT 'JSON-encoded restrictions(DC2Type:json)'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `role`
    MODIFY COLUMN `roleid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Role ID'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `internal_error`
    MODIFY COLUMN `errorid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Internal error ID',
    MODIFY COLUMN `disabled` TEXT NOT NULL COMMENT 'Disabled stuff, JSON-encoded(DC2Type:json)',
    MODIFY COLUMN `status` ENUM('open', 'resolved', 'ignored') DEFAULT 'open' NOT NULL COMMENT 'Status of internal error(DC2Type:internal_error_status)'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `scorecache`
    MODIFY COLUMN `cid` int(4) UNSIGNED NOT NULL COMMENT 'Contest ID',
    MODIFY COLUMN `teamid` int(4) UNSIGNED NOT NULL COMMENT 'Team ID',
    MODIFY COLUMN `probid` int(4) UNSIGNED NOT NULL COMMENT 'Problem ID',
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
    MODIFY COLUMN `cid` int(4) UNSIGNED NOT NULL COMMENT 'Contest ID',
    MODIFY COLUMN `teamid` int(4) UNSIGNED NOT NULL COMMENT 'Team ID',
    ADD KEY `cid` (`cid`),
    ADD KEY `teamid` (`teamid`),
    ADD CONSTRAINT `rankcache_ibfk_1` FOREIGN KEY (`cid`) REFERENCES `contest` (`cid`) ON DELETE CASCADE,
    ADD CONSTRAINT `rankcache_ibfk_2` FOREIGN KEY (`teamid`) REFERENCES `team` (`teamid`) ON DELETE CASCADE
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `event`
    MODIFY COLUMN `eventid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Event ID',
    MODIFY COLUMN `content` LONGBLOB NOT NULL COMMENT 'JSON encoded content of the change, as provided in the event feed(DC2Type:binaryjson)'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `user`
    MODIFY COLUMN `userid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'User ID'
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
    MODIFY COLUMN `balloonid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Balloon ID'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `auditlog`
    MODIFY COLUMN `logid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Audit log ID'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `rejudging`
    MODIFY COLUMN `rejudgingid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Rejudging ID'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `judging`
    MODIFY COLUMN `judgingid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Judging ID'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `judging_run`
    MODIFY COLUMN `runid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Run ID'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `executable`
    MODIFY COLUMN `execid` VARCHAR(32) NOT NULL COMMENT 'Executable ID (string)'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `removed_interval`
    MODIFY COLUMN `intervalid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Removed interval ID'
SQL
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<SQL
ALTER TABLE `contest`
    MODIFY COLUMN `cid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
    MODIFY COLUMN `finalizetime` decimal(32,9) DEFAULT NULL COMMENT 'Time when contest was finalized, null if not yet',
    MODIFY COLUMN `process_balloons` tinyint(1) unsigned DEFAULT 1 COMMENT 'Will balloons be processed for this contest?'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `team`
    MODIFY COLUMN `teamid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID'
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
    MODIFY COLUMN `affilid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
    MODIFY COLUMN `externalid` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Team affiliation ID in an external system'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `team_category`
    MODIFY COLUMN `categoryid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
    MODIFY COLUMN `sortorder` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT 'Where to sort this category on the scoreboard'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `language`
    MODIFY COLUMN `langid` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Unique ID (string)',
    MODIFY COLUMN `extensions` longtext COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'List of recognized extensions (JSON encoded)',
    DROP FOREIGN KEY `language_ibfk_1`,
    DROP INDEX `compile_script`
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `problem`
    MODIFY COLUMN `probid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
    MODIFY COLUMN `externalid` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Problem ID in an external system, should be unique inside a single contest',
    DROP FOREIGN KEY `problem_ibfk_1`,
    DROP FOREIGN KEY `problem_ibfk_2`,
    DROP INDEX `special_run`,
    DROP INDEX `special_compare`
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `submission`
    MODIFY COLUMN `submitid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
    MODIFY COLUMN `externalid` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Specifies ID of submission if imported from external CCS, e.g. Kattis',
    MODIFY COLUMN `expected_results` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'JSON encoded list of expected results - used to validate jury submissions',
    DROP FOREIGN KEY `submission_ibfk_8`,
    DROP INDEX `probid_2`
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `clarification`
    MODIFY COLUMN `clarid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
    MODIFY COLUMN `externalid` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Clarification ID in an external system, should be unique inside a single contest',
    DROP FOREIGN KEY `clarification_ibfk_4`,
    DROP FOREIGN KEY `clarification_ibfk_5`,
    DROP INDEX `sender`,
    DROP INDEX `recipient`
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `submission_file`
    MODIFY COLUMN `submitfileid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
    MODIFY COLUMN `sourcecode` longblob NOT NULL COMMENT 'Full source code'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `configuration`
    MODIFY COLUMN `configid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
    MODIFY COLUMN `value` longtext COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Content of the configuration variable (JSON encoded)'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `testcase`
    MODIFY COLUMN `testcaseid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
    MODIFY COLUMN `rank` int(4) NOT NULL COMMENT 'Determines order of the testcases in judging'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `judgehost_restriction`
    MODIFY COLUMN `restrictionid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
    MODIFY COLUMN `restrictions` longtext COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'JSON-encoded restrictions'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `role`
    MODIFY COLUMN `roleid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `internal_error`
    MODIFY COLUMN `errorid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
    MODIFY COLUMN `disabled` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Disabled stuff, JSON-encoded',
    MODIFY COLUMN `status` enum('open','resolved','ignored') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open' COMMENT 'Status of internal error'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `scorecache`
    MODIFY COLUMN `cid` int(4) unsigned NOT NULL COMMENT 'Contest ID',
    MODIFY COLUMN `teamid` int(4) unsigned NOT NULL COMMENT 'Team ID',
    MODIFY COLUMN `probid` int(4) unsigned NOT NULL COMMENT 'Problem ID',
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
    MODIFY COLUMN `cid` int(4) unsigned NOT NULL COMMENT 'Contest ID',
    MODIFY COLUMN `teamid` int(4) unsigned NOT NULL COMMENT 'Team ID',
    DROP FOREIGN KEY `rankcache_ibfk_1`,
    DROP FOREIGN KEY `rankcache_ibfk_2`,
    DROP INDEX `cid`,
    DROP INDEX `teamid`
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `event`
    MODIFY COLUMN `eventid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
    MODIFY COLUMN `content` longblob NOT NULL COMMENT 'JSON encoded content of the change, as provided in the event feed'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `user`
    MODIFY COLUMN `userid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID'
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
    MODIFY COLUMN `balloonid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `auditlog`
    MODIFY COLUMN `logid` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `rejudging`
    MODIFY COLUMN `rejudgingid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `judging`
    MODIFY COLUMN `judgingid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `judging_run`
    MODIFY COLUMN `runid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `executable`
    MODIFY COLUMN `execid` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Unique ID (string)'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `removed_interval`
    MODIFY COLUMN `intervalid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID'
SQL
        );
    }
}
