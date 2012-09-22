-- This script upgrades table structure, data, and privileges
-- from/to the exact version numbers specified in the filename.

--
-- First execute a check whether this upgrade should apply. The check
-- below should fail if this upgrade has already been applied, but
-- keep everything unchanged if not.
--

-- @UPGRADE-CHECK@
ALTER TABLE `problem` ADD  COLUMN `text` longblob;
ALTER TABLE `problem` DROP COLUMN `text`;

--
-- Create additional structures
--

ALTER TABLE `problem`
  ADD COLUMN `text` longblob COMMENT 'Problem text in HTML/PDF/ASCII' AFTER `color`;

--
-- Transfer data from old to new structure
--

--
-- Add/remove sample/initial contents
--

--
-- Finally remove obsolete structures after moving data
--

