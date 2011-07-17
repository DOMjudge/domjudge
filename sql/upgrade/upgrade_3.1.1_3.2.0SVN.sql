-- This script upgrades table structure, data, and privileges
-- from/to the exact version numbers specified in the filename.

--
-- First execute a check whether this upgrade should apply. The check
-- below should fail if this upgrade has already been applied, but
-- keep everything unchanged if not.
--

-- @UPGRADE-CHECK@
ALTER TABLE `contest` ADD  COLUMN `activatetime_string` varchar(20) NOT NULL;
ALTER TABLE `contest` DROP COLUMN `activatetime_string`;

--
-- Create additional structures
--

ALTER TABLE `clarification`
  ADD COLUMN  `jury_member` varchar(15) default NULL COMMENT 'Name of jury member who answered this' AFTER `recipient`;

ALTER TABLE `contest`
  ADD COLUMN `activatetime_string` varchar(20) NOT NULL COMMENT 'Time contest becomes visible in team/public views' AFTER `unfreezetime`,
  ADD COLUMN `freezetime_string` varchar(20) default NULL COMMENT 'Time scoreboard is frozen' AFTER `activatetime_string`,
  ADD COLUMN `endtime_string` varchar(20) NOT NULL COMMENT 'Time after which no more submissions are accepted' AFTER `freezetime_string`,
  ADD COLUMN `unfreezetime_string` varchar(20) default NULL COMMENT 'Unfreeze a frozen scoreboard at this time' AFTER `endtime_string`;

ALTER TABLE `judging`
  CHANGE COLUMN `verifier` `jury_member` varchar(15) default NULL COMMENT 'Name of jury member who verified this';

ALTER TABLE `language`
  CHANGE COLUMN `langid` `langid` varchar(8) NOT NULL COMMENT 'Unique ID (string), used for source file extension';

--
-- Add/remove privileges
--

REVOKE SELECT (extension) ON language FROM `domjudge_team`, `domjudge_plugin`;

FLUSH PRIVILEGES;

--
-- Transfer data from old to new structure
--

UPDATE `contest` SET
  `activatetime_string` = `activatetime`,
  `freezetime_string`   = `freezetime`,
  `endtime_string`      = `endtime`,
  `unfreezetime_string` = `unfreezetime`;

--
-- Add/remove sample/initial contents
--

-- Disable language 'Bash' for 'POSIX shell' replacement, but do not
-- remove as there may be dependent data. Copy POSIX shell from Bash data.
UPDATE `language` SET `extension` = 'bash' WHERE `langid` = 'bash';

INSERT INTO `language` (`langid`, `name`, `extension`, `allow_submit`, `allow_judge`, `time_factor`)
  SELECT 'sh', 'POSIX shell', 'sh', `allow_submit`, `allow_judge`, `time_factor`
  FROM `language` WHERE `langid` = 'bash';

UPDATE `language` SET `allow_submit` = 0, `allow_judge` = 0 WHERE `langid` = 'bash';

-- Change some langid's to default extension, prepare for dropping
-- extension column below.
UPDATE `language` SET `langid` = 'hs'  WHERE `langid` = 'haskell';
UPDATE `language` SET `langid` = 'pas' WHERE `langid` = 'pascal';
UPDATE `language` SET `langid` = 'pl'  WHERE `langid` = 'perl';
UPDATE `language` SET `langid` = 'py'  WHERE `langid` = 'python';

UPDATE `problem` SET `special_compare` = 'float' WHERE `special_compare` = 'program.sh';

INSERT INTO `problem` (`probid`, `cid`, `name`, `allow_submit`, `allow_judge`, `timelimit`, `special_run`, `special_compare`, `color`) VALUES ('boolfind', 2, 'Boolean switch search', 1, 1, 5, 'boolfind', 'boolfind', 'limegreen');

INSERT INTO `testcase` (`md5sum_input`, `md5sum_output`, `input`, `output`, `probid`, `rank`, `description`) VALUES ('90864a8759427d63b40f1f5f75e32308', '6267776644f5bd2bf0edccf5a210e087', 0x310a350a310a310a300a310a300a, 0x4f555450555420310a, 'boolfind', 1, NULL);

--
-- Finally remove obsolete structures after moving data
--

ALTER TABLE `language`
  DROP KEY `extension`,
  DROP COLUMN `extension`;
