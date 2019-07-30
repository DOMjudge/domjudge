-- This script upgrades table structure, data, and privileges
-- from/to the exact version numbers specified in the filename.

--
-- First execute a check whether this upgrade should apply. The check
-- below should fail if this upgrade has already been applied, but
-- keep everything unchanged if not.
--

-- @UPGRADE-CHECK@
ALTER TABLE `contest` ADD  COLUMN `open_to_all_teams` tinyint(1);
ALTER TABLE `contest` DROP COLUMN `open_to_all_teams`;

--
-- Create additional structures
--

ALTER TABLE `contest`
  CHANGE COLUMN `public` `public` tinyint(1) UNSIGNED DEFAULT 1 NOT NULL COMMENT 'Is this contest visible for the public?',
  ADD    COLUMN `open_to_all_teams` tinyint(1) UNSIGNED DEFAULT 1 NOT NULL COMMENT 'Is this contest open to all teams?' AFTER `public`;

-- Create external judgement/run tables
CREATE TABLE `external_judgement` (
  `extjudgementid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'External judgement ID',
  `externalid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Judgement ID in external system, should be unique inside a single contest',
  `cid` int(4) unsigned NOT NULL COMMENT 'Contest ID',
  `submitid` int(4) unsigned NOT NULL COMMENT 'Submission ID being judged by external system',
  `result` varchar(32) DEFAULT NULL COMMENT 'Result string as obtained from external system. null if not finished yet',
  `starttime` decimal(32,9) unsigned NOT NULL COMMENT 'Time judging started',
  `endtime` decimal(32,9) unsigned DEFAULT NULL COMMENT 'Time judging ended, null = still busy',
  `valid` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT 'Old external judgement is marked as invalid when receiving a new one',
  PRIMARY KEY  (`extjudgementid`),
  UNIQUE KEY `externalid` (`cid`,`externalid`(190)),
  KEY `submitid` (`submitid`),
  KEY `cid` (`cid`),
  CONSTRAINT `external_judgement_ibfk_1` FOREIGN KEY (`submitid`) REFERENCES `submission` (`submitid`) ON DELETE CASCADE,
  CONSTRAINT `external_judgement_ibfk_2` FOREIGN KEY (`cid`) REFERENCES `contest` (`cid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Judgement in external system';

CREATE TABLE `external_run` (
  `extrunid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'External run ID',
  `extjudgementid` int(4) unsigned NOT NULL COMMENT 'Judging ID this run belongs to',
  `testcaseid` int(4) unsigned NOT NULL COMMENT 'Testcase ID',
  `externalid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Run ID in external system, should be unique inside a single contest',
  `cid` int(4) unsigned NOT NULL COMMENT 'Contest ID',
  `result` varchar(32) NOT NULL COMMENT 'Result string as obtained from external system',
  `endtime` decimal(32,9) unsigned NOT NULL COMMENT 'Time run ended',
  `runtime` float NOT NULL COMMENT 'Running time on this testcase',
  PRIMARY KEY  (`extrunid`),
  UNIQUE KEY `externalid` (`cid`,`externalid`(190)),
  KEY `extjudgementid` (`extjudgementid`),
  KEY `testcaseid` (`testcaseid`),
  CONSTRAINT `external_run_ibfk_1` FOREIGN KEY (`extjudgementid`) REFERENCES `external_judgement` (`extjudgementid`) ON DELETE CASCADE,
  CONSTRAINT `external_run_ibfk_2` FOREIGN KEY (`testcaseid`) REFERENCES `testcase` (`testcaseid`) ON DELETE CASCADE,
  CONSTRAINT `external_run_ibfk_3` FOREIGN KEY (`cid`) REFERENCES `contest` (`cid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Run in external system';

-- Move fields with lots of data to separate tables
CREATE TABLE `testcase_content` (
  `testcaseid` int(4) UNSIGNED NOT NULL COMMENT 'Testcase ID',
  `input` longblob DEFAULT NULL COMMENT 'Input data(DC2Type:blobtext)',
  `output` longblob DEFAULT NULL COMMENT 'Output data(DC2Type:blobtext)',
  `image` longblob DEFAULT NULL COMMENT 'A graphical representation of the testcase(DC2Type:blobtext)',
  `image_thumb` longblob DEFAULT NULL COMMENT 'Automatically created thumbnail of the image(DC2Type:blobtext)',
  PRIMARY KEY  (`testcaseid`),
  CONSTRAINT `testcase_content_ibfk_1` FOREIGN KEY (`testcaseid`) REFERENCES `testcase` (`testcaseid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores contents of testcase';

CREATE TABLE `judging_run_output` (
  `runid` int(4) unsigned NOT NULL COMMENT 'Run ID',
  `output_run` longblob DEFAULT NULL COMMENT 'Output of running the program(DC2Type:blobtext)',
  `output_diff` longblob DEFAULT NULL COMMENT 'Diffing the program output and testcase output(DC2Type:blobtext)',
  `output_error` longblob DEFAULT NULL COMMENT 'Standard error output of the program(DC2Type:blobtext)',
  `output_system` longblob DEFAULT NULL COMMENT 'Judging system output(DC2Type:blobtext)',
  PRIMARY KEY  (`runid`),
  KEY `runid` (`runid`),
  CONSTRAINT `judging_run_output_ibfk_1` FOREIGN KEY (`runid`) REFERENCES `judging_run` (`runid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores output of judging run';

-- Change table structure to reflect Doctrine entities when appropriate
ALTER TABLE `contest`
    CHANGE COLUMN `cid` `cid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Contest ID',
    CHANGE COLUMN `finalizetime` `finalizetime` decimal(32, 9) UNSIGNED DEFAULT NULL COMMENT 'Time when contest was finalized, null if not yet',
    CHANGE COLUMN `process_balloons` `process_balloons` tinyint(1) UNSIGNED DEFAULT 1 NOT NULL COMMENT 'Will balloons be processed for this contest?';

ALTER TABLE `team`
    CHANGE COLUMN `teamid` `teamid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Team ID';

ALTER TABLE `contestteam`
    CHANGE COLUMN `cid` `cid` int(4) UNSIGNED NOT NULL COMMENT 'Contest ID',
    CHANGE COLUMN `teamid` `teamid` int(4) UNSIGNED NOT NULL COMMENT 'Team ID',
    DROP INDEX `cid`,
    DROP INDEX `teamid`,
    ADD INDEX `IDX_8328F8554B30D9C4` (`cid`),
    ADD INDEX `IDX_8328F8554DD6ABF3` (`teamid`);

ALTER TABLE `team_unread`
    CHANGE COLUMN `teamid` `teamid` int(4) UNSIGNED NOT NULL COMMENT 'Team ID',
    CHANGE COLUMN `mesgid` `mesgid` int(4) UNSIGNED NOT NULL COMMENT 'Clarification ID',
    DROP INDEX `mesgid`,
    ADD INDEX `IDX_3272D5F4DD6ABF3` (`teamid`),
    ADD INDEX `IDX_3272D5F9E88E262` (`mesgid`);

ALTER TABLE `team_affiliation`
    CHANGE COLUMN `affilid` `affilid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Team affiliation ID',
    CHANGE COLUMN `externalid` `externalid` VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_bin COMMENT 'Team affiliation ID in an external system';

ALTER TABLE `team_category`
    CHANGE COLUMN `categoryid` `categoryid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Team category ID',
    CHANGE COLUMN `sortorder` `sortorder` TINYINT(1) UNSIGNED DEFAULT 0 NOT NULL COMMENT 'Where to sort this category on the scoreboard(DC2Type:tinyint)';

ALTER TABLE `language`
    CHANGE COLUMN `langid` `langid` VARCHAR(32) NOT NULL COMMENT 'Language ID (string)',
    CHANGE COLUMN `extensions` `extensions` LONGTEXT DEFAULT NULL COMMENT 'List of recognized extensions (JSON encoded)(DC2Type:json)',
    ADD KEY `compile_script` (`compile_script`),
    ADD CONSTRAINT `language_ibfk_1` FOREIGN KEY (`compile_script`) REFERENCES `executable` (`execid`) ON DELETE SET NULL;

ALTER TABLE `problem`
    CHANGE COLUMN `probid` `probid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Problem ID',
    CHANGE COLUMN `externalid` `externalid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Problem ID in an external system, should be unique inside a single contest',
    ADD KEY `special_run` (`special_run`),
    ADD KEY `special_compare` (`special_compare`),
    ADD CONSTRAINT `problem_ibfk_1` FOREIGN KEY (`special_run`) REFERENCES `executable` (`execid`) ON DELETE SET NULL,
    ADD CONSTRAINT `problem_ibfk_2` FOREIGN KEY (`special_compare`) REFERENCES `executable` (`execid`) ON DELETE SET NULL;

ALTER TABLE `contestproblem`
    CHANGE COLUMN `cid` `cid` int(4) UNSIGNED NOT NULL COMMENT 'Contest ID',
    CHANGE COLUMN `probid` `probid` int(4) UNSIGNED NOT NULL COMMENT 'Problem ID';

ALTER TABLE `submission`
    CHANGE COLUMN `submitid` `submitid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Submission ID',
    CHANGE COLUMN `externalid` `externalid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Specifies ID of submission if imported from external CCS, e.g. Kattis',
    CHANGE COLUMN `expected_results` `expected_results` varchar(255) DEFAULT NULL COMMENT 'JSON encoded list of expected results - used to validate jury submissions(DC2Type:json)',
    ADD KEY `probid_2` (`cid`,`probid`),
    ADD CONSTRAINT `submission_ibfk_8` FOREIGN KEY (`cid`,`probid`) REFERENCES `contestproblem` (`cid`,`probid`) ON DELETE CASCADE;

ALTER TABLE `clarification`
    CHANGE COLUMN `clarid` `clarid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Clarification ID',
    CHANGE COLUMN `externalid` `externalid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Clarification ID in an external system, should be unique inside a single contest',
    ADD KEY `sender` (`sender`),
    ADD KEY `recipient` (`recipient`),
    ADD CONSTRAINT `clarification_ibfk_4` FOREIGN KEY (`sender`) REFERENCES `team` (`teamid`) ON DELETE CASCADE,
    ADD CONSTRAINT `clarification_ibfk_5` FOREIGN KEY (`recipient`) REFERENCES `team` (`teamid`) ON DELETE CASCADE;

ALTER TABLE `submission_file`
    CHANGE COLUMN `submitfileid` `submitfileid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Submission file ID',
    CHANGE COLUMN `sourcecode` `sourcecode` LONGBLOB NOT NULL COMMENT 'Full source code(DC2Type:blobtext)';

ALTER TABLE `configuration`
    CHANGE COLUMN `configid` `configid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Configuration ID',
    CHANGE COLUMN `value` `value` LONGTEXT NOT NULL COMMENT 'Content of the configuration variable (JSON encoded)(DC2Type:json)';

ALTER TABLE `testcase`
    CHANGE COLUMN `testcaseid` `testcaseid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Testcase ID',
    CHANGE COLUMN `rank` `rank` INT(4) UNSIGNED NOT NULL COMMENT 'Determines order of the testcases in judging';

ALTER TABLE `judgehost_restriction`
    CHANGE COLUMN `restrictionid` `restrictionid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Judgehost restriction ID',
    CHANGE COLUMN `restrictions` `restrictions` LONGTEXT DEFAULT NULL COMMENT 'JSON-encoded restrictions(DC2Type:json)';

ALTER TABLE `role`
    CHANGE COLUMN `roleid` `roleid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Role ID';

ALTER TABLE `internal_error`
    CHANGE COLUMN `errorid` `errorid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Internal error ID',
    CHANGE COLUMN `disabled` `disabled` TEXT NOT NULL COMMENT 'Disabled stuff, JSON-encoded(DC2Type:json)',
    CHANGE COLUMN `status` `status` ENUM('open', 'resolved', 'ignored') DEFAULT 'open' NOT NULL COMMENT 'Status of internal error(DC2Type:internal_error_status)';

ALTER TABLE `scorecache`
    CHANGE COLUMN `cid` `cid` int(4) UNSIGNED NOT NULL COMMENT 'Contest ID',
    CHANGE COLUMN `teamid` `teamid` int(4) UNSIGNED NOT NULL COMMENT 'Team ID',
    CHANGE COLUMN `probid` `probid` int(4) UNSIGNED NOT NULL COMMENT 'Problem ID',
    ADD KEY `cid` (`cid`),
    ADD KEY `teamid` (`teamid`),
    ADD KEY `probid` (`probid`),
    ADD CONSTRAINT `scorecache_ibfk_1` FOREIGN KEY (`cid`) REFERENCES `contest` (`cid`) ON DELETE CASCADE,
    ADD CONSTRAINT `scorecache_ibfk_2` FOREIGN KEY (`teamid`) REFERENCES `team` (`teamid`) ON DELETE CASCADE,
    ADD CONSTRAINT `scorecache_ibfk_3` FOREIGN KEY (`probid`) REFERENCES `problem` (`probid`) ON DELETE CASCADE;

ALTER TABLE `rankcache`
    CHANGE COLUMN `cid` `cid` int(4) UNSIGNED NOT NULL COMMENT 'Contest ID',
    CHANGE COLUMN `teamid` `teamid` int(4) UNSIGNED NOT NULL COMMENT 'Team ID',
    ADD KEY `cid` (`cid`),
    ADD KEY `teamid` (`teamid`),
    ADD CONSTRAINT `rankcache_ibfk_1` FOREIGN KEY (`cid`) REFERENCES `contest` (`cid`) ON DELETE CASCADE,
    ADD CONSTRAINT `rankcache_ibfk_2` FOREIGN KEY (`teamid`) REFERENCES `team` (`teamid`) ON DELETE CASCADE;

ALTER TABLE `event`
    CHANGE COLUMN `eventid` `eventid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Event ID',
    CHANGE COLUMN `content` `content` LONGBLOB NOT NULL COMMENT 'JSON encoded content of the change, as provided in the event feed(DC2Type:binaryjson)';

ALTER TABLE `user`
    CHANGE COLUMN `userid` `userid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'User ID';

ALTER TABLE `userrole`
    CHANGE COLUMN `userid` `userid` int(4) UNSIGNED NOT NULL COMMENT 'User ID',
    CHANGE COLUMN `roleid` `roleid` int(4) UNSIGNED NOT NULL COMMENT 'Role ID',
    DROP INDEX `userid`,
    DROP INDEX `roleid`,
    ADD INDEX `IDX_F114F21BF132696E` (`userid`),
    ADD INDEX `IDX_F114F21B2D46D92A` (`roleid`);

ALTER TABLE `balloon`
    CHANGE COLUMN `balloonid` `balloonid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Balloon ID';

ALTER TABLE `auditlog`
    CHANGE COLUMN `logid` `logid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Audit log ID';

ALTER TABLE `rejudging`
    CHANGE COLUMN `rejudgingid` `rejudgingid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Rejudging ID';

ALTER TABLE `judging`
    CHANGE COLUMN `judgingid` `judgingid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Judging ID';

ALTER TABLE `judging_run`
    CHANGE COLUMN `runid` `runid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Run ID';

ALTER TABLE `executable`
    CHANGE COLUMN `execid` `execid` VARCHAR(32) NOT NULL COMMENT 'Executable ID (string)';

ALTER TABLE `removed_interval`
    CHANGE COLUMN `intervalid` `intervalid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Removed interval ID';

--
-- Transfer data from old to new structure
--

INSERT INTO `testcase_content` (`testcaseid`, `input`, `output`, `image`, `image_thumb`)
    SELECT `testcaseid`, `input`, `output`, `image`, `image_thumb` FROM `testcase`;

INSERT INTO `judging_run_output` (`runid`, `output_run`, `output_diff`, `output_error`, `output_system`)
    SELECT `runid`, `output_run`, `output_diff`, `output_error`, `output_system` FROM `judging_run`;

UPDATE `contest` SET `open_to_all_teams` = `public`;

--
-- Add/remove sample/initial contents
--

INSERT INTO `configuration` (`name`, `value`, `type`, `public`, `category`, `description`) VALUES
('external_ccs_submission_url', '""', 'string', '0', 'Misc', 'URL of a submission detail page on the external CCS. Placeholder :id: will be replaced by submission ID. Leave empty to not display links to external CCS');

--
-- Finally remove obsolete structures after moving data
--

ALTER TABLE `submission`
    DROP COLUMN `externalresult`;

ALTER TABLE `testcase`
    DROP COLUMN `input`,
    DROP COLUMN `output`,
    DROP COLUMN `image`,
    DROP COLUMN `image_thumb`;

ALTER TABLE `judging_run`
    DROP COLUMN `output_run`,
    DROP COLUMN `output_diff`,
    DROP COLUMN `output_error`,
    DROP COLUMN `output_system`;
