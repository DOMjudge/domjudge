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

-- Add ON DELETE actions to foreign keys, first delete old ones, since
-- a change syntax is not available. Also add required keys and DEFAULT NULL.
ALTER TABLE `clarification`
  ADD KEY `respid` (`respid`),
  ADD KEY `probid` (`probid`),
  DROP FOREIGN KEY `clarification_ibfk_1`;
ALTER TABLE `clarification`
  ADD CONSTRAINT `clarification_ibfk_1` FOREIGN KEY (`cid`) REFERENCES `contest` (`cid`) ON DELETE CASCADE,
  ADD CONSTRAINT `clarification_ibfk_2` FOREIGN KEY (`respid`) REFERENCES `clarification` (`clarid`) ON DELETE SET NULL,
  ADD CONSTRAINT `clarification_ibfk_3` FOREIGN KEY (`probid`) REFERENCES `problem` (`probid`) ON DELETE SET NULL;

ALTER TABLE `judging`
  CHANGE COLUMN `judgehost` `judgehost` varchar(50) default NULL COMMENT 'Judgehost that performed the judging',
  DROP FOREIGN KEY `judging_ibfk_1`,
  DROP FOREIGN KEY `judging_ibfk_2`,
  DROP FOREIGN KEY `judging_ibfk_3`;
ALTER TABLE `judging`
  ADD CONSTRAINT `judging_ibfk_1` FOREIGN KEY (`cid`) REFERENCES `contest` (`cid`) ON DELETE CASCADE,
  ADD CONSTRAINT `judging_ibfk_2` FOREIGN KEY (`submitid`) REFERENCES `submission` (`submitid`) ON DELETE CASCADE,
  ADD CONSTRAINT `judging_ibfk_3` FOREIGN KEY (`judgehost`) REFERENCES `judgehost` (`hostname`) ON DELETE SET NULL;

ALTER TABLE `judging_run`
  ADD KEY `judgingid` (`judgingid`),
  ADD KEY `testcaseid_2` (`testcaseid`);
ALTER TABLE `judging_run`
  ADD CONSTRAINT `judging_run_ibfk_1` FOREIGN KEY (`testcaseid`) REFERENCES `testcase` (`testcaseid`),
  ADD CONSTRAINT `judging_run_ibfk_2` FOREIGN KEY (`judgingid`) REFERENCES `judging` (`judgingid`) ON DELETE CASCADE;

ALTER TABLE `problem`
  DROP FOREIGN KEY `problem_ibfk_1`;
ALTER TABLE `problem`
  ADD CONSTRAINT `problem_ibfk_1` FOREIGN KEY (`cid`) REFERENCES `contest` (`cid`) ON DELETE CASCADE;

ALTER TABLE `submission`
  DROP FOREIGN KEY `submission_ibfk_1`,
  DROP FOREIGN KEY `submission_ibfk_2`,
  DROP FOREIGN KEY `submission_ibfk_3`,
  DROP FOREIGN KEY `submission_ibfk_4`,
  DROP FOREIGN KEY `submission_ibfk_5`;
ALTER TABLE `submission`
  ADD CONSTRAINT `submission_ibfk_1` FOREIGN KEY (`cid`) REFERENCES `contest` (`cid`) ON DELETE CASCADE,
  ADD CONSTRAINT `submission_ibfk_2` FOREIGN KEY (`teamid`) REFERENCES `team` (`login`) ON DELETE CASCADE,
  ADD CONSTRAINT `submission_ibfk_3` FOREIGN KEY (`probid`) REFERENCES `problem` (`probid`) ON DELETE CASCADE,
  ADD CONSTRAINT `submission_ibfk_4` FOREIGN KEY (`langid`) REFERENCES `language` (`langid`) ON DELETE CASCADE,
  ADD CONSTRAINT `submission_ibfk_5` FOREIGN KEY (`judgehost`) REFERENCES `judgehost` (`hostname`) ON DELETE SET NULL;

ALTER TABLE `team`
  DROP FOREIGN KEY `team_ibfk_1`,
  DROP FOREIGN KEY `team_ibfk_2`;
ALTER TABLE `team`
  ADD CONSTRAINT `team_ibfk_1` FOREIGN KEY (`categoryid`) REFERENCES `team_category` (`categoryid`) ON DELETE CASCADE,
  ADD CONSTRAINT `team_ibfk_2` FOREIGN KEY (`affilid`) REFERENCES `team_affiliation` (`affilid`) ON DELETE SET NULL;

ALTER TABLE `team_unread`
  ADD KEY `mesgid` (`mesgid`),
  DROP FOREIGN KEY `team_unread_ibfk_1`;
ALTER TABLE `team_unread`
  ADD CONSTRAINT `team_unread_ibfk_1` FOREIGN KEY (`teamid`) REFERENCES `team` (`login`) ON DELETE CASCADE,
  ADD CONSTRAINT `team_unread_ibfk_2` FOREIGN KEY (`mesgid`) REFERENCES `clarification` (`clarid`) ON DELETE CASCADE;

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
-- extension column below. We must first disable the index on extension
-- to allow temporary duplicates, then copy the language row, then
-- change dependent submissions and events, and finally delete the
-- old rows.
ALTER TABLE `language`
  DROP KEY `extension`;

INSERT INTO `language` (`langid`, `name`, `extension`, `allow_submit`, `allow_judge`, `time_factor`)
  SELECT 'csharp', `name`, `extension`, `allow_submit`, `allow_judge`, `time_factor`
  FROM `language` WHERE `langid` = 'cs';
INSERT INTO `language` (`langid`, `name`, `extension`, `allow_submit`, `allow_judge`, `time_factor`)
  SELECT 'hs', `name`, `extension`, `allow_submit`, `allow_judge`, `time_factor`
  FROM `language` WHERE `langid` = 'haskell';
INSERT INTO `language` (`langid`, `name`, `extension`, `allow_submit`, `allow_judge`, `time_factor`)
  SELECT 'pas', `name`, `extension`, `allow_submit`, `allow_judge`, `time_factor`
  FROM `language` WHERE `langid` = 'pascal';
INSERT INTO `language` (`langid`, `name`, `extension`, `allow_submit`, `allow_judge`, `time_factor`)
  SELECT 'pl', `name`, `extension`, `allow_submit`, `allow_judge`, `time_factor`
  FROM `language` WHERE `langid` = 'perl';
INSERT INTO `language` (`langid`, `name`, `extension`, `allow_submit`, `allow_judge`, `time_factor`)
  SELECT 'py', `name`, `extension`, `allow_submit`, `allow_judge`, `time_factor`
  FROM `language` WHERE `langid` = 'python';

UPDATE `submission` SET `langid` = 'csharp' WHERE `langid` = 'cs';
UPDATE `submission` SET `langid` = 'hs'     WHERE `langid` = 'haskell';
UPDATE `submission` SET `langid` = 'pas'    WHERE `langid` = 'pascal';
UPDATE `submission` SET `langid` = 'pl'     WHERE `langid` = 'perl';
UPDATE `submission` SET `langid` = 'py'     WHERE `langid` = 'python';

UPDATE `event`      SET `langid` = 'csharp' WHERE `langid` = 'cs';
UPDATE `event`      SET `langid` = 'hs'     WHERE `langid` = 'haskell';
UPDATE `event`      SET `langid` = 'pas'    WHERE `langid` = 'pascal';
UPDATE `event`      SET `langid` = 'pl'     WHERE `langid` = 'perl';
UPDATE `event`      SET `langid` = 'py'     WHERE `langid` = 'python';

DELETE FROM `language` WHERE `langid` IN ('cs', 'haskell', 'pascal', 'perl', 'python');

UPDATE `problem` SET `special_compare` = 'float' WHERE `special_compare` = 'program.sh';

INSERT INTO `problem` (`probid`, `cid`, `name`, `allow_submit`, `allow_judge`, `timelimit`, `special_run`, `special_compare`, `color`) VALUES ('boolfind', 2, 'Boolean switch search', 1, 1, 5, 'boolfind', 'boolfind', 'limegreen');

INSERT INTO `testcase` (`md5sum_input`, `md5sum_output`, `input`, `output`, `probid`, `rank`, `description`) VALUES ('90864a8759427d63b40f1f5f75e32308', '6267776644f5bd2bf0edccf5a210e087', 0x310a350a310a310a300a310a300a, 0x4f555450555420310a, 'boolfind', 1, NULL);

--
-- Finally remove obsolete structures after moving data
--

ALTER TABLE `language`
  DROP COLUMN `extension`;
