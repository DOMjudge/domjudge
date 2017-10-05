-- This script upgrades table structure, data, and privileges
-- from/to the exact version numbers specified in the filename.

--
-- First execute a check whether this upgrade should apply. The check
-- below should fail if this upgrade has already been applied, but
-- keep everything unchanged if not.
--

-- @UPGRADE-CHECK@
ALTER TABLE `judging_run` ADD  COLUMN `endtime` decimal(32,9);
ALTER TABLE `judging_run` DROP COLUMN `endtime`;

--
-- Create additional structures
--

ALTER TABLE `contestteam`
  DROP PRIMARY KEY,
  ADD PRIMARY KEY (`cid`,`teamid`);

ALTER TABLE `judging_run`
  ADD COLUMN `endtime` decimal(32,9) unsigned NOT NULL COMMENT 'Time run judging ended' AFTER `runtime`;

ALTER TABLE `submission`
  ADD COLUMN `entry_point` varchar(255) DEFAULT NULL COMMENT 'Optional entry point. Can be used e.g. for java main class.' AFTER `expected_results`;

source convert_event_6.0.sql

--
-- Transfer data from old to new structure
--


--
-- Add/remove sample/initial contents
--


--
-- Finally remove obsolete structures after moving data
--

