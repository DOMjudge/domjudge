-- This script upgrades table structure, data, and privileges
-- from/to the exact version numbers specified in the filename.

--
-- First execute a check whether this upgrade should apply. The check
-- below should fail if this upgrade has already been applied, but
-- keep everything unchanged if not.
--

-- @UPGRADE-CHECK@
ALTER TABLE `team` ADD  COLUMN `judging_last_started` datetime default NULL;
ALTER TABLE `team` DROP COLUMN `judging_last_started`;

--
-- Create additional structures
--

ALTER TABLE `team`
  ADD COLUMN `judging_last_started` datetime default NULL COMMENT 'Start time of last judging for priorization' AFTER `comments`;

--
-- Add/remove privileges
--

FLUSH PRIVILEGES;

--
-- Transfer data from old to new structure
--

--
-- Add/remove sample/initial contents
--

--
-- Finally remove obsolete structures after moving data
--
