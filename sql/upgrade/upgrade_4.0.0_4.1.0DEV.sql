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

-- Add a column to keep track of whether balloons will be processed for this contest
ALTER TABLE `contest`
  ADD COLUMN `shortname` varchar(255) NOT NULL COMMENT 'Short name for this contest' AFTER `contestname`,
  ADD COLUMN `deactivatetime` decimal(32,9) UNSIGNED NOT NULL COMMENT 'Time contest becomes invisible in team/public views' AFTER `unfreezetime`,
  ADD COLUMN `deactivatetime_string` varchar(20) NOT NULL COMMENT 'Authoritative absolute or relative string representation of deactivatetime' AFTER `unfreezetime_string`,
  ADD COLUMN `process_balloons` tinyint(1) UNSIGNED DEFAULT 1 COMMENT 'Will balloons be processed for this contest?',
  ADD COLUMN `public` tinyint(1) UNSIGNED DEFAULT 1 COMMENT 'Is this contest visible for public';

-- Create a table linking contests and problems
CREATE TABLE `contestproblem` (
  `cid` int(4) UNSIGNED NOT NULL COMMENT 'Contest ID',
  `probid` int(4) UNSIGNED NOT NULL COMMENT 'Problem ID',
  `shortname` varchar(255) NOT NULL COMMENT 'Unique ID (string)',
  `allow_submit` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'Are submissions accepted for this problem?',
  `allow_judge` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT 'Are submissions for this problem judged?',
  `color` varchar(25) DEFAULT NULL COMMENT 'Balloon colour to display on the scoreboard',
  KEY `cid` (`cid`),
  KEY `probid` (`probid`),
  UNIQUE KEY `shortname` (`shortname`,`cid`),
  PRIMARY KEY (`probid`, `cid`),
  CONSTRAINT `contestproblem_ibfk_1` FOREIGN KEY (`cid`) REFERENCES `contest` (`cid`) ON DELETE CASCADE,
  CONSTRAINT `contestproblem_ibfk_2` FOREIGN KEY (`probid`) REFERENCES `problem` (`probid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Many-to-Many mapping of contests and problems';

-- Create a table linking contests and teams
CREATE TABLE `contestteam` (
  `cid` int(4) UNSIGNED NOT NULL COMMENT 'Contest ID',
  `teamid` int(4) UNSIGNED NOT NULL COMMENT 'Team ID',
  KEY `cid` (`cid`),
  KEY `teamid` (`teamid`),
  PRIMARY KEY (`teamid`, `cid`),
  CONSTRAINT `contestteam_ibfk_1` FOREIGN KEY (`cid`) REFERENCES `contest` (`cid`) ON DELETE CASCADE,
  CONSTRAINT `contestteam_ibfk_2` FOREIGN KEY (`teamid`) REFERENCES `team` (`teamid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Many-to-Many mapping of contests and teams';

--
-- Transfer data from old to new structure
--

-- Copy data from old tables to new tables
INSERT INTO `contestproblem` (`cid`, `probid`, `shortname`, `allow_submit`, `allow_judge`, `color`)
  SELECT `cid`, `probid`, `shortname`, `allow_submit`, `allow_judge`, `color` FROM `problem`;

INSERT INTO `contestteam` (`cid`, `teamid`)
  SELECT `cid`, `teamid` FROM `contest` INNER JOIN `team`;

--
-- Add/remove sample/initial contents
--

UPDATE `configuration` SET `name` = 'script_timelimit', `description` = 'Maximum seconds available for compile/compare scripts. This is a safeguard against malicious code and buggy scripts, so a reasonable but large amount should do.' WHERE `name` = 'compile_time';
UPDATE `configuration` SET `name` = 'script_memory_limit', `description` = 'Maximum memory usage (in kB) by compile/compare scripts. This is a safeguard against malicious code and buggy script, so a reasonable but large amount should do.' WHERE `name` = 'compile_memory';
UPDATE `configuration` SET `name` = 'script_filesize', `description` = 'Maximum filesize (in kB) compile/compare scripts may write. Submission will fail with compiler-error when trying to write more, so this should be greater than any *intermediate* result written by compilers.' WHERE `name` = 'compile_filesize';
UPDATE `contest` SET `shortname` = UPPER(SUBSTR(REPLACE(`contestname`, ' ', ''), 1, 6));

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
