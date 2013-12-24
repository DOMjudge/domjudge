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

ALTER TABLE `problem`
  ADD COLUMN `start` datetime default NULL COMMENT 'Start time when submissions are allowed' AFTER `timelimit`,
  ADD COLUMN `end` datetime default NULL COMMENT 'deadline for this problem' AFTER `start`;

ALTER TABLE `team`
  CHANGE COLUMN `teampage_first_visited` `teampage_visited` datetime default NULL COMMENT 'Time of last teampage view',
  MODIFY COLUMN `hostname` varchar(255) default NULL COMMENT 'Teampage last visited from this address',
  ADD COLUMN `maillog` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'Whether the team gets mails for each submission' AFTER `hostname`;

ALTER TABLE `testcase`
  ADD COLUMN `sample` tinyint(1) unsigned NOT NULL default '0' COMMENT 'Sample testcases can be shared with teams.' AFTER `description`;

ALTER TABLE `clarification`
  MODIFY COLUMN `probid` varchar(50) default NULL COMMENT 'Problem associated to this clarification';

ALTER TABLE `event`
  MODIFY COLUMN `probid` varchar(50) default NULL COMMENT 'Problem ID';

ALTER TABLE `problem`
  MODIFY COLUMN `probid` varchar(50) NOT NULL COMMENT 'Unique ID (string)';

ALTER TABLE `scorecache_jury`
  MODIFY COLUMN `probid` varchar(50) NOT NULL COMMENT 'Problem ID';

ALTER TABLE `scorecache_public`
  MODIFY COLUMN `probid` varchar(50) NOT NULL COMMENT 'Problem ID';

ALTER TABLE `submission`
  MODIFY COLUMN `probid` varchar(50) NOT NULL COMMENT 'Problem ID';

ALTER TABLE `testcase`
  MODIFY COLUMN `probid` varchar(50) NOT NULL COMMENT 'Corresponding problem ID';

--
-- Transfer data from old to new structure
--

--
-- Add/remove sample/initial contents
--

INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('separate_start_end', '0', 'bool', 'Enable separate start and end times per problem in a contest.');

UPDATE `configuration` SET `value` = '"%Y-%m-%d %H:%M"' WHERE `name` = 'time_format';

--
-- Finally remove obsolete structures after moving data
--

