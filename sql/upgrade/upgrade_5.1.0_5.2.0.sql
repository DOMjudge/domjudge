-- This script upgrades table structure, data, and privileges
-- from/to the exact version numbers specified in the filename.

--
-- First execute a check whether this upgrade should apply. The check
-- below should fail if this upgrade has already been applied, but
-- keep everything unchanged if not.
--

-- @UPGRADE-CHECK@
ALTER TABLE `contest` ADD  COLUMN `externalid` varchar(255);
ALTER TABLE `contest` DROP COLUMN `externalid`;

--
-- Create additional structures
--

ALTER TABLE `contest`
  MODIFY COLUMN `unfreezetime_string` varchar(64) DEFAULT NULL COMMENT 'Authoritative absolute or relative string representation of unfreezetime',
  ADD COLUMN `externalid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Contest ID in an external system' AFTER `cid`,
  ADD UNIQUE KEY `externalid` (`externalid`(190));

ALTER TABLE `user`
  MODIFY COLUMN `password` varchar(255) DEFAULT NULL COMMENT 'Password hash';

ALTER TABLE `problem`
  MODIFY COLUMN `timelimit` float unsigned NOT NULL DEFAULT '0' COMMENT 'Maximum run time (in seconds) for this problem';

ALTER TABLE `judgehost`
  DROP FOREIGN KEY `restriction_ibfk_1`,
  ADD CONSTRAINT `judgehost_ibfk_1` FOREIGN KEY (`restrictionid`) REFERENCES `judgehost_restriction` (`restrictionid`) ON DELETE SET NULL;

-- Merge {rank,score}cache_{public,jury} tables into one.
CREATE TABLE `rankcache` (
  `cid` int(4) unsigned NOT NULL COMMENT 'Contest ID',
  `teamid` int(4) unsigned NOT NULL COMMENT 'Team ID',
  `points_restricted` int(4) unsigned NOT NULL DEFAULT '0' COMMENT 'Total correctness points (restricted audience)',
  `totaltime_restricted` int(4) NOT NULL DEFAULT '0' COMMENT 'Total penalty time in minutes (restricted audience)',
  `points_public` int(4) unsigned NOT NULL DEFAULT '0' COMMENT 'Total correctness points (public)',
  `totaltime_public` int(4) NOT NULL DEFAULT '0' COMMENT 'Total penalty time in minutes (public)',
  PRIMARY KEY (`cid`,`teamid`),
  KEY `order_restricted` (`cid`,`points_restricted`,`totaltime_restricted`) USING BTREE,
  KEY `order_public` (`cid`,`points_public`,`totaltime_public`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Scoreboard rank cache';

CREATE TABLE `scorecache` (
  `cid` int(4) unsigned NOT NULL COMMENT 'Contest ID',
  `teamid` int(4) unsigned NOT NULL COMMENT 'Team ID',
  `probid` int(4) unsigned NOT NULL COMMENT 'Problem ID',
  `submissions_restricted` int(4) unsigned NOT NULL DEFAULT '0' COMMENT 'Number of submissions made (restricted audiences)',
  `pending_restricted` int(4) unsigned NOT NULL DEFAULT '0' COMMENT 'Number of submissions pending judgement (restricted audience)',
  `solvetime_restricted`  decimal(32,9) NOT NULL DEFAULT '0.000000000' COMMENT 'Seconds into contest when problem solved (restricted audience)',
  `is_correct_restricted` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'Has there been a correct submission? (restricted audience)',
  `submissions_public` int(4) unsigned NOT NULL DEFAULT '0' COMMENT 'Number of submissions made (public)',
  `pending_public` int(4) unsigned NOT NULL DEFAULT '0' COMMENT 'Number of submissions pending judgement (public)',
  `solvetime_public` decimal(32,9) NOT NULL DEFAULT '0.000000000' COMMENT 'Seconds into contest when problem solved (public)',
  `is_correct_public` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'Has there been a correct submission? (public)',
  PRIMARY KEY (`cid`,`teamid`,`probid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Scoreboard cache';

CREATE TABLE `internal_error` (
  `errorid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
  `judgingid` int(4) unsigned DEFAULT NULL COMMENT 'Judging ID',
  `cid` int(4) unsigned DEFAULT NULL COMMENT 'Contest ID',
  `description` varchar(255) NOT NULL COMMENT 'Description of the error',
  `judgehostlog` text NOT NULL COMMENT 'Last N lines of the judgehost log',
  `time` decimal(32,9) unsigned NOT NULL COMMENT 'Timestamp of the internal error',
  `disabled` text NOT NULL COMMENT 'Disabled stuff, JSON-encoded',
  `status` ENUM('open', 'resolved', 'ignored')  NOT NULL DEFAULT 'open' COMMENT 'Status of internal error',
  PRIMARY KEY (`errorid`),
  KEY `judgingid` (`judgingid`),
  KEY `cid` (`cid`),
  CONSTRAINT `internal_error_ibfk_1` FOREIGN KEY (`judgingid`) REFERENCES `judging` (`judgingid`) ON DELETE SET NULL,
  CONSTRAINT `internal_error_ibfk_2` FOREIGN KEY (`cid`) REFERENCES `contest` (`cid`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Log of judgehost internal errors';

ALTER TABLE `testcase`
  MODIFY COLUMN `description` longblob COMMENT 'Description of this testcase';

--
-- Transfer data from old to new structure
--

-- First assign a newly created judgehost to all judgings to guarantee
-- that we can add a constraint later.
REPLACE INTO `judgehost` (`hostname`, `active`) VALUES ('host-created-by-SQL-upgrade', '0');
UPDATE `judging` SET `judgehost` = 'host-created-by-SQL-upgrade' WHERE `judgehost` IS NULL;

-- We don't bother to migrate rankcache/scorecache data. It's just a cache.

--
-- Add/remove sample/initial contents
--
INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES
('output_storage_limit', '50000', 'int', 'Maximum size of error/system output stored in the database (in bytes); use "-1" to disable any limits.'),
('output_display_limit', '2000', 'int', 'Maximum size of run/diff/error/system output shown in the jury interface (in bytes); use "-1" to disable any limits.'),
('diskspace_error', '1048576', 'int', 'Minimum free disk space (in kB) on judgehosts.'),
('allow_openid_auth', '0', 'bool', 'Allow users to log in using OpenID'),
('openid_autocreate_team', '1', 'bool', 'Create a team for each user that logs in with OpenID'),
('openid_provider', '"https://accounts.google.com"', 'string', 'OpenID Provider URL'),
('openid_clientid', '""', 'string', 'OpenID Connect client id'),
('openid_clientsecret', '""', 'string', 'OpenID Connect client secret');

--
-- Finally remove obsolete structures after moving data
--

ALTER TABLE `judging`
  DROP FOREIGN KEY `judging_ibfk_3`;
ALTER TABLE `judging`
  MODIFY COLUMN `judgehost` varchar(50) NOT NULL COMMENT 'Judgehost that performed the judging',
  ADD CONSTRAINT `judging_ibfk_3` FOREIGN KEY (`judgehost`) REFERENCES `judgehost` (`hostname`);

DROP TABLE `rankcache_jury`, `rankcache_public`,`scorecache_jury`, `scorecache_public`;
