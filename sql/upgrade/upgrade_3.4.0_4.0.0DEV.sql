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
  ADD COLUMN `extensions` longtext DEFAULT NULL COMMENT 'List of recognized extensions (JSON encoded)' AFTER `name`;

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

--
-- Finally remove obsolete structures after moving data
--

