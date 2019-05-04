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
  CHANGE COLUMN `public` `public` tinyint(1) UNSIGNED DEFAULT '1' COMMENT 'Is this contest visible for the public?',
  ADD    COLUMN `open_to_all_teams` tinyint(1) UNSIGNED DEFAULT '1' COMMENT 'Is this contest open to all teams?' AFTER `public`;

-- Create external judgement/run tables
CREATE TABLE `external_judgement` (
  `extjudgementid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
  `externalid` varchar(255) DEFAULT NULL COMMENT 'Judgement ID in external system, should be unique inside a single contest',
  `cid` int(4) unsigned NOT NULL COMMENT 'Contest ID',
  `submitid` int(4) unsigned NOT NULL COMMENT 'Submission ID being judged by external system',
  `result` varchar(32) DEFAULT NULL COMMENT 'Result string as obtained from external system. null if not finished yet',
  `starttime` decimal(32,9) unsigned NOT NULL COMMENT 'Time judging started',
  `endtime` decimal(32,9) unsigned DEFAULT NULL COMMENT 'Time judging ended, null = still busy',
  `valid` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT 'Old external judgement is marked as invalid when receiving a new one',
  PRIMARY KEY  (`extjudgementid`),
  UNIQUE KEY `externalid` (`cid`,`externalid`(190)),
  KEY `submitid` (`submitid`),
  CONSTRAINT `external_judgement_ibfk_1` FOREIGN KEY (`submitid`) REFERENCES `submission` (`submitid`) ON DELETE CASCADE,
  CONSTRAINT `external_judgement_ibfk_2` FOREIGN KEY (`cid`) REFERENCES `contest` (`cid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Judgement in external system';

CREATE TABLE `external_run` (
  `extrunid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
  `extjudgementid` int(4) unsigned NOT NULL COMMENT 'Judging ID this run belongs to',
  `testcaseid` int(4) unsigned NOT NULL COMMENT 'Testcase ID',
  `externalid` varchar(255) DEFAULT NULL COMMENT 'Run ID in external system, should be unique inside a single contest',
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
  `testcaseid` int(4) unsigned NOT NULL COMMENT 'Testcase ID',
  `input` longblob DEFAULT NULL COMMENT 'Input data',
  `output` longblob DEFAULT NULL COMMENT 'Output data',
  `image` longblob DEFAULT NULL COMMENT 'A graphical representation of the testcase',
  `image_thumb` longblob DEFAULT NULL COMMENT 'Aumatically created thumbnail of the image',
  PRIMARY KEY  (`testcaseid`),
  CONSTRAINT `testcase_content_ibfk_1` FOREIGN KEY (`testcaseid`) REFERENCES `testcase` (`testcaseid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores contents of testcase';

CREATE TABLE `judging_run_output` (
  `runid` int(4) unsigned NOT NULL COMMENT 'Run ID',
  `output_run` longblob DEFAULT NULL COMMENT 'Output of running the program',
  `output_diff` longblob DEFAULT NULL COMMENT 'Diffing the program output and testcase output',
  `output_error` longblob DEFAULT NULL COMMENT 'Standard error output of the program',
  `output_system` longblob DEFAULT NULL COMMENT 'Judging system output',
  PRIMARY KEY  (`runid`),
  CONSTRAINT `judging_run_output_ibfk_1` FOREIGN KEY (`runid`) REFERENCES `judging_run` (`runid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores output of judging run';

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
