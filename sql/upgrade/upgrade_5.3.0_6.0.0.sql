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

ALTER TABLE `configuration`
  ADD COLUMN `public` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'Is this variable publicly visible?' AFTER `type`,
  ADD KEY `public` (`public`);

ALTER TABLE contest
  ADD COLUMN `starttime_enabled` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT 'If disabled, starttime is not used, e.g. to delay contest start' AFTER `enabled`,
  ADD COLUMN `finalizetime` decimal(32,9) DEFAULT NULL COMMENT 'Time when contest was finalized, null if not yet' AFTER `deactivatetime_string`,
  ADD COLUMN `finalizecomment` text COMMENT 'Comments by the finalizer' AFTER `finalizetime`,
  ADD COLUMN `b` smallint(3) unsigned NOT NULL default '0' COMMENT 'Number of extra bronze medals' AFTER `finalizecomment`;

ALTER TABLE `contestteam`
  DROP PRIMARY KEY,
  ADD PRIMARY KEY (`cid`,`teamid`);

ALTER TABLE `contestproblem`
  MODIFY COLUMN `shortname` varchar(255) NOT NULL COMMENT 'Unique problem ID within contest, used to sort problems in the scoreboard and typically a single letter';

ALTER TABLE `clarification`
  ADD COLUMN `externalid` varchar(255) DEFAULT NULL COMMENT 'Clarification ID in an external system, should be unique inside a single contest' AFTER `clarid`,
  ADD UNIQUE KEY `externalid` (`cid`,`externalid`(190));

ALTER TABLE `language`
  ADD COLUMN `externalid` varchar(255) DEFAULT NULL COMMENT 'Language ID to expose in the REST API' AFTER `langid`,
  ADD COLUMN `require_entry_point` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Whether submissions require a code entry point to be specified.' AFTER `extensions`,
  ADD COLUMN `entry_point_description` VARCHAR(255) NULL COMMENT 'The description used in the UI for the entry point field.' AFTER `require_entry_point`,
  ADD UNIQUE KEY `externalid` (`externalid`(190));

ALTER TABLE `problem`
  ADD COLUMN `externalid` varchar(255) DEFAULT NULL COMMENT 'Problem ID in an external system, should be unique inside a single contest' AFTER `probid`,
  ADD KEY `externalid` (`externalid`(190));

ALTER TABLE `team_affiliation`
  ADD COLUMN `externalid` varchar(255) DEFAULT NULL COMMENT 'Team affiliation ID in an external system' AFTER `affilid`,
  ADD UNIQUE KEY `externalid` (`externalid`(190));

ALTER TABLE `judging_run`
  ADD COLUMN `endtime` decimal(32,9) unsigned NOT NULL COMMENT 'Time run judging ended' AFTER `runtime`;

ALTER TABLE `submission`
  ADD COLUMN `externalid` varchar(255) DEFAULT NULL COMMENT 'Specifies ID of submission if imported from external CCS, e.g. Kattis' AFTER `expected_results`,
  ADD COLUMN `externalresult` varchar(32) DEFAULT NULL COMMENT 'Result string as returned from external CCS, e.g. Kattis' AFTER `externalid`,
  ADD COLUMN `entry_point` varchar(255) DEFAULT NULL COMMENT 'Optional entry point. Can be used e.g. for java main class.' AFTER `externalresult`,
  ADD UNIQUE KEY `externalid` (`cid`,`externalid`(190));


source upgrade/convert_event_6.0.sql

-- More consistent varchar() lengths, we only increase lengths:

-- Temporarily disable foreign key checks to enable length change:
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;

ALTER TABLE `judging` DROP FOREIGN KEY `judging_ibfk_3`;
ALTER TABLE `submission` DROP FOREIGN KEY `submission_ibfk_4`;
ALTER TABLE `submission` DROP FOREIGN KEY `submission_ibfk_5`;

ALTER TABLE `auditlog`
  MODIFY COLUMN `datatype` varchar(32) DEFAULT NULL COMMENT 'Reference to DB table associated to this entry',
  MODIFY COLUMN `dataid` varchar(64) DEFAULT NULL COMMENT 'Identifier in reference table',
  MODIFY COLUMN `action` varchar(64) DEFAULT NULL COMMENT 'Description of action performed';

ALTER TABLE `clarification`
  MODIFY COLUMN `jury_member` varchar(255) DEFAULT NULL COMMENT 'Name of jury member who answered this',
  MODIFY COLUMN `category` varchar(255) DEFAULT NULL COMMENT 'Category associated to this clarification; only set for non problem clars';

ALTER TABLE `configuration`
  MODIFY COLUMN `name` varchar(32) NOT NULL COMMENT 'Name of the configuration variable',
  MODIFY COLUMN `type` varchar(32) DEFAULT NULL COMMENT 'Type of the value (metatype for use in the webinterface)';

ALTER TABLE `contestproblem`
  MODIFY COLUMN `color` varchar(32) DEFAULT NULL COMMENT 'Balloon colour to display on the scoreboard';

ALTER TABLE `executable`
  MODIFY COLUMN `type` varchar(32) NOT NULL COMMENT 'Type of executable';

ALTER TABLE `judgehost`
  MODIFY COLUMN `hostname` varchar(64) NOT NULL COMMENT 'Resolvable hostname of judgehost';

ALTER TABLE `judging`
  MODIFY COLUMN `judgehost` varchar(64) NOT NULL COMMENT 'Judgehost that performed the judging',
  MODIFY COLUMN `result` varchar(32) DEFAULT NULL COMMENT 'Result string as defined in config.php',
  MODIFY COLUMN `jury_member` varchar(255) DEFAULT NULL COMMENT 'Name of jury member who verified this';

ALTER TABLE `language`
  MODIFY COLUMN `langid` varchar(32) NOT NULL COMMENT 'Unique ID (string)';

ALTER TABLE `judging_run`
  MODIFY COLUMN `runresult` varchar(32) DEFAULT NULL COMMENT 'Result of this run, NULL if not finished yet';

ALTER TABLE `role`
  MODIFY COLUMN `role` varchar(32) NOT NULL COMMENT 'Role name';

ALTER TABLE `submission`
  MODIFY COLUMN `langid` varchar(32) NOT NULL COMMENT 'Language ID',
  MODIFY COLUMN `judgehost` varchar(64) DEFAULT NULL COMMENT 'Current/last judgehost judging this submission';

ALTER TABLE `team`
  MODIFY COLUMN `room` varchar(255) DEFAULT NULL COMMENT 'Physical location of team';

ALTER TABLE `team_affiliation`
  MODIFY COLUMN `shortname` varchar(32) NOT NULL COMMENT 'Short descriptive name';

ALTER TABLE `team_category`
  MODIFY COLUMN `color` varchar(32) DEFAULT NULL COMMENT 'Background colour on the scoreboard';

ALTER TABLE `judging`
  ADD CONSTRAINT `judging_ibfk_3` FOREIGN KEY (`judgehost`) REFERENCES `judgehost` (`hostname`);

ALTER TABLE `submission`
  ADD CONSTRAINT `submission_ibfk_4` FOREIGN KEY (`langid`) REFERENCES `language` (`langid`) ON DELETE CASCADE,
  ADD CONSTRAINT `submission_ibfk_5` FOREIGN KEY (`judgehost`) REFERENCES `judgehost` (`hostname`) ON DELETE SET NULL;

/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;

ALTER TABLE `clarification`
  ADD COLUMN `queue` varchar(255) DEFAULT NULL COMMENT 'Queue associated to this clarification' AFTER `category`;

-- Add `removed_interval` table

CREATE TABLE `removed_interval` (
  `intervalid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
  `cid` int(4) unsigned NOT NULL COMMENT 'Contest ID',
  `starttime` decimal(32,9) unsigned NOT NULL COMMENT 'Initial time of removed interval',
  `endtime` decimal(32,9) unsigned NOT NULL COMMENT 'Final time of removed interval',
  `starttime_string` varchar(64) NOT NULL COMMENT 'Authoritative (absolute only) string representation of starttime',
  `endtime_string` varchar(64) NOT NULL COMMENT 'Authoritative (absolute only) string representation of endtime',
  PRIMARY KEY (`intervalid`),
  KEY `cid` (`cid`),
  CONSTRAINT `removed_interval_ibfk_1` FOREIGN KEY (`cid`) REFERENCES `contest` (`cid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Time intervals removed from the contest for scoring';


--
-- Transfer data from old to new structure
--


--
-- Add/remove sample/initial contents
--

INSERT INTO `configuration` (`name`, `value`, `type`, `description`, `public`) VALUES
('show_flags', '1', 'bool', 'Show country flags on the scoreboard?', 1),
('show_affiliation_logos', '0', 'bool', 'Show affiliation logos on the scoreboard?', 1),
('show_limits_on_team_page', '0', 'bool', 'Show time and memory limit on the team problems page', 1),
('show_relative_time', '0', 'bool', 'Print times of contest events relative to contest start (instead of absolute).', '1'),
('clar_answers', '["No comment","Read the problem statement carefully"]', 'array_val', 'List of predefined clarification answers', 0),
('clar_queues', '{}', 'array_keyval', 'List of clarification queues', 1),
('clar_default_problem_queue', '""', 'string', 'Category to assign to problem clarifications', 1);

UPDATE `configuration` SET `description` = 'Show affiliation names in the interfaces?' WHERE `name` = 'show_affiliations';

UPDATE `language` SET `externalid` = 'c'          WHERE `langid` = 'c';
UPDATE `language` SET `externalid` = 'cpp'        WHERE `langid` = 'cpp';
UPDATE `language` SET `externalid` = 'csharp'     WHERE `langid` = 'csharp';
UPDATE `language` SET `externalid` = 'haskell'    WHERE `langid` = 'hs';
UPDATE `language` SET `externalid` = 'java'       WHERE `langid` = 'java';
UPDATE `language` SET `externalid` = 'javascript' WHERE `langid` = 'js';
UPDATE `language` SET `externalid` = 'kotlin'     WHERE `langid` = 'kt';
UPDATE `language` SET `externalid` = 'pascal'     WHERE `langid` = 'pas';
UPDATE `language` SET `externalid` = 'prolog'     WHERE `langid` = 'plg';
UPDATE `language` SET `externalid` = 'python2'    WHERE `langid` = 'py2';
UPDATE `language` SET `externalid` = 'python3'    WHERE `langid` = 'py3';
UPDATE `language` SET `externalid` = 'scala'      WHERE `langid` = 'scala';

UPDATE `language` SET `extensions` = '["cpp","cc","cxx","c++"]' WHERE `langid` = 'cpp';

UPDATE `language` SET `require_entry_point` = 1, `entry_point_description` = 'Main class' WHERE `langid` = 'java' or `langid` = 'kt';
UPDATE `language` SET `require_entry_point` = 1, `entry_point_description` = 'Main file' WHERE `langid` = 'py2' or `langid` = 'py3';

UPDATE `configuration` SET `public` = '1' WHERE `name` IN (
  'clar_categories', 'sourcesize_limit', 'sourcefiles_limit',
  'score_in_seconds', 'show_flags', 'show_affiliations',
  'show_affiliation_logos', 'show_pending', 'show_teams_submissions',
  'show_compile', 'show_sample_output', 'show_balloons_postfreeze',
  'penalty_time', 'compile_penalty', 'enable_printing',
  'allow_registration', 'allow_openid_auth', 'openid_autocreate_team',
  'openid_provider');

--
-- Finally remove obsolete structures after moving data
--

