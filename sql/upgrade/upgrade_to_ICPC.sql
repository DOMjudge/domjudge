-- This script upgrades table structure, data, and privileges
-- from/to the exact version numbers specified in the filename.

--
-- First execute a check whether this upgrade should apply. The check
-- below should fail if this upgrade has already been applied, but
-- keep everything unchanged if not.
--

-- @UPGRADE-CHECK@
ALTER TABLE `contest` ADD  COLUMN `finalizetime` int(4) default NULL;
ALTER TABLE `contest` DROP COLUMN `finalizetime`;

--
-- Create additional structures
--

ALTER TABLE contest
  ADD COLUMN `starttime_enabled` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT 'If disabled, starttime is not used, e.g. to delay contest start' AFTER `enabled`,
  ADD COLUMN `finalizetime` decimal(32,9) unsigned DEFAULT NULL COMMENT 'Time when contest was finalized, null if not yet' AFTER `starttime_enabled`,
  ADD COLUMN `finalizecomment` text COMMENT 'Comments by the finalizer' AFTER `finalizetime`,
  ADD COLUMN `b` smallint(3) unsigned NOT NULL default '0' COMMENT 'Number of extra bronze medals' AFTER `finalizecomment`;

ALTER TABLE `submission`
  ADD COLUMN `externalid` varchar(255) DEFAULT NULL COMMENT 'Specifies ID of submission if imported from external CCS, e.g. Kattis',
  ADD COLUMN `externalresult` varchar(32) DEFAULT NULL COMMENT 'Result string as returned from external CCS, e.g. Kattis',
  ADD UNIQUE KEY `externalid` (`cid`,`externalid`(190));

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

INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES
('clar_answers', '["No comment","Read the problem statement carefully"]', 'array_val', 'List of predefined clarification answers');

UPDATE `configuration` SET `value` = '60' WHERE `name` = 'script_timelimit';
UPDATE `configuration` SET `value` = '1'  WHERE `name` = 'show_pending';
UPDATE `configuration` SET `value` = '0'  WHERE `name` = 'compile_penalty';
UPDATE `configuration` SET `value` = '0'  WHERE `name` = 'lazy_eval_results';
UPDATE `configuration` SET `value` = '{"memory-limit":99,"output-limit":99,"run-error":99,"timelimit":99,"wrong-answer":99,"no-output":99,"correct":1}' WHERE `name` = 'results_prio';
UPDATE `configuration` SET `value` = '1 ' WHERE `name` = 'show_limits_on_team_page';


--
-- Finally remove obsolete structures after moving data
--
