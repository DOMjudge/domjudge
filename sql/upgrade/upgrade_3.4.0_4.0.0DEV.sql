-- This script upgrades table structure, data, and privileges
-- from/to the exact version numbers specified in the filename.

-- It needs to be run from the sql/ directory (not sql/upgrade/).

--
-- First execute a check whether this upgrade should apply. The check
-- below should fail if this upgrade has already been applied, but
-- keep everything unchanged if not.
--

-- @UPGRADE-CHECK@
ALTER TABLE `language` ADD  COLUMN `extensions` varchar(255);
ALTER TABLE `language` DROP COLUMN `extensions`;

--
-- Create additional structures
--

-- Create a new key `cid` here, later recreate the old key as `cid_2`
-- after the new submittime column has been created.
SET FOREIGN_KEY_CHECKS = 0;
ALTER TABLE `clarification`
  DROP KEY `cid`,
  ADD KEY `cid` (`cid`);
SET FOREIGN_KEY_CHECKS = 1;

ALTER TABLE `configuration`
  MODIFY COLUMN `value` longtext NOT NULL COMMENT 'Content of the configuration variable (JSON encoded)';

ALTER TABLE `judging_run`
  ADD COLUMN `output_system` longblob COMMENT 'Judging system output' AFTER `output_error`;

ALTER TABLE `language`
  ADD COLUMN `extensions` longtext COMMENT 'List of recognized extensions (JSON encoded)' AFTER `name`;

-- Rename scoreboard cache tables to match new rankcache_{jury,public}.

RENAME TABLE `scoreboard_jury`   TO `scorecache_jury`;
RENAME TABLE `scoreboard_public` TO `scorecache_public`;


CREATE TABLE `executable` (
  `execid` varchar(32) NOT NULL COMMENT 'Unique ID (string)',
  `md5sum` char(32) DEFAULT NULL COMMENT 'Md5sum of zip file',
  `zipfile` longblob COMMENT 'Zip file',
  `description` varchar(255) DEFAULT NULL COMMENT 'Description of this executable',
  `type` varchar(8) NOT NULL COMMENT 'Type of executable',
  PRIMARY KEY (`execid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Compile, compare, and run script executable bundles';

ALTER TABLE `problem`
  MODIFY COLUMN `special_run` varchar(32) DEFAULT NULL COMMENT 'Script to run submissions for this problem',
  MODIFY COLUMN `special_compare` varchar(32) DEFAULT NULL COMMENT 'Script to compare problem and jury output for this problem';

ALTER TABLE `language`
  ADD COLUMN `compile_script` varchar(32) DEFAULT NULL COMMENT 'Script to compile source code for this language';

ALTER TABLE `testcase`
  ADD COLUMN `sample` tinyint(1) unsigned NOT NULL default '0' COMMENT 'Sample testcases that can be shared with teams' AFTER `description`;

-- Before modifying the datetime to decimal(32.9) data type, we have
-- to move the data to be able to convert it afterwards.

ALTER TABLE `auditlog`
  CHANGE COLUMN `logtime` `logtime_old` datetime NOT NULL,
  ADD COLUMN `logtime` decimal(32,9) unsigned NOT NULL COMMENT 'Timestamp of the logentry' AFTER `logid`;

ALTER TABLE `clarification`
  CHANGE COLUMN `submittime` `submittime_old` datetime NOT NULL,
  ADD COLUMN `submittime` decimal(32,9) unsigned NOT NULL COMMENT 'Time sent' AFTER `respid`,
  ADD KEY `cid_2` (`cid`,`answered`,`submittime`);

ALTER TABLE `contest`
  CHANGE COLUMN `activatetime` `activatetime_old` datetime NOT NULL,
  CHANGE COLUMN `starttime` `starttime_old` datetime NOT NULL,
  CHANGE COLUMN `freezetime` `freezetime_old` datetime DEFAULT NULL,
  CHANGE COLUMN `endtime` `endtime_old` datetime NOT NULL,
  CHANGE COLUMN `unfreezetime` `unfreezetime_old` datetime DEFAULT NULL,
  ADD COLUMN `activatetime` decimal(32,9) unsigned NOT NULL COMMENT 'Time contest becomes visible in team/public views' AFTER `contestname`,
  ADD COLUMN `starttime` decimal(32,9) unsigned NOT NULL COMMENT 'Time contest starts, submissions accepted' AFTER `activatetime`,
  ADD COLUMN `freezetime` decimal(32,9) unsigned DEFAULT NULL COMMENT 'Time scoreboard is frozen' AFTER `starttime`,
  ADD COLUMN `endtime` decimal(32,9) unsigned NOT NULL COMMENT 'Time after which no more submissions are accepted' AFTER `freezetime`,
  ADD COLUMN `unfreezetime` decimal(32,9) unsigned DEFAULT NULL COMMENT 'Unfreeze a frozen scoreboard at this time' AFTER `endtime`,
  ADD COLUMN `starttime_string` varchar(20) NOT NULL COMMENT 'Authoritative absolute (only!) string representation of starttime' AFTER `activatetime_string`;

ALTER TABLE `event`
  CHANGE COLUMN `eventtime` `eventtime_old` datetime NOT NULL,
  ADD COLUMN `eventtime` decimal(32,9) unsigned NOT NULL COMMENT 'When the event occurred' AFTER `eventid`;

ALTER TABLE `judgehost`
  CHANGE COLUMN `polltime` `polltime_old` datetime DEFAULT NULL,
  ADD COLUMN `polltime` decimal(32,9) unsigned DEFAULT NULL COMMENT 'Time of last poll by autojudger' AFTER `active`;

ALTER TABLE `judging`
  CHANGE COLUMN `starttime` `starttime_old` datetime NOT NULL,
  CHANGE COLUMN `endtime` `endtime_old` datetime DEFAULT NULL,
  ADD COLUMN `starttime` decimal(32,9) unsigned NOT NULL COMMENT 'Time judging started' AFTER `submitid`,
  ADD COLUMN `endtime` decimal(32,9) unsigned DEFAULT NULL COMMENT 'Time judging ended, null = still busy' AFTER `starttime`;

ALTER TABLE `submission`
  CHANGE COLUMN `submittime` `submittime_old` datetime NOT NULL,
  ADD COLUMN `submittime` decimal(32,9) unsigned NOT NULL COMMENT 'Time submitted' AFTER `langid`;

ALTER TABLE `team`
  CHANGE COLUMN `judging_last_started` `judging_last_started_old` datetime DEFAULT NULL,
  CHANGE COLUMN `teampage_first_visited` `teampage_first_visited_old` datetime DEFAULT NULL,
  ADD COLUMN `judging_last_started` decimal(32,9) unsigned DEFAULT NULL COMMENT 'Start time of last judging for priorization' AFTER `comments`,
  ADD COLUMN `teampage_first_visited` decimal(32,9) unsigned DEFAULT NULL COMMENT 'Time of first teampage view' AFTER `judging_last_started`;

-- Consistent description of primary key 'Unique ID':
ALTER TABLE `configuration`
  MODIFY COLUMN `configid`   int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID';
ALTER TABLE `contest`
  MODIFY COLUMN `cid`        int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID';
ALTER TABLE `judging_run`
  MODIFY COLUMN `runid`      int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID';
ALTER TABLE `testcase`
  MODIFY COLUMN `testcaseid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID';

-- We move the affilid primary key to shortname and create a new
-- auto-incremented affilid. We drop and recreate the foreign key
-- constraint in the team table to allow the update.
SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE `team`
  DROP FOREIGN KEY `team_ibfk_2`,
  ADD COLUMN `affilid_old` varchar(30) NOT NULL AFTER `affilid`;

ALTER TABLE `team_affiliation`
  ADD COLUMN `shortname` varchar(30) NOT NULL COMMENT 'Short descriptive name' AFTER `affilid`;

UPDATE `team_affiliation` SET `shortname` = `affilid`;
UPDATE `team` SET `affilid_old` = `affilid`;

ALTER TABLE `team`
  MODIFY COLUMN `affilid` int(4) unsigned DEFAULT NULL COMMENT 'Team affiliation ID';

ALTER TABLE `team_affiliation`
  MODIFY COLUMN `affilid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID';

UPDATE `team`
  LEFT JOIN `team_affiliation` affil ON team.affilid_old = affil.shortname
  SET team.affilid = affil.affilid;

ALTER TABLE `team`
  DROP COLUMN `affilid_old`,
  ADD FOREIGN KEY (`affilid`) REFERENCES `team_affiliation` (`affilid`) ON DELETE SET NULL;

-- Similarly, we move the probid primary key to shortname and create a
-- new auto-incremented probid. We drop and recreate all (foreign) key
-- constraint in tables referencing probid to allow the update.
ALTER TABLE `clarification`
  DROP FOREIGN KEY `clarification_ibfk_3`,
  DROP KEY `probid`,
  ADD COLUMN `probid_old` varchar(8) DEFAULT NULL AFTER `probid`;
ALTER TABLE `event`
  ADD COLUMN `probid_old` varchar(8) DEFAULT NULL AFTER `probid`;
ALTER TABLE `scorecache_jury`
  DROP PRIMARY KEY,
  ADD COLUMN `probid_old` varchar(8) DEFAULT NULL AFTER `probid`;
ALTER TABLE `scorecache_public`
  DROP PRIMARY KEY,
  ADD COLUMN `probid_old` varchar(8) DEFAULT NULL AFTER `probid`;
ALTER TABLE `submission`
  DROP FOREIGN KEY `submission_ibfk_3`,
  DROP KEY `probid`,
  ADD COLUMN `probid_old` varchar(8) DEFAULT NULL AFTER `probid`;
ALTER TABLE `testcase`
  DROP FOREIGN KEY `testcase_ibfk_1`,
  DROP KEY `probid`,
  DROP KEY `rank`,
  ADD COLUMN `probid_old` varchar(8) DEFAULT NULL AFTER `probid`;

ALTER TABLE `problem`
  ADD COLUMN `shortname` varchar(8) NOT NULL COMMENT 'Unique ID (string)' AFTER `probid`;

UPDATE `problem` SET `shortname` = `probid`;

UPDATE `clarification`     SET `probid_old` = `probid`;
UPDATE `event`             SET `probid_old` = `probid`;
UPDATE `scorecache_jury`   SET `probid_old` = `probid`;
UPDATE `scorecache_public` SET `probid_old` = `probid`;
UPDATE `submission`        SET `probid_old` = `probid`;
UPDATE `testcase`          SET `probid_old` = `probid`;

ALTER TABLE `clarification`
  MODIFY COLUMN `probid` int(4) unsigned DEFAULT NULL COMMENT 'Problem associated to this clarification';
ALTER TABLE `event`
  MODIFY COLUMN `probid` int(4) unsigned DEFAULT NULL COMMENT 'Problem ID';
ALTER TABLE `scorecache_jury`
  MODIFY COLUMN `probid` int(4) unsigned NOT NULL COMMENT 'Problem ID';
ALTER TABLE `scorecache_public`
  MODIFY COLUMN `probid` int(4) unsigned NOT NULL COMMENT 'Problem ID';
ALTER TABLE `submission`
  MODIFY COLUMN `probid` int(4) unsigned NOT NULL COMMENT 'Problem ID';
ALTER TABLE `testcase`
  MODIFY COLUMN `probid` int(4) unsigned NOT NULL COMMENT 'Corresponding problem ID';

ALTER TABLE `problem`
  MODIFY COLUMN `probid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
  ADD UNIQUE KEY `shortname` (`shortname`,`cid`);

UPDATE `clarification`
  LEFT JOIN `problem` ON clarification.probid_old = problem.shortname
  SET clarification.probid = problem.probid;
UPDATE `event`
  LEFT JOIN `problem` ON event.probid_old = problem.shortname
  SET event.probid = problem.probid;
UPDATE `scorecache_jury`
  LEFT JOIN `problem` ON scorecache_jury.probid_old = problem.shortname
  SET scorecache_jury.probid = problem.probid;
UPDATE `scorecache_public`
  LEFT JOIN `problem` ON scorecache_public.probid_old = problem.shortname
  SET scorecache_public.probid = problem.probid;
UPDATE `submission`
  LEFT JOIN `problem` ON submission.probid_old = problem.shortname
  SET submission.probid = problem.probid;
UPDATE `testcase`
  LEFT JOIN `problem` ON testcase.probid_old = problem.shortname
  SET testcase.probid = problem.probid;

ALTER TABLE `clarification`
  DROP COLUMN `probid_old`,
  ADD KEY `probid` (`probid`),
  ADD CONSTRAINT `clarification_ibfk_3` FOREIGN KEY (`probid`) REFERENCES `problem` (`probid`) ON DELETE SET NULL;
ALTER TABLE `event`
  DROP COLUMN `probid_old`;
ALTER TABLE `scorecache_jury`
  DROP COLUMN `probid_old`,
  ADD PRIMARY KEY (`cid`,`teamid`,`probid`);
ALTER TABLE `scorecache_public`
  DROP COLUMN `probid_old`,
  ADD PRIMARY KEY (`cid`,`teamid`,`probid`);
ALTER TABLE `submission`
  DROP COLUMN `probid_old`,
  ADD KEY `probid` (`probid`),
  ADD CONSTRAINT `submission_ibfk_3` FOREIGN KEY (`probid`) REFERENCES `problem` (`probid`) ON DELETE CASCADE;
ALTER TABLE `testcase`
  DROP COLUMN `probid_old`,
  ADD KEY `probid` (`probid`),
  ADD UNIQUE KEY `rank` (`probid`,`rank`),
  ADD CONSTRAINT `testcase_ibfk_1` FOREIGN KEY (`probid`) REFERENCES `problem` (`probid`) ON DELETE CASCADE;
-- Pfew, we're done with changing probid!

-- Finally, replace team 'login' by AUTO_INCREMENT teamid:
ALTER TABLE `clarification`
  CHANGE COLUMN `sender` `sender_old` varchar(15) DEFAULT NULL,
  CHANGE COLUMN `recipient` `recipient_old` varchar(15) DEFAULT NULL,
  ADD COLUMN `sender` int(4) unsigned DEFAULT NULL COMMENT 'Team ID, null means jury' AFTER `sender_old`,
  ADD COLUMN `recipient` int(4) unsigned DEFAULT NULL COMMENT 'Team ID, null means to jury or to all' AFTER `recipient_old`;
ALTER TABLE `event`
  CHANGE COLUMN `teamid` `teamid_old` varchar(15) DEFAULT NULL,
  ADD COLUMN `teamid` int(4) unsigned DEFAULT NULL COMMENT 'Team ID' AFTER `teamid_old`;
ALTER TABLE `scorecache_jury`
  DROP PRIMARY KEY,
  CHANGE COLUMN `teamid` `teamid_old` varchar(15) NOT NULL,
  ADD COLUMN `teamid` int(4) unsigned NOT NULL COMMENT 'Team ID' AFTER `teamid_old`;
ALTER TABLE `scorecache_public`
  DROP PRIMARY KEY,
  CHANGE COLUMN `teamid` `teamid_old` varchar(15) NOT NULL,
  ADD COLUMN `teamid` int(4) unsigned NOT NULL COMMENT 'Team ID' AFTER `teamid_old`;
ALTER TABLE `submission`
  DROP FOREIGN KEY `submission_ibfk_2`,
  DROP KEY `teamid`,
  DROP KEY `teamid_2`,
  CHANGE COLUMN `teamid` `teamid_old` varchar(15) NOT NULL,
  ADD COLUMN `teamid` int(4) unsigned NOT NULL COMMENT 'Team ID' AFTER `teamid_old`;
ALTER TABLE `team_unread`
  DROP FOREIGN KEY `team_unread_ibfk_1`,
  DROP PRIMARY KEY,
  CHANGE COLUMN `teamid` `teamid_old` varchar(15) NOT NULL,
  ADD COLUMN `teamid` int(4) unsigned NOT NULL COMMENT 'Team ID' AFTER `teamid_old`,
  MODIFY COLUMN `mesgid` int(4) unsigned NOT NULL COMMENT 'Clarification ID';

ALTER TABLE `team`
  DROP PRIMARY KEY,
  ADD COLUMN `teamid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID' FIRST,
  ADD PRIMARY KEY (`teamid`);

UPDATE `clarification`
  LEFT JOIN `team` ON clarification.sender_old = team.login
  SET clarification.sender = team.teamid;
UPDATE `clarification`
  LEFT JOIN `team` ON clarification.recipient_old = team.login
  SET clarification.recipient = team.teamid;
UPDATE `event`
  LEFT JOIN `team` ON event.teamid_old = team.login
  SET event.teamid = team.teamid;
UPDATE `scorecache_jury`
  LEFT JOIN `team` ON scorecache_jury.teamid_old = team.login
  SET scorecache_jury.teamid = team.teamid;
UPDATE `scorecache_public`
  LEFT JOIN `team` ON scorecache_public.teamid_old = team.login
  SET scorecache_public.teamid = team.teamid;
UPDATE `submission`
  LEFT JOIN `team` ON submission.teamid_old = team.login
  SET submission.teamid = team.teamid;
UPDATE `team_unread`
  LEFT JOIN `team` ON team_unread.teamid_old = team.login
  SET team_unread.teamid = team.teamid;

ALTER TABLE `clarification`
  DROP COLUMN `recipient_old`,
  DROP COLUMN `sender_old`;
ALTER TABLE `event`
  DROP COLUMN `teamid_old`;
ALTER TABLE `scorecache_jury`
  DROP COLUMN `teamid_old`,
  ADD PRIMARY KEY (`cid`,`teamid`,`probid`);
ALTER TABLE `scorecache_public`
  DROP COLUMN `teamid_old`,
  ADD PRIMARY KEY (`cid`,`teamid`,`probid`);
ALTER TABLE `submission`
  DROP COLUMN `teamid_old`,
  ADD KEY `teamid` (`cid`,`teamid`),
  ADD KEY `teamid_2` (`teamid`),
  ADD CONSTRAINT `submission_ibfk_2` FOREIGN KEY (`teamid`) REFERENCES `team` (`teamid`) ON DELETE CASCADE;
ALTER TABLE `team_unread`
  DROP COLUMN `teamid_old`,
  ADD PRIMARY KEY (`teamid`,`type`,`mesgid`),
  ADD CONSTRAINT `team_unread_ibfk_1` FOREIGN KEY (`teamid`) REFERENCES `team` (`teamid`) ON DELETE CASCADE;

SET FOREIGN_KEY_CHECKS = 1;

-- Create some new tables and keys after ID updates to reduce
-- changes necessary.

ALTER TABLE `event`
  ADD KEY `cid` (`cid`),
  ADD KEY `clarid` (`clarid`),
  ADD KEY `langid` (`langid`),
  ADD KEY `probid` (`probid`),
  ADD KEY `submitid` (`submitid`),
  ADD KEY `judgingid` (`judgingid`),
  ADD KEY `teamid` (`teamid`),
  ADD FOREIGN KEY (`cid`) REFERENCES `contest` (`cid`) ON DELETE CASCADE,
  ADD FOREIGN KEY (`clarid`) REFERENCES `clarification` (`clarid`) ON DELETE CASCADE,
  ADD FOREIGN KEY (`langid`) REFERENCES `language` (`langid`) ON DELETE CASCADE,
  ADD FOREIGN KEY (`probid`) REFERENCES `problem` (`probid`) ON DELETE CASCADE,
  ADD FOREIGN KEY (`submitid`) REFERENCES `submission` (`submitid`) ON DELETE CASCADE,
  ADD FOREIGN KEY (`judgingid`) REFERENCES `judging` (`judgingid`) ON DELETE CASCADE,
  ADD FOREIGN KEY (`teamid`) REFERENCES `team` (`teamid`) ON DELETE CASCADE;

CREATE TABLE `user` (
  `userid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
  `username` varchar(255) NOT NULL COMMENT 'User login name',
  `name` varchar(255) NOT NULL COMMENT 'Name',
  `email` varchar(255) DEFAULT NULL COMMENT 'Email address',
  `last_login` decimal(32,9) unsigned DEFAULT NULL COMMENT 'Time of last successful login',
  `last_ip_address` varchar(255) DEFAULT NULL COMMENT 'Last IP address of successful login',
  `password` varchar(32) DEFAULT NULL COMMENT 'Password hash',
  `ip_address` varchar(255) DEFAULT NULL COMMENT 'IP Address used to autologin',
  `enabled` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT 'Whether the user is able to log in',
  `teamid` int(4) unsigned DEFAULT NULL COMMENT 'Team associated with',
  PRIMARY KEY (`userid`),
  UNIQUE KEY `username` (`username`),
  KEY `teamid` (`teamid`),
  CONSTRAINT `user_ibfk_1` FOREIGN KEY (`teamid`) REFERENCES `team` (`teamid`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Users that have access to DOMjudge';

CREATE TABLE `role` (
  `roleid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
  `role` varchar(15) NOT NULL COMMENT 'Role name',
  `description` varchar(255) NOT NULL COMMENT 'Description for the web interface',
  PRIMARY KEY (`roleid`),
  UNIQUE KEY `role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Possible user roles';

CREATE TABLE `userrole` (
  `userid` int(4) unsigned NOT NULL COMMENT 'User ID',
  `roleid` int(4) unsigned NOT NULL COMMENT 'Role ID',
  KEY `userid` (`userid`),
  KEY `roleid` (`roleid`),
  CONSTRAINT `userrole_pk` PRIMARY KEY (`userid`, `roleid`),
  CONSTRAINT `userrole_ibfk_1` FOREIGN KEY (`userid`) REFERENCES `user` (`userid`) ON DELETE CASCADE,
  CONSTRAINT `userrole_ibfk_2` FOREIGN KEY (`roleid`) REFERENCES `role` (`roleid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Many-to-Many mapping of users and roles';

CREATE TABLE `rankcache_jury` (
  `cid` int(4) unsigned NOT NULL COMMENT 'Contest ID',
  `teamid` int(4) unsigned NOT NULL COMMENT 'Team ID',
  `correct` int(4) unsigned NOT NULL DEFAULT '0' COMMENT 'Number of problems solved',
  `totaltime` int(4) unsigned NOT NULL DEFAULT '0' COMMENT 'Total time spent',
  PRIMARY KEY  (`cid`,`teamid`),
  KEY `order` (`cid`,`correct`, `totaltime`) USING BTREE,
  CONSTRAINT `rankcache_jury_ibfk_1` FOREIGN KEY (`cid`) REFERENCES `contest` (`cid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Rank cache (jury version)';

CREATE TABLE `rankcache_public` (
  `cid` int(4) unsigned NOT NULL COMMENT 'Contest ID',
  `teamid` int(4) unsigned NOT NULL COMMENT 'Team ID',
  `correct` int(4) unsigned NOT NULL DEFAULT '0' COMMENT 'Number of problems solved',
  `totaltime` int(4) unsigned NOT NULL DEFAULT '0' COMMENT 'Total time spent',
  PRIMARY KEY  (`cid`,`teamid`),
  KEY `order` (`cid`,`correct`,`totaltime`) USING BTREE,
  CONSTRAINT `rankcache_public_ibfk_1` FOREIGN KEY (`cid`) REFERENCES `contest` (`cid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Rank cache (public/team version)';

ALTER TABLE `team`
  ADD COLUMN `externalid` varchar(128) DEFAULT NULL COMMENT 'Team ID in an external system' AFTER `teamid`,
  ADD KEY `externalid` (`externalid`);

--
-- Transfer data from old to new structure
--

UPDATE `auditlog` SET `logtime` = UNIX_TIMESTAMP(logtime_old);

UPDATE `clarification` SET `submittime` = UNIX_TIMESTAMP(submittime_old);

UPDATE `contest` SET
  `activatetime`     = UNIX_TIMESTAMP(activatetime_old),
  `starttime`        = UNIX_TIMESTAMP(starttime_old),
  `endtime`          = UNIX_TIMESTAMP(endtime_old),
  `starttime_string` = `starttime_old`;

UPDATE `contest` SET `freezetime`   = UNIX_TIMESTAMP(freezetime_old)   WHERE `freezetime_old`   IS NOT NULL;
UPDATE `contest` SET `unfreezetime` = UNIX_TIMESTAMP(unfreezetime_old) WHERE `unfreezetime_old` IS NOT NULL;

UPDATE `event` SET `eventtime` = UNIX_TIMESTAMP(eventtime_old);

UPDATE `judgehost` SET `polltime` = UNIX_TIMESTAMP(polltime_old);

UPDATE `judging` SET
  `starttime` = UNIX_TIMESTAMP(starttime_old),
  `endtime`   = UNIX_TIMESTAMP(endtime_old);

UPDATE `submission` SET `submittime` = UNIX_TIMESTAMP(submittime_old);

UPDATE `team` SET
  `judging_last_started`   = UNIX_TIMESTAMP(judging_last_started_old),
  `teampage_first_visited` = UNIX_TIMESTAMP(teampage_first_visited_old);

--
-- Add/remove sample/initial contents
--

UPDATE `configuration` SET `value` = '"%H:%M"', `description` = 'The format used to print times. For formatting options see the PHP \'strftime\' function.' WHERE `name` = 'time_format';

INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('timelimit_overshoot', '"1s|10%"', 'string', 'Time that submissions are kept running beyond timelimt before being killed. Specify as "Xs" for X seconds, "Y%" as percentage, or a combination of both separated by one of "+|&" for the sum, maximum, or minimum of both.');

INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('show_sample_output', '0', 'bool', 'Should teams be able to view a diff of their and the reference output to sample testcases?');

INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('compile_penalty', '1', 'bool', 'Should submissions with compiler-error incur penalty time (and show on the scoreboard)?');

INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('default_compare', '"compare"', 'string', 'The script used to compare outputs if no special compare script specified.');
INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('default_run', '"run"', 'string', 'The script used to run submissions if no special run script specified.');

INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('compile_memory', '2097152', 'int', 'Maximum memory usage (in kB) by *compilers*.  This is only to safeguard against malicious code, so a reasonable but large amount should do.');
INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('compile_filesize', '65536', 'int', 'Maximum filesize (in kB) compilers may write. Submission will fail with compiler-error when trying to write more, so this should be greater than any *intermediate* result written by compilers.');

UPDATE `language` SET `extensions` = '["adb","ads"]' WHERE `langid` = 'adb';
UPDATE `language` SET `extensions` = '["awk"]' WHERE `langid` = 'awk';
UPDATE `language` SET `extensions` = '["bash"]' WHERE `langid` = 'bash';
UPDATE `language` SET `extensions` = '["c"]' WHERE `langid` = 'c';
UPDATE `language` SET `extensions` = '["cpp","cc","c++"]' WHERE `langid` = 'cpp';
UPDATE `language` SET `extensions` = '["csharp","cs"]' WHERE `langid` = 'csharp';
UPDATE `language` SET `extensions` = '["f95","f90"]' WHERE `langid` = 'f95';
UPDATE `language` SET `extensions` = '["hs","lhs"]' WHERE `langid` = 'hs';
UPDATE `language` SET `extensions` = '["java"]' WHERE `langid` = 'java';
UPDATE `language` SET `extensions` = '["lua"]' WHERE `langid` = 'lua';
UPDATE `language` SET `extensions` = '["pas","p"]' WHERE `langid` = 'pas';
UPDATE `language` SET `extensions` = '["pl"]' WHERE `langid` = 'pl';
UPDATE `language` SET `extensions` = '["py2","py"]' WHERE `langid` = 'py2';
UPDATE `language` SET `extensions` = '["py3"]' WHERE `langid` = 'py3';
UPDATE `language` SET `extensions` = '["scala"]' WHERE `langid` = 'scala';
UPDATE `language` SET `extensions` = '["sh"]' WHERE `langid` = 'sh';

INSERT INTO `executable` (`execid`, `description`, `type`) VALUES ('adb', 'adb', 'compile');
INSERT INTO `executable` (`execid`, `description`, `type`) VALUES ('awk', 'awk', 'compile');
INSERT INTO `executable` (`execid`, `description`, `type`) VALUES ('bash', 'bash', 'compile');
INSERT INTO `executable` (`execid`, `description`, `type`) VALUES ('c', 'c', 'compile');
INSERT INTO `executable` (`execid`, `description`, `type`) VALUES ('cpp', 'cpp', 'compile');
INSERT INTO `executable` (`execid`, `description`, `type`) VALUES ('csharp', 'csharp', 'compile');
INSERT INTO `executable` (`execid`, `description`, `type`) VALUES ('f95', 'f95', 'compile');
INSERT INTO `executable` (`execid`, `description`, `type`) VALUES ('hs', 'hs', 'compile');
INSERT INTO `executable` (`execid`, `description`, `type`) VALUES ('java_gcj', 'java_gcj', 'compile');
INSERT INTO `executable` (`execid`, `description`, `type`) VALUES ('java_javac', 'java_javac', 'compile');
INSERT INTO `executable` (`execid`, `description`, `type`) VALUES ('java_javac_detect', 'java_javac_detect', 'compile');
INSERT INTO `executable` (`execid`, `description`, `type`) VALUES ('lua', 'lua', 'compile');
INSERT INTO `executable` (`execid`, `description`, `type`) VALUES ('pas', 'pas', 'compile');
INSERT INTO `executable` (`execid`, `description`, `type`) VALUES ('pl', 'pl', 'compile');
INSERT INTO `executable` (`execid`, `description`, `type`) VALUES ('plg', 'plg', 'compile');
INSERT INTO `executable` (`execid`, `description`, `type`) VALUES ('py2', 'py2', 'compile');
INSERT INTO `executable` (`execid`, `description`, `type`) VALUES ('py3', 'py3', 'compile');
INSERT INTO `executable` (`execid`, `description`, `type`) VALUES ('rb', 'rb', 'compile');
INSERT INTO `executable` (`execid`, `description`, `type`) VALUES ('scala', 'scala', 'compile');
INSERT INTO `executable` (`execid`, `description`, `type`) VALUES ('sh', 'sh', 'compile');
INSERT INTO `executable` (`execid`, `description`, `type`) VALUES ('compare', 'default compare script', 'compare');
INSERT INTO `executable` (`execid`, `description`, `type`) VALUES ('float', 'default compare script for floats with prec 1E-7', 'compare');
INSERT INTO `executable` (`execid`, `description`, `type`) VALUES ('run', 'default run script', 'run');
INSERT INTO `executable` (`execid`, `description`, `type`) VALUES ('boolfind_cmp', 'boolfind comparator', 'compare');
INSERT INTO `executable` (`execid`, `description`, `type`) VALUES ('boolfind_run', 'boolfind run script', 'run');

source mysql_db_files_defaultdata.sql
source mysql_db_files_examples.sql

-- Update languages to use executable scripts, special case Java:
UPDATE `language` SET `compile_script` = `langid`;
UPDATE `language` SET `compile_script` = 'java_javac_detect' WHERE `langid` = 'java';

UPDATE `problem` SET `special_compare` = 'float' WHERE `shortname` = 'fltcmp';
UPDATE `problem` SET `special_compare` = 'boolfind_cmp', `special_run` = 'boolfind_run' WHERE `shortname` = 'boolfind';

INSERT INTO `role` (`roleid`, `role`, `description`) VALUES (1, 'admin',          'Administrative User');
INSERT INTO `role` (`roleid`, `role`, `description`) VALUES (2, 'jury',           'Jury User');
INSERT INTO `role` (`roleid`, `role`, `description`) VALUES (3, 'team',           'Team Member');
INSERT INTO `role` (`roleid`, `role`, `description`) VALUES (4, 'balloon',        'Balloon runner');
INSERT INTO `role` (`role`, `description`) VALUES ('print',             'print');
INSERT INTO `role` (`role`, `description`) VALUES ('judgehost',         '(Internal/System) Judgehost');
INSERT INTO `role` (`role`, `description`) VALUES ('event_reader',      '(Internal/System) event_reader');
INSERT INTO `role` (`role`, `description`) VALUES ('full_event_reader', '(Internal/System) full_event_reader');

INSERT INTO `user` (`userid`, `username`, `name`, `password`) VALUES (1, 'admin', 'Administrator', MD5('admin#admin'));
INSERT INTO `user` (`userid`, `username`, `name`, `password`) VALUES (2, 'judgehost', 'User for judgedaemons', NULL);

INSERT INTO `userrole` (`userid`, `roleid`) VALUES (1, 1);
INSERT INTO `userrole` (`userid`, `roleid`) VALUES (2, 6);

INSERT INTO `language` (`langid`, `name`, `extensions`, `allow_submit`, `allow_judge`, `time_factor`) VALUES ('plg', 'Prolog', '["plg"]', 0, 1, 1);
INSERT INTO `language` (`langid`, `name`, `extensions`, `allow_submit`, `allow_judge`, `time_factor`) VALUES ('rb', 'Ruby', '["rb"]', 0, 1, 1);

--
-- Finally remove obsolete structures after moving data
--

ALTER TABLE `submission` DROP KEY `judgemark`;
ALTER TABLE `submission` DROP COLUMN `judgemark`;
ALTER TABLE `team` DROP COLUMN `authtoken`;

ALTER TABLE `auditlog` DROP COLUMN `logtime_old`;

ALTER TABLE `clarification` DROP COLUMN `submittime_old`;

ALTER TABLE `contest`
  DROP COLUMN `activatetime_old`,
  DROP COLUMN `starttime_old`,
  DROP COLUMN `freezetime_old`,
  DROP COLUMN `endtime_old`,
  DROP COLUMN `unfreezetime_old`;

ALTER TABLE `event` DROP COLUMN `eventtime_old`;

ALTER TABLE `judgehost` DROP COLUMN `polltime_old`;

ALTER TABLE `judging`
  DROP COLUMN `starttime_old`,
  DROP COLUMN `endtime_old`;

ALTER TABLE `submission` DROP COLUMN `submittime_old`;

ALTER TABLE `team`
  DROP COLUMN `judging_last_started_old`,
  DROP COLUMN `teampage_first_visited_old`;

ALTER TABLE `team_unread`
  DROP PRIMARY KEY,
  ADD PRIMARY KEY (`teamid`,`mesgid`),
  DROP COLUMN `type`;

ALTER TABLE `team`
  DROP COLUMN `login`;
