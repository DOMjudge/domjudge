-- This script upgrades table structure, data, and privileges
-- from/to the exact version numbers specified in the filename.

--
-- First execute a check whether this upgrade should apply. The check
-- below should fail if this upgrade has already been applied, but
-- keep everything unchanged if not.
--

-- @UPGRADE-CHECK@
SELECT '1'; -- No check possible yet.

--
-- Create additional structures
--

ALTER TABLE `contest`
  ADD COLUMN `externalid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Contest ID in an external system' AFTER `cid`,
  ADD UNIQUE KEY `externalid` (`externalid`(190));

-- Merge {rank,score}cache_{public,jury} tables into one.
CREATE TABLE `rankcache` (
  `cid` int(4) unsigned NOT NULL COMMENT 'Contest ID',
  `teamid` int(4) unsigned NOT NULL COMMENT 'Team ID',
  `points_restricted` int(4) unsigned NOT NULL DEFAULT '0' COMMENT 'Total correctness points (restricted audience)',
  `totaltime_restricted` int(4) unsigned NOT NULL DEFAULT '0' COMMENT 'Total time spent (restricted audience)',
  `points_public` int(4) unsigned NOT NULL DEFAULT '0' COMMENT 'Total correctness points (public)',
  `totaltime_public` int(4) unsigned NOT NULL DEFAULT '0' COMMENT 'Total time spent (public)',
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
  `totaltime_restricted` int(4) unsigned NOT NULL DEFAULT '0' COMMENT 'Total time spent (restricted audience)',
  `is_correct_restricted` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'Has there been a correct submission? (restricted audience)',
  `submissions_public` int(4) unsigned NOT NULL DEFAULT '0' COMMENT 'Number of submissions made (public)',
  `pending_public` int(4) unsigned NOT NULL DEFAULT '0' COMMENT 'Number of submissions pending judgement (public)',
  `totaltime_public` int(4) unsigned NOT NULL DEFAULT '0' COMMENT 'Total time spent (public)',
  `is_correct_public` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'Has there been a correct submission? (public)',
  PRIMARY KEY (`cid`,`teamid`,`probid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Scoreboard cache';


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

--
-- Finally remove obsolete structures after moving data
--

ALTER TABLE `judging`
  DROP FOREIGN KEY `judging_ibfk_3`;
ALTER TABLE `judging`
  MODIFY COLUMN `judgehost` varchar(50) NOT NULL COMMENT 'Judgehost that performed the judging',
  ADD CONSTRAINT `judging_ibfk_3` FOREIGN KEY (`judgehost`) REFERENCES `judgehost` (`hostname`);

DROP TABLE `rankcache_jury`, `rankcache_public`,`scorecache_jury`, `scorecache_public`;
