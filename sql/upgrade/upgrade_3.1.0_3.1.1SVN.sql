-- This script upgrades table structure, data, and privileges
-- from/to the exact version numbers specified in the filename.

--
-- First execute a check whether this upgrade should apply. The check
-- below should fail if this upgrade has already been applied, but
-- keep everything unchanged if not.
--

-- @UPGRADE-CHECK@
SELECT '1';

--
-- Create additional structures
--

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

UPDATE `problem` SET `special_compare` = 'float' WHERE `special_compare` = 'program.sh';

--
-- Finally remove obsolete structures after moving data
--

