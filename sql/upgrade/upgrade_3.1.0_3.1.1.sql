-- This script upgrades table structure, data, and privileges
-- from/to the exact version numbers specified in the filename.

--
-- First execute a check whether this upgrade should apply. The check
-- below should fail if this upgrade has already been applied, but
-- keep everything unchanged if not.
--

-- @UPGRADE-CHECK@
ALTER TABLE `language` ADD UNIQUE KEY `extension` (`extension`);
ALTER TABLE `language` DROP KEY `extension`;

--
-- Create additional structures
--

ALTER TABLE `language` ADD UNIQUE KEY `extension` (`extension`);

--
-- Add/remove privileges
--

FLUSH PRIVILEGES;

--
-- Transfer data from old to new structure
--

--
-- Add/remove sample/initial contents
--

--
-- Finally remove obsolete structures after moving data
--

