-- This script upgrades table structure, data, and privileges
-- from/to the exact version numbers specified in the filename.

--
-- First execute a check whether this upgrade should apply. The check
-- below should fail if this upgrade has already been applied, but
-- keep everything unchanged if not.
--

-- @UPGRADE-CHECK@
ALTER TABLE `problem` ADD  COLUMN `problemtext` longblob;
ALTER TABLE `problem` DROP COLUMN `problemtext`;

--
-- Create additional structures
--

ALTER TABLE `problem`
  ADD COLUMN `problemtext` longblob COMMENT 'Problem text in HTML/PDF/ASCII' AFTER `color`,
  ADD COLUMN `problemtext_type` varchar(4) DEFAULT NULL COMMENT 'File type of problem text' AFTER `problemtext`;

--
-- Transfer data from old to new structure
--

--
-- Add/remove sample/initial contents
--

INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('show_balloons_postfreeze', '0', 'bool', 'Give out balloon notifications after the scoreboard has been frozen?');
INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('enable_printing', '0', 'bool', 'Enable teams and jury to send source code to a printer via the DOMjudge web interface.');
INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('time_format', '"H:i"', 'string', 'The format used to print times. For formatting options see the PHP \'date\' function.');

UPDATE `configuration` SET `description` = 'Lazy evaluation of results? If enabled, stops judging as soon as a highest priority result is found, otherwise always all testcases will be judged.' WHERE `name` = 'lazy_eval_results';

INSERT INTO `language` (`langid`, `name`, `allow_submit`, `allow_judge`, `time_factor`) VALUES ('adb',    'Ada',     0, 1, 1);
INSERT INTO `language` (`langid`, `name`, `allow_submit`, `allow_judge`, `time_factor`) VALUES ('f95',    'Fortran', 0, 1, 1);
INSERT INTO `language` (`langid`, `name`, `allow_submit`, `allow_judge`, `time_factor`) VALUES ('scala',  'Scala',   0, 1, 1.5);
INSERT INTO `language` (`langid`, `name`, `allow_submit`, `allow_judge`, `time_factor`) VALUES ('lua',    'Lua',     0, 1, 1);
INSERT INTO `language` (`langid`, `name`, `allow_submit`, `allow_judge`, `time_factor`) VALUES ('py3', 'Python 3', 0, 1, 1);

-- Rename language extension py to py2 (to discern from added py3):
INSERT INTO `language` (`langid`, `name`, `allow_submit`, `allow_judge`, `time_factor`)
  SELECT 'py2', 'Python 2', `allow_submit`, `allow_judge`, `time_factor`
  FROM `language` WHERE `langid` = 'py';

UPDATE `event`      SET `langid` = 'py2' WHERE `langid` = 'py';
UPDATE `submission` SET `langid` = 'py2' WHERE `langid` = 'py';

DELETE FROM `language` WHERE `langid` = 'py';

--
-- Finally remove obsolete structures after moving data
--

