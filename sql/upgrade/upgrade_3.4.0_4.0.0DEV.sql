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

ALTER TABLE `configuration`
  MODIFY COLUMN `value` longtext NOT NULL COMMENT 'Content of the configuration variable (JSON encoded)';

ALTER TABLE `language`
  ADD COLUMN `extensions` longtext COMMENT 'List of recognized extensions (JSON encoded)' AFTER `name`;

CREATE TABLE `user` (
  `userid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
  `username` varchar(255) NOT NULL COMMENT 'User login name',
  `name` varchar(255) NOT NULL COMMENT 'Name',
  `email` varchar(255) DEFAULT NULL COMMENT 'Email address',
  `last_login` datetime DEFAULT NULL COMMENT 'Time of last successful login',
  `last_ip_address` varchar(255) DEFAULT NULL COMMENT 'Last IP address of successful login',
  `authtoken` varchar(255) DEFAULT NULL COMMENT 'Password/auth hash',
  `ip_address` varchar(255) DEFAULT NULL COMMENT 'IP Address used to autologin',
  `enabled` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT 'Whether the user is able to log in',
  `teamid` varchar(15) DEFAULT NULL COMMENT 'Team associated with',
  PRIMARY KEY (`userid`),
  UNIQUE KEY `username` (`username`),
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

--
-- Transfer data from old to new structure
--

--
-- Add/remove sample/initial contents
--

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
--
-- Finally remove obsolete structures after moving data
--

ALTER TABLE `submission` DROP KEY `judgemark`;
ALTER TABLE `submission` DROP COLUMN `judgemark`;

