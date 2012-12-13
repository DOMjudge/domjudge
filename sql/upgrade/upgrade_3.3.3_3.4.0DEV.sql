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

ALTER TABLE `balloon`
  ADD KEY `submitid` (`submitid`),
  ADD FOREIGN KEY (`submitid`) REFERENCES `submission` (`submitid`) ON DELETE CASCADE;

--
-- Transfer data from old to new structure
--

--
-- Add/remove sample/initial contents
--

INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('show_balloons_postfreeze', '0', 'bool', 'Give out balloon notifications after the scoreboard has been frozen?');
INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('enable_printing', '0', 'bool', 'Enable teams and jury to send source code to a printer via the DOMjudge web interface.');
INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('team_select', '1', 'bool', 'Enable selection of favourite teams in the public scoreboard?');
INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('time_format', '"H:i"', 'string', 'The format used to print times. For formatting options see the PHP \'date\' function.');

UPDATE `configuration` SET `description` = 'Lazy evaluation of results? If enabled, stops judging as soon as a highest priority result is found, otherwise always all testcases will be judged.' WHERE `name` = 'lazy_eval_results';

--
-- Finally remove obsolete structures after moving data
--

