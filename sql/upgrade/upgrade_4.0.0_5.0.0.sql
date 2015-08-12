-- This script upgrades table structure, data, and privileges
-- from/to the exact version numbers specified in the filename.

--
-- First execute a check whether this upgrade should apply. The check
-- below should fail if this upgrade has already been applied, but
-- keep everything unchanged if not.
--

-- @UPGRADE-CHECK@
CREATE TABLE `contestproblem` (`dummy` int(4) UNSIGNED);
DROP TABLE `contestproblem`;

--
-- Create additional structures
--

ALTER TABLE `configuration`
  DROP KEY `name`,
  ADD UNIQUE KEY `name` (`name`);

ALTER TABLE `contest`
  CHANGE COLUMN `contestname` `name` varchar(255) NOT NULL COMMENT 'Descriptive name',
  ADD COLUMN `shortname` varchar(255) NOT NULL COMMENT 'Short name for this contest' AFTER `name`,
  ADD COLUMN `deactivatetime` decimal(32,9) unsigned DEFAULT NULL COMMENT 'Time contest becomes invisible in team/public views' AFTER `unfreezetime`,
  ADD COLUMN `deactivatetime_string` varchar(20) DEFAULT NULL COMMENT 'Authoritative absolute or relative string representation of deactivatetime' AFTER `unfreezetime_string`,
  ADD COLUMN `process_balloons` tinyint(1) unsigned DEFAULT '1' COMMENT 'Will balloons be processed for this contest?',
  ADD COLUMN `public` tinyint(1) unsigned DEFAULT '1' COMMENT 'Is this contest visible for the public and non-associated teams?';

-- We add a unique key on contest.shortname and contestproblem.shortname
-- later, after filling it with data.

-- Create a table linking contests and problems
CREATE TABLE `contestproblem` (
  `cid` int(4) unsigned NOT NULL COMMENT 'Contest ID',
  `probid` int(4) unsigned NOT NULL COMMENT 'Problem ID',
  `shortname` varchar(255) NOT NULL COMMENT 'Unique problem ID within contest (string)',
  `allow_submit` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'Are submissions accepted for this problem?',
  `allow_judge` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT 'Are submissions for this problem judged?',
  `color` varchar(25) DEFAULT NULL COMMENT 'Balloon colour to display on the scoreboard',
  `lazy_eval_results` tinyint(1) unsigned DEFAULT NULL COMMENT 'Whether to do lazy evaluation for this problem; if set this overrides the global configuration setting',
  PRIMARY KEY (`cid`,`probid`),
  KEY `cid` (`cid`),
  KEY `probid` (`probid`),
  CONSTRAINT `contestproblem_ibfk_1` FOREIGN KEY (`cid`) REFERENCES `contest` (`cid`) ON DELETE CASCADE,
  CONSTRAINT `contestproblem_ibfk_2` FOREIGN KEY (`probid`) REFERENCES `problem` (`probid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Many-to-Many mapping of contests and problems';

-- Create a table linking contests and teams
CREATE TABLE `contestteam` (
  `cid` int(4) unsigned NOT NULL COMMENT 'Contest ID',
  `teamid` int(4) unsigned NOT NULL COMMENT 'Team ID',
  PRIMARY KEY (`teamid`,`cid`),
  KEY `cid` (`cid`),
  KEY `teamid` (`teamid`),
  CONSTRAINT `contestteam_ibfk_1` FOREIGN KEY (`cid`) REFERENCES `contest` (`cid`) ON DELETE CASCADE,
  CONSTRAINT `contestteam_ibfk_2` FOREIGN KEY (`teamid`) REFERENCES `team` (`teamid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Many-to-Many mapping of contests and teams';

-- Create a table for judgehost restrictions
CREATE TABLE `judgehost_restriction` (
  `restrictionid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
  `name` varchar(255) NOT NULL COMMENT 'Descriptive name',
  `restrictions` longtext COMMENT 'JSON-encoded restrictions',
  PRIMARY KEY  (`restrictionid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Restrictions for judgehosts';

-- Add the restriction column to the judgehost table
ALTER TABLE `judgehost`
  ADD COLUMN `restrictionid` int(4) unsigned DEFAULT NULL COMMENT 'Optional set of restrictions for this judgehost',
  ADD KEY `restrictionid` (`restrictionid`),
  ADD CONSTRAINT `restriction_ibfk_1` FOREIGN KEY (`restrictionid`) REFERENCES `judgehost_restriction` (`restrictionid`) ON DELETE SET NULL;

-- Create a table for rejudging groups
CREATE TABLE `rejudging` (
  `rejudgingid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
  `userid_start` int(4) unsigned DEFAULT NULL COMMENT 'User ID of user who started the rejudge',
  `userid_finish` int(4) unsigned DEFAULT NULL COMMENT 'User ID of user who accepted or canceled the rejudge',
  `starttime` decimal(32,9) unsigned NOT NULL COMMENT 'Time rejudging started',
  `endtime` decimal(32,9) unsigned DEFAULT NULL COMMENT 'Time rejudging ended, null = still busy',
  `reason` varchar(255) NOT NULL COMMENT 'Reason to start this rejudge',
  `valid` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT 'Rejudging is marked as invalid if canceled',
  PRIMARY KEY  (`rejudgingid`),
  KEY `userid_start` (`userid_start`),
  KEY `userid_finish` (`userid_finish`),
  CONSTRAINT `rejudging_ibfk_1` FOREIGN KEY (`userid_start`) REFERENCES `user` (`userid`) ON DELETE SET NULL,
  CONSTRAINT `rejudging_ibfk_2` FOREIGN KEY (`userid_finish`) REFERENCES `user` (`userid`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Rejudge group';

ALTER TABLE `judging`
  ADD COLUMN `rejudgingid` int(4) unsigned DEFAULT NULL COMMENT 'Rejudging ID (if rejudge)',
  ADD COLUMN `prevjudgingid` int(4) unsigned DEFAULT NULL COMMENT 'Previous valid judging (if rejudge)',
  ADD KEY `rejudgingid` (`rejudgingid`),
  ADD KEY `prevjudgingid` (`prevjudgingid`),
  ADD CONSTRAINT `judging_ibfk_4` FOREIGN KEY (`rejudgingid`) REFERENCES `rejudging` (`rejudgingid`) ON DELETE SET NULL,
  ADD CONSTRAINT `judging_ibfk_5` FOREIGN KEY (`prevjudgingid`) REFERENCES `judging` (`judgingid`) ON DELETE SET NULL;

ALTER TABLE `submission`
  ADD `rejudgingid` int(4) unsigned DEFAULT NULL COMMENT 'Rejudging ID (if rejudge)',
  ADD KEY `rejudgingid` (`rejudgingid`),
  ADD CONSTRAINT `submission_ibfk_7` FOREIGN KEY (`rejudgingid`) REFERENCES `rejudging` (`rejudgingid`) ON DELETE SET NULL;

ALTER TABLE `problem`
  ADD COLUMN `memlimit` int(4) unsigned DEFAULT NULL COMMENT 'Maximum memory available (in kB) for this problem' AFTER `timelimit`,
  ADD COLUMN `outputlimit` int(4) unsigned DEFAULT NULL COMMENT 'Maximum output size (in kB) for this problem' AFTER `memlimit`,
  ADD COLUMN `special_compare_args` varchar(255) DEFAULT NULL COMMENT 'Optional arguments to special_compare script' AFTER `special_compare`;

ALTER TABLE `team`
  MODIFY COLUMN `externalid` varchar(255) DEFAULT NULL COMMENT 'Team ID in an external system',
  ADD COLUMN `penalty` int(4) NOT NULL default '0' COMMENT 'Additional penalty time in minutes' AFTER `hostname`,
  DROP INDEX `externalid`,
  ADD UNIQUE KEY `externalid` (`externalid`);

ALTER TABLE `testcase`
  ADD COLUMN `image` longblob COMMENT 'A graphical representation of this testcase' AFTER `description`,
  ADD COLUMN `image_thumb` longblob COMMENT 'Aumatically created thumbnail of the image' AFTER `image`,
  ADD COLUMN `image_type` varchar(4) DEFAULT NULL COMMENT 'File type of the image and thumbnail' AFTER `image_thumb`;

-- Add support for points per problem
ALTER TABLE `contestproblem`
  ADD COLUMN `points` int(4) unsigned NOT NULL DEFAULT '1' COMMENT 'Number of points earned by solving this problem' AFTER `shortname`;

ALTER TABLE `rankcache_jury`
  CHANGE COLUMN `correct` `points` int(4) unsigned NOT NULL DEFAULT '0' COMMENT 'Total correctness points';
ALTER TABLE `rankcache_public`
  CHANGE COLUMN `correct` `points` int(4) unsigned NOT NULL DEFAULT '0' COMMENT 'Total correctness points';

-- Increase some varchar field lengths
ALTER TABLE `judging`
  MODIFY COLUMN `jury_member` varchar(25) DEFAULT NULL COMMENT 'Name of jury member who verified this';
ALTER TABLE `role`
  MODIFY COLUMN `role` varchar(25) NOT NULL COMMENT 'Role name';

--
-- Transfer data from old to new structure
--

-- Copy data from old tables to new tables
INSERT INTO `contestproblem` (`cid`, `probid`, `shortname`, `allow_submit`, `allow_judge`, `color`)
  SELECT `cid`, `probid`, `shortname`, `allow_submit`, `allow_judge`, `color` FROM `problem`;

--
-- Add/remove sample/initial contents
--

UPDATE `configuration` SET `name` = 'script_timelimit', `description` = 'Maximum seconds available for compile/compare scripts. This is a safeguard against malicious code and buggy scripts, so a reasonable but large amount should do.' WHERE `name` = 'compile_time';
UPDATE `configuration` SET `name` = 'script_memory_limit', `description` = 'Maximum memory usage (in kB) by compile/compare scripts. This is a safeguard against malicious code and buggy script, so a reasonable but large amount should do.' WHERE `name` = 'compile_memory';
UPDATE `configuration` SET `name` = 'script_filesize_limit', `description` = 'Maximum filesize (in kB) compile/compare scripts may write. Submission will fail with compiler-error when trying to write more, so this should be greater than any *intermediate* result written by compilers.' WHERE `name` = 'compile_filesize';

UPDATE `configuration` SET `description` = 'Maximum memory usage (in kB) by submissions. This includes the shell which starts the compiled solution and also any interpreter like the Java VM, which takes away approx. 300MB! Can be overridden per problem.' WHERE `name` = 'memory_limit';

UPDATE `configuration` SET `description` = 'Show country flags and affiliations names on the scoreboard?' WHERE `name` = 'show_affiliations';

UPDATE `configuration` SET `name` = 'output_limit', `description` = 'Maximum output (in kB) submissions may generate. Any excessive output is truncated, so this should be greater than the maximum testdata output.' WHERE `name` = 'filesize_limit';

INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES
('judgehost_warning', '30', 'int', 'Time in seconds after a judgehost last checked in before showing its status as "warning".'),
('judgehost_critical', '120', 'int', 'Time in seconds after a judgehost last checked in before showing its status as "critical".'),
('thumbnail_size', '128', 'int', 'Maximum width/height of a thumbnail for uploaded testcase images.');

UPDATE `contest` SET `shortname` = UPPER(SUBSTR(REPLACE(`name`, ' ', ''), 1, 10)), `public` = 1;

-- Fix truncated string due to too short field length:
UPDATE `role` SET `role` = 'full_event_reader' WHERE `role` = 'full_event_read';

-- Update compare scripts to support new Kattis 42/43 exitcode format:
source mysql_db_files_defaultdata.sql
source mysql_db_files_examples.sql

-- Add UNIQUE keys
ALTER TABLE `contest`
  ADD UNIQUE KEY `shortname` (`shortname`);

ALTER TABLE `contestproblem`
  ADD UNIQUE KEY `shortname` (`cid`,`shortname`);

--
-- Finally remove obsolete structures after moving data
--

-- Remove fields from problem table that are now present in contestproblem
ALTER TABLE `problem`
  DROP FOREIGN KEY `problem_ibfk_1`,
  DROP INDEX `shortname`,
  DROP INDEX `cid`,
  DROP COLUMN `cid`,
  DROP COLUMN `shortname`,
  DROP COLUMN `allow_submit`,
  DROP COLUMN `allow_judge`,
  DROP COLUMN `color`;
