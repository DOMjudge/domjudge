ALTER TABLE `event`
  ADD COLUMN `datatype` varchar(25) NOT NULL COMMENT 'Reference to DB table associated to this entry' AFTER `cid`,
  ADD COLUMN `dataid` varchar(50) NOT NULL COMMENT 'Identifier in reference table' AFTER `datatype`,
  ADD COLUMN `action` varchar(30) NOT NULL COMMENT 'Description of action performed' AFTER `dataid`,
  ADD COLUMN `content` longblob NOT NULL COMMENT 'Cached JSON encoded content of the change, as provided in the event feed' AFTER `action`,
  DROP PRIMARY KEY,
  ADD PRIMARY KEY (`eventid`),
  ADD KEY `datatype` (`datatype`(16));

-- To make sure that all eventtimes are strictly increasing.
-- FIXME: MySQL does not allow sub-queries on the same table in UPDATE.
--UPDATE `event` AS `curr` (`eventtime`) VALUES
--  ( SELECT MAX(curr.eventtime,last.eventtime+0.001)
--    FROM `event` AS `last`
--    WHERE last.eventid < curr.eventid
--    ORDER BY last.eventid DESC LIMIT 1 );

UPDATE `event` SET `datatype` = 'clarification', `dataid` = `clarid`, `action` = 'create', `content` = 'null'
  WHERE `description` = 'clarification';
UPDATE `event` SET `datatype` = 'submission', `dataid` = `submitid`, `action` = 'create', `content` = 'null'
  WHERE `description` = 'problem submitted';
UPDATE `event` SET `datatype` = 'judging', `dataid` = `judgingid`, `action` = 'update', `content` = 'null'
  WHERE `description` = 'problem judged';

ALTER TABLE `event`
  ADD UNIQUE KEY `eventtime` (`cid`,`eventtime`),
  DROP FOREIGN KEY `event_ibfk_2`,
  DROP FOREIGN KEY `event_ibfk_3`,
  DROP FOREIGN KEY `event_ibfk_4`,
  DROP FOREIGN KEY `event_ibfk_5`,
  DROP FOREIGN KEY `event_ibfk_6`,
  DROP FOREIGN KEY `event_ibfk_7`,
  DROP COLUMN `clarid`,
  DROP COLUMN `langid`,
  DROP COLUMN `probid`,
  DROP COLUMN `submitid`,
  DROP COLUMN `judgingid`,
  DROP COLUMN `teamid`,
  DROP COLUMN `description`;
