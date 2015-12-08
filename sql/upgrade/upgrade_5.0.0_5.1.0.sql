-- This script upgrades table structure, data, and privileges
-- from/to the exact version numbers specified in the filename.

--
-- First execute a check whether this upgrade should apply. The check
-- below should fail if this upgrade has already been applied, but
-- keep everything unchanged if not.
--

-- @UPGRADE-CHECK@
ALTER TABLE `team` DROP KEY `name`;
ALTER TABLE `team` ADD UNIQUE KEY `name` (`name`(190));

-- Before any upgrades, update from utf8 (3-byte character) to utf8mb4
-- full UTF-8 unicode support. This requires MySQL/MariaDB >= 5.5.3.
-- Comment out to disable this change.
source upgrade/convert_to_utf8mb4_5.0.sql

--
-- Create additional structures
--

-- Set allow_submit default to 1
ALTER TABLE `contestproblem`
  MODIFY COLUMN `allow_submit` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT 'Are submissions accepted for this problem?';

-- Support longer contest time strings to include microseconds
ALTER TABLE `contest`
  MODIFY COLUMN `activatetime_string` varchar(64) NOT NULL COMMENT 'Authoritative absolute or relative string representation of activatetime',
  MODIFY COLUMN `starttime_string` varchar(64) NOT NULL COMMENT 'Authoritative absolute (only!) string representation of starttime',
  MODIFY COLUMN `freezetime_string` varchar(64) DEFAULT NULL COMMENT 'Authoritative absolute or relative string representation of freezetime',
  MODIFY COLUMN `endtime_string` varchar(64) NOT NULL COMMENT 'Authoritative absolute or relative string representation of endtime',
  MODIFY COLUMN `unfreezetime_string` varchar(64) DEFAULT NULL COMMENT 'Authoritative absolute or relative string representation of unfreezetrime',
  MODIFY COLUMN `deactivatetime_string` varchar(64) DEFAULT NULL COMMENT 'Authoritative absolute or relative string representation of deactivatetime';

-- Drop unique key on team name
ALTER TABLE `team` DROP KEY `name`;

-- Remove obsolete comment that langid is used for source filenames
ALTER TABLE `language`
  MODIFY COLUMN `langid` varchar(8) NOT NULL COMMENT 'Unique ID (string)';

-- Add extra field for clarification category (alternative to probid)
ALTER TABLE `clarification`
  ADD COLUMN `category` varchar(128) DEFAULT NULL COMMENT 'Category associated to this clarification; only set for non problem clars' AFTER `probid`;

-- Add field in submissions table to store expected results
ALTER TABLE `submission`
  ADD COLUMN `expected_results` varchar(255) DEFAULT NULL COMMENT 'JSON encoded list of expected results - used to validate jury submissions' AFTER `rejudgingid`;

--
-- Transfer data from old to new structure
--

-- Add timezones to the contest time strings. We cannot detect the
-- real timezone intended, only the offset, so we add Etc/GMT-+X
-- "timezones. Note that the sign is opposite to normal!
SET @old_time_zone = @@session.time_zone;
SET time_zone = '+00:00';

UPDATE `contest`
  SET `activatetime_string` = CONCAT(`activatetime_string`,' Etc/GMT',
    IF(`activatetime`-UNIX_TIMESTAMP(`activatetime_string`)>=0,'+',''),
    ROUND((`activatetime`-UNIX_TIMESTAMP(`activatetime_string`))/3600))
  WHERE `activatetime_string` NOT LIKE '-%';

UPDATE `contest`
  SET `starttime_string` = CONCAT(`starttime_string`,' Etc/GMT',
    IF(`starttime`-UNIX_TIMESTAMP(`starttime_string`)>=0,'+',''),
    ROUND((`starttime`-UNIX_TIMESTAMP(`starttime_string`))/3600));

UPDATE `contest`
  SET `freezetime_string` = CONCAT(`freezetime_string`,' Etc/GMT',
    IF(`freezetime`-UNIX_TIMESTAMP(`freezetime_string`)>=0,'+',''),
    ROUND((`freezetime`-UNIX_TIMESTAMP(`freezetime_string`))/3600))
  WHERE `freezetime_string` NOT LIKE '+%';

UPDATE `contest`
  SET `endtime_string` = CONCAT(`endtime_string`,' Etc/GMT',
    IF(`endtime`-UNIX_TIMESTAMP(`endtime_string`)>=0,'+',''),
    ROUND((`endtime`-UNIX_TIMESTAMP(`endtime_string`))/3600))
  WHERE `endtime_string` NOT LIKE '+%';

UPDATE `contest`
  SET `unfreezetime_string` = CONCAT(`unfreezetime_string`,' Etc/GMT',
    IF(`unfreezetime`-UNIX_TIMESTAMP(`unfreezetime_string`)>=0,'+',''),
    ROUND((`unfreezetime`-UNIX_TIMESTAMP(`unfreezetime_string`))/3600))
  WHERE `unfreezetime_string` NOT LIKE '+%';

UPDATE `contest`
  SET `deactivatetime_string` = CONCAT(`deactivatetime_string`,' Etc/GMT',
    IF(`deactivatetime`-UNIX_TIMESTAMP(`deactivatetime_string`)>=0,'+',''),
    ROUND((`deactivatetime`-UNIX_TIMESTAMP(`deactivatetime_string`))/3600))
  WHERE `deactivatetime_string` NOT LIKE '+%';

SET time_zone = @old_time_zone;

--
-- Add/remove sample/initial contents
--

-- Add default clarification categories to configuration settings
INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES
('clar_categories', '{"general":"General issue","tech":"Technical issue"}', 'array_keyval', 'List of additional clarification categories');

--
-- Finally remove obsolete structures after moving data
--

