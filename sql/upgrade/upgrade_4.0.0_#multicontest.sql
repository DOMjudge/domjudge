-- This script upgrades table structure, data, and privileges
-- from 4.0.0 to include multicontest support.

-- It needs to be run from the sql/ directory (not sql/upgrade/).

-- The # in the filename is to make sure it will be run before any 4.0.0->4+
-- SQL upgrade files.

-- Note that we prefix everything with `gewis` when appropiate to make sure
-- there is no overlap with upstream.

--
-- First execute a check whether this upgrade should apply. The check
-- below should fail if this upgrade has already been applied, but
-- keep everything unchanged if not.
--

-- @UPGRADE-CHECK@
CREATE TABLE `gewis_contestproblem` (`dummy` int(4) UNSIGNED);
DROP TABLE `gewis_contestproblem`;

--
-- Create additional structures
--

-- Make cid of problem table NULLable. We could actually remove it but this
-- could be problematic if later upstream decides to
ALTER TABLE `problem`
  CHANGE COLUMN `cid` `cid` int(4) UNSIGNED DEFAULT NULL COMMENT 'Contest ID (not used anymore, see table `gewis_contestproblem`)';

-- Add a column to keep track of whether balloons will be processed for this contest
ALTER TABLE `contest`
  ADD COLUMN `deactivatetime` decimal(32,9) UNSIGNED NOT NULL COMMENT 'Time contest becomes invisible in team/public views' AFTER `unfreezetime`,
  ADD COLUMN `deactivatetime_string` varchar(20) NOT NULL COMMENT 'Authoritative absolute or relative string representation of deactivatetime' AFTER `unfreezetime_string`,
  ADD COLUMN `process_balloons` tinyint(1) UNSIGNED DEFAULT 1 COMMENT 'Will balloons be processed for this contest?';


-- Create a table linking contests and problems
CREATE TABLE `gewis_contestproblem` (
  `cid` int(4) UNSIGNED NOT NULL COMMENT 'Contest ID',
  `probid` int(4) UNSIGNED NOT NULL COMMENT 'Problem ID',
  KEY `cid` (`cid`),
  KEY `probid` (`probid`),
  CONSTRAINT `contestproblem_pk` PRIMARY KEY (`probid`, `cid`),
  CONSTRAINT `contestproblem_ibfk_1` FOREIGN KEY (`cid`) REFERENCES `contest` (`cid`) ON DELETE CASCADE,
  CONSTRAINT `contestproblem_ibfk_2` FOREIGN KEY (`probid`) REFERENCES `problem` (`probid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Many-to-Many mapping of contests and problems';

-- Create a table linking contests and teams
CREATE TABLE `gewis_contestteam` (
  `cid` int(4) UNSIGNED NOT NULL COMMENT 'Contest ID',
  `teamid` int(4) UNSIGNED NOT NULL COMMENT 'Team ID',
  KEY `cid` (`cid`),
  KEY `teamid` (`teamid`),
  CONSTRAINT `contestteam_pk` PRIMARY KEY (`teamid`, `cid`),
  CONSTRAINT `contestteam_ibfk_1` FOREIGN KEY (`cid`) REFERENCES `contest` (`cid`) ON DELETE CASCADE,
  CONSTRAINT `contestteam_ibfk_2` FOREIGN KEY (`teamid`) REFERENCES `team` (`teamid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Many-to-Many mapping of contests and teams';

--
-- Transfer data from old to new structure
--

-- Copy data to new tables
INSERT INTO `gewis_contestproblem` (`cid`, `probid`)
  SELECT `cid`, `probid` FROM `problem`;

INSERT INTO `gewis_contestteam` (`cid`, `teamid`)
  SELECT `cid`, `teamid` FROM `contest` INNER JOIN `team`;

-- Remove data from old column
UPDATE `problem` SET `cid` = NULL;

--
-- Add/remove sample/initial contents
--

--
-- Finally remove obsolete structures after moving data
--
