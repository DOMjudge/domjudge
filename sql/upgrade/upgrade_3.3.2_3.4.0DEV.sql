-- This script upgrades table structure, data, and privileges
-- from/to the exact version numbers specified in the filename.

--
-- First execute a check whether this upgrade should apply. The check
-- below should fail if this upgrade has already been applied, but
-- keep everything unchanged if not.
--

-- @UPGRADE-CHECK@
ALTER TABLE `problem` ADD  COLUMN `problemtext` longblob;
ALTER TABLE `problem` DROP COLUMN `problemtext`;

--
-- Create additional structures
--

ALTER TABLE `problem`
  ADD COLUMN `problemtext` longblob COMMENT 'Problem text in HTML/PDF/ASCII' AFTER `color`;

ALTER TABLE `balloon`
  ADD KEY `submitid` (`submitid`),
  ADD FOREIGN KEY (`submitid`) REFERENCES `submission` (`submitid`) ON DELETE CASCADE;

--
-- Transfer data from old to new structure
--

--
-- Add/remove sample/initial contents
--

INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES ('enable_printing', '0', 'bool', 'Enable teams and jury to send source code to a printer via the DOMjudge web interface.');

--
-- Finally remove obsolete structures after moving data
--

