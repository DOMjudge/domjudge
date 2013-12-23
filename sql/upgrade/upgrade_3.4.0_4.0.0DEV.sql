-- This script upgrades table structure, data, and privileges
-- from/to the exact version numbers specified in the filename.

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
  ADD FOREIGN KEY (`teamid`) REFERENCES `team` (`login`) ON DELETE CASCADE;

ALTER TABLE `language`
  ADD COLUMN `extensions` longtext COMMENT 'List of recognized extensions (JSON encoded)' AFTER `name`;

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
  `teamid` varchar(15) DEFAULT NULL COMMENT 'Team associated with',
  PRIMARY KEY (`userid`),
  UNIQUE KEY `username` (`username`),
  KEY `teamid` (`teamid`),
  CONSTRAINT `user_ibfk_1` FOREIGN KEY (`teamid`) REFERENCES `team` (`login`) ON DELETE SET NULL
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
  `teamid` varchar(15) NOT NULL COMMENT 'Team login',
  `correct` int(4) unsigned NOT NULL DEFAULT '0' COMMENT 'Number of problems solved',
  `totaltime` int(4) unsigned NOT NULL DEFAULT '0' COMMENT 'Total time spent',
  PRIMARY KEY  (`cid`,`teamid`),
  KEY `order` (`cid`,`correct`, `totaltime`) USING BTREE,
  CONSTRAINT `rankcache_jury_ibfk_1` FOREIGN KEY (`cid`) REFERENCES `contest` (`cid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Rank cache (jury version)';

CREATE TABLE `rankcache_public` (
  `cid` int(4) unsigned NOT NULL COMMENT 'Contest ID',
  `teamid` varchar(15) NOT NULL COMMENT 'Team login',
  `correct` int(4) unsigned NOT NULL DEFAULT '0' COMMENT 'Number of problems solved',
  `totaltime` int(4) unsigned NOT NULL DEFAULT '0' COMMENT 'Total time spent',
  PRIMARY KEY  (`cid`,`teamid`),
  KEY `order` (`cid`,`correct`,`totaltime`) USING BTREE,
  CONSTRAINT `rankcache_public_ibfk_1` FOREIGN KEY (`cid`) REFERENCES `contest` (`cid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Rank cache (public/team version)';

-- Rename scoreboard cache tables to match new rankcache_{jury,public}.

RENAME TABLE `scoreboard_jury`   TO `scorecache_jury`;
RENAME TABLE `scoreboard_public` TO `scorecache_public`;

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

INSERT INTO `role` (`roleid`, `role`, `description`) VALUES (1, 'admin',          'Administrative User');
INSERT INTO `role` (`roleid`, `role`, `description`) VALUES (2, 'jury',           'Jury User');
INSERT INTO `role` (`roleid`, `role`, `description`) VALUES (3, 'team',           'Team Member');
INSERT INTO `role` (`roleid`, `role`, `description`) VALUES (4, 'balloon',        'Balloon runner');
INSERT INTO `role` (`role`, `description`) VALUES ('print',             'print');
INSERT INTO `role` (`role`, `description`) VALUES ('judgehost',         '(Internal/System) Judgehost');
INSERT INTO `role` (`role`, `description`) VALUES ('event_reader',      '(Internal/System) event_reader');
INSERT INTO `role` (`role`, `description`) VALUES ('full_event_reader', '(Internal/System) full_event_reader');

INSERT INTO `user` (`userid`, `username`, `name`, `password`) VALUES ('1', 'admin', 'Administrator', MD5('admin#admin'));

INSERT INTO `userrole` (`userid`, `roleid`) VALUES ('1', '1');

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
