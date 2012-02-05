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
  MODIFY COLUMN `name` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL COMMENT 'Team name',
  ADD COLUMN `judging_last_started` datetime default NULL COMMENT 'Start time of last judging for priorization' AFTER `comments`,
  ADD COLUMN `penalty` int(4) NOT NULL default '0' COMMENT 'Additional penalty time in minutes' AFTER `hostname`,
  ADD COLUMN `enabled` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT 'Whether the team is visible and operational' AFTER `authtoken`;

ALTER TABLE `team_affiliation`
  MODIFY COLUMN `country` char(3) default NULL COMMENT 'ISO 3166-1 alpha-3 country code',

ALTER TABLE `scoreboard_jury`
  ADD COLUMN `pending` int(4) NOT NULL DEFAULT '0' COMMENT 'Number of submissions pending judgement' AFTER `submissions`;

ALTER TABLE `scoreboard_public`
  ADD COLUMN `pending` int(4) NOT NULL  DEFAULT '0'COMMENT 'Number of submissions pending judgement' AFTER `submissions`;

CREATE TABLE `auditlog` (
  `logid` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `logtime` datetime NOT NULL,
  `cid` int(4) unsigned DEFAULT NULL,
  `user` varchar(255) DEFAULT NULL,
  `datatype` varchar(15) DEFAULT NULL,
  `dataid` varchar(15) DEFAULT NULL,
  `action` varchar(30) DEFAULT NULL,
  `extrainfo` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`logid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Log of all actions performed';

CREATE TABLE `balloon` (
  `balloonid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
  `submitid` int(4) unsigned NOT NULL COMMENT 'Submission for which balloon was earned',
  `done` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'Has been handed out yet?',
  PRIMARY KEY (`balloonid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Balloons to be handed out';

-- Resize datastructures to fit "arbitrary" large data to satisfy
-- http://domjudge.a-eskwadraat.nl/trac/ticket/15 for the ICPC CSS spec.
ALTER TABLE `clarification`
  MODIFY COLUMN `body` longtext NOT NULL COMMENT 'Clarification text';

ALTER TABLE `configuration`
  MODIFY `value` longtext NOT NULL COMMENT 'Content of the configuration variable';

ALTER TABLE `event`
  MODIFY COLUMN `description` longtext NOT NULL COMMENT 'Event description';

ALTER TABLE `judging`
  MODIFY COLUMN `output_compile` longblob COMMENT 'Output of the compiling the program';

ALTER TABLE `judging_run`
  MODIFY COLUMN `output_run` longblob COMMENT 'Output of running the program',
  MODIFY COLUMN `output_diff` longblob COMMENT 'Diffing the program output and testcase output',
  MODIFY COLUMN `output_error` longblob COMMENT 'Standard error output of the program';

ALTER TABLE `submission`
  MODIFY COLUMN `sourcecode` longblob NOT NULL COMMENT 'Full source code';

ALTER TABLE `team`
  MODIFY COLUMN `members` longtext COMMENT 'Team member names (freeform)',
  MODIFY COLUMN `comments` longtext COMMENT 'Comments about this team';

ALTER TABLE `team_affiliation`
  MODIFY COLUMN `comments` longtext COMMENT 'Comments';

--
-- Transfer data from old to new structure
--

--
-- Add/remove sample/initial contents
--

UPDATE team_affiliation SET country = "NLD" WHERE country = "NL";

INSERT INTO `configuration` (`name`, `value`) VALUES ('clar_answers', 'No comment	Read the problem statement carefully');
INSERT INTO `configuration` (`name`, `value`) VALUES ('clar_categories', 'general:General issue	technical:Technical issue');
INSERT INTO `configuration` (`name`, `value`) VALUES ('show_pending', '0');
INSERT INTO `configuration` (`name`, `value`) VALUES ('disable_verify', '0');

--
-- Finally remove obsolete structures after moving data
--

ALTER TABLE `scoreboard_jury`
  DROP COLUMN balloon;
