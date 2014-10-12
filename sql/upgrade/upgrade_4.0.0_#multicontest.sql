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
CREATE TABLE `gewis_contestproblem` (`dummy` INT(4) UNSIGNED);
DROP TABLE `gewis_contestproblem`;

--
-- Create additional structures
--

-- Make cid of problem table NULLable. We could actually remove it but this
-- could be problematic if later upstream decides to
ALTER TABLE `problem`
  CHANGE COLUMN `cid` `cid` INT(4) UNSIGNED DEFAULT NULL COMMENT 'Contest ID (not used anymore, see table `gewis_contestproblem`)';

CREATE TABLE `gewis_contestproblem` (
  `cid` INT(4) UNSIGNED NOT NULL COMMENT 'Contest ID',
  `probid` INT(4) UNSIGNED NOT NULL COMMENT 'Problem ID',
  KEY `cid` (`cid`),
  KEY `probid` (`probid`),
  CONSTRAINT `contestproblem_pk` PRIMARY KEY (`probid`, `cid`),
  CONSTRAINT `contestproblem_ibfk_1` FOREIGN KEY (`cid`) REFERENCES `contest` (`cid`) ON DELETE CASCADE,
  CONSTRAINT `contestproblem_ibfk_2` FOREIGN KEY (`probid`) REFERENCES `problem` (`probid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Many-to-Many mapping of contests and problems';

--
-- Transfer data from old to new structure
--

-- Copy data to new table
INSERT INTO `gewis_contestproblem` (`cid`, `probid`)
  SELECT `cid`, `probid` FROM `problem`;

-- Remove data from old column
UPDATE `problem` SET `cid` = NULL;

--
-- Add/remove sample/initial contents
--

--
-- Finally remove obsolete structures after moving data
--
