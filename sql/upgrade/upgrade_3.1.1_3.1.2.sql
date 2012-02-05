-- This script upgrades table structure, data, and privileges
-- from/to the exact version numbers specified in the filename.

--
-- First execute a check whether this upgrade should apply. The check
-- below should fail if this upgrade has already been applied, but
-- keep everything unchanged if not.
--

-- @UPGRADE-CHECK@
ALTER TABLE `contest` ADD  COLUMN `activatetime_string` varchar(20) NOT NULL;
ALTER TABLE `contest` DROP COLUMN `activatetime_string`;
-- This upgrade can always be applied without problems, but we still
-- check at least that our version < 3.2.

--
-- Create additional structures
--

--
-- Add/remove privileges
--

GRANT SELECT ON clarification TO `domjudge_plugin`;

GRANT SELECT (langid) ON submission TO `domjudge_public`, `domjudge_plugin`;

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

