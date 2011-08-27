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
  CHANGE COLUMN `body` longtext NOT NULL COMMENT 'Clarification text';

ALTER TABLE `event`
  CHANGE COLUMN `description` longtext NOT NULL COMMENT 'Event description';

ALTER TABLE `judging`
  CHANGE COLUMN `output_compile` longtext COMMENT 'Output of the compiling the program';

ALTER TABLE `judging_run`
  CHANGE COLUMN `output_run` longtext COMMENT 'Output of running the program',
  CHANGE COLUMN `output_diff` longtext COMMENT 'Diffing the program output and testcase output',
  CHANGE COLUMN `output_error` longtext COMMENT 'Standard error output of the program';

ALTER TABLE `submission`
  CHANGE COLUMN `sourcecode` longblob NOT NULL COMMENT 'Full source code';

ALTER TABLE `team`
  CHANGE COLUMN `members` longtext COMMENT 'Team member names (freeform)',
  CHANGE COLUMN `comments` longtext COMMENT 'Comments about this team';

ALTER TABLE `team_affiliation`
  CHANGE COLUMN `comments` longtext COMMENT 'Comments';

--
-- Add/remove privileges
--

GRANT INSERT ON auditlog TO `domjudge_team`;

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

ALTER TABLE `scoreboard_jury`
  DROP COLUMN balloon;
