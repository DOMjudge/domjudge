-- This script upgrades table structure, data, and privileges
-- from/to the exact version numbers specified in the filename.

--
-- First execute a check whether this upgrade should apply. The check
-- below should fail if this upgrade has already been applied, but
-- keep everything unchanged if not.
--

-- @UPGRADE-CHECK@
ALTER TABLE `team` ADD  COLUMN `penalty` int(4) default NULL;
ALTER TABLE `team` DROP COLUMN `penalty`;

--
-- Create additional structures
--

ALTER TABLE contest
  ADD `finalizetime` DATETIME NULL COMMENT 'Time when contest was finalized, null if not yet',
  ADD `finalizecomment` TEXT NULL COMMENT 'Comments by the finalizer',
  ADD `b` smallint(3) unsigned NOT NULL default '0' COMMENT 'Number of extra bronze medals';

-- Drop constraint before changing data
ALTER TABLE `clarification`
  MODIFY COLUMN `probid` varchar(8) default NULL COMMENT 'Problem or category associated to this clarification',
  DROP FOREIGN KEY `clarification_ibfk_3`;

ALTER TABLE `team`
  ADD COLUMN `penalty` int(4) NOT NULL default '0' COMMENT 'Additional penalty time in minutes' AFTER `hostname`,
  ADD COLUMN `externalid` int(4) unsigned default NULL COMMENT 'Specifies ID of team if imported from external source';

ALTER TABLE `submission`
  ADD COLUMN `externalid` int(4) unsigned default NULL COMMENT 'Specifies ID of submission if imported from external CCS, e.g. Kattis',
  ADD COLUMN `externalresult` varchar(25) default NULL COMMENT 'Result string as returned from external CCS, e.g. Kattis';

CREATE TABLE `removed_interval` (
  `intervalid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
  `cid` int(4) unsigned NOT NULL COMMENT 'Contest ID',
  `starttime` datetime NOT NULL COMMENT 'Initial time of removed interval',
  `endtime` datetime NOT NULL COMMENT 'Final time of removed interval',
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
INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('clar_categories', '{"general":"General issue","technical":"Technical issue"}', 'array_keyval', 'List of additional clarification categories');

--
-- Finally remove obsolete structures after moving data
--
