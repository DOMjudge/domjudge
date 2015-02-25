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
  ADD COLUMN `finalizetime` decimal(32,9) unsigned DEFAULT NULL COMMENT 'Time when contest was finalized, null if not yet',
  ADD COLUMN `finalizecomment` text COMMENT 'Comments by the finalizer',
  ADD COLUMN `b` smallint(3) unsigned NOT NULL default '0' COMMENT 'Number of extra bronze medals';

-- Drop constraint before changing data
ALTER TABLE `clarification`
  DROP FOREIGN KEY `clarification_ibfk_3`,
  MODIFY COLUMN `probid` varchar(128) default NULL COMMENT 'Problem or category associated to this clarification';

ALTER TABLE `submission`
  ADD COLUMN `externalid` int(4) unsigned default NULL COMMENT 'Specifies ID of submission if imported from external CCS, e.g. Kattis',
  ADD COLUMN `externalresult` varchar(25) default NULL COMMENT 'Result string as returned from external CCS, e.g. Kattis',
  ADD UNIQUE KEY `externalid` (`externalid`);

CREATE TABLE `removed_interval` (
  `intervalid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
  `cid` int(4) unsigned NOT NULL COMMENT 'Contest ID',
  `starttime` decimal(32,9) unsigned NOT NULL COMMENT 'Initial time of removed interval',
  `endtime` decimal(32,9) unsigned NOT NULL COMMENT 'Final time of removed interval',
  PRIMARY KEY (`intervalid`),
  KEY `cid` (`cid`),
  CONSTRAINT `removed_interval_ibfk_1` FOREIGN KEY (`cid`) REFERENCES `contest` (`cid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Time intervals removed from the contest for scoring';

--
-- Transfer data from old to new structure
--

--
-- Add/remove sample/initial contents
--

INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('clar_answers', '["No comment","Read the problem statement carefully"]', 'array_val', 'List of predefined clarification answers');
INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('clar_categories', '{general:"General issue",tech:"Technical issue"}', 'array_keyval', 'List of additional clarification categories');

--
-- Finally remove obsolete structures after moving data
--
