-- This script upgrades table structure, data, and privileges
-- from the default DOMjudge database to the online judge version.

--
-- First execute a check whether this upgrade should apply. The check
-- below should fail if this upgrade has already been applied, but
-- keep everything unchanged if not.
--

-- @UPGRADE-CHECK@
ALTER TABLE `testcase` ADD  COLUMN `sample` tinyint(1) default NULL;
ALTER TABLE `testcase` DROP COLUMN `sample`;

--
-- Create additional structures
--

ALTER TABLE `testcase`
  ADD COLUMN `sample` tinyint(1) unsigned NOT NULL default '0' COMMENT 'Sample testcases can be shared with teams.' AFTER `description`;

--
-- Transfer data from old to new structure
--

--
-- Add/remove sample/initial contents
--

INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('time_format', 'Y-m-d H:i', 'string', 'The format used to print times. For formatting options see the PHP \'date\' function.');

--
-- Finally remove obsolete structures after moving data
--

