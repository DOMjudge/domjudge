-- This script upgrades table structure, data, and privileges
-- from/to the exact version numbers specified in the filename.

--
-- First execute a check whether this upgrade should apply. The check
-- below should fail if this upgrade has already been applied, but
-- keep everything unchanged if not.
--

-- @UPGRADE-CHECK@
ALTER TABLE `team` DROP KEY `name`;
ALTER TABLE `team` ADD UNIQUE KEY `name` (`name`(190));

-- Before any upgrades, update from utf8 (3-byte character) to utf8mb4
-- full UTF-8 unicode support. This requires MySQL/MariaDB >= 5.5.3.
-- Comment out to disable this change.
source upgrade/convert_to_utf8mb4_5.0.sql

--
-- Create additional structures
--

-- Set allow_submit default to 1
ALTER TABLE `contestproblem`
  MODIFY COLUMN `allow_submit` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT 'Are submissions accepted for this problem?';

-- Support longer contest time strings to include microseconds
ALTER TABLE `contest`
  MODIFY COLUMN `activatetime_string` varchar(64) NOT NULL COMMENT 'Authoritative absolute or relative string representation of activatetime',
  MODIFY COLUMN `starttime_string` varchar(64) NOT NULL COMMENT 'Authoritative absolute (only!) string representation of starttime',
  MODIFY COLUMN `freezetime_string` varchar(64) DEFAULT NULL COMMENT 'Authoritative absolute or relative string representation of freezetime',
  MODIFY COLUMN `endtime_string` varchar(64) NOT NULL COMMENT 'Authoritative absolute or relative string representation of endtime',
  MODIFY COLUMN `unfreezetime_string` varchar(64) DEFAULT NULL COMMENT 'Authoritative absolute or relative string representation of unfreezetrime',
  MODIFY COLUMN `deactivatetime_string` varchar(64) DEFAULT NULL COMMENT 'Authoritative absolute or relative string representation of deactivatetime';

-- Drop unique key on team name
ALTER TABLE `team` DROP KEY `name`;

--
-- Transfer data from old to new structure
--

--
-- Add/remove sample/initial contents
--

--
-- Finally remove obsolete structures after moving data
--

