-- Create new event table columns:
ALTER TABLE `event`
  ADD COLUMN `endpointtype` varchar(32) NOT NULL COMMENT 'API endpoint associated to this entry' AFTER `cid`,
  ADD COLUMN `endpointid` varchar(64) NOT NULL COMMENT 'API endpoint (external) ID' AFTER `endpointtype`,
  ADD COLUMN `datatype` varchar(32) DEFAULT NULL COMMENT 'DB table associated to this entry' AFTER `endpointid`,
  ADD COLUMN `dataid` varchar(64) DEFAULT NULL COMMENT 'Identifier in reference DB table' AFTER `datatype`,
  ADD COLUMN `action` varchar(32) NOT NULL COMMENT 'Description of action performed' AFTER `dataid`,
  ADD COLUMN `content` longblob NOT NULL COMMENT 'JSON encoded content of the change, as provided in the event feed' AFTER `action`,
  DROP PRIMARY KEY,
  ADD PRIMARY KEY (`eventid`),
  ADD KEY `eventtime` (`cid`,`eventtime`);


-- Update old data the best we can:
UPDATE `event`
  SET `endpointtype` = 'clarifications', `endpointid` = `clarid`,
      `datatype`     = 'clarification',  `dataid`     = `clarid`,
      `action` = 'create', `content` = 'null'
  WHERE `description` = 'clarification';
UPDATE `event`
  SET `endpointtype` = 'submissions', `endpointid` = `submitid`,
      `datatype`     = 'submission',  `dataid`     = `submitid`,
      `action` = 'create', `content` = 'null'
  WHERE `description` = 'problem submitted';
UPDATE `event`
  SET `endpointtype` = 'judgings', `endpointid` = `judgingid`,
      `datatype`     = 'judging',  `dataid`     = `judgingid`,
      `action` = 'update', `content` = 'null'
  WHERE `description` = 'problem judged';

-- Drop old columns and indices:
ALTER TABLE `event`
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
