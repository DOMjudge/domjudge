-- This script upgrades table structure, data, and privileges
-- from/to the exact version numbers specified in the filename.

--
-- First execute a check whether this upgrade should apply. The check
-- below should fail if this upgrade has already been applied, but
-- keep everything unchanged if not.
--

-- @UPGRADE-CHECK@
SELECT 1;
-- These upgrades can always be performed safely, and don't prevent future upgrades.

--
-- Create additional structures
--

ALTER TABLE `auditlog`
  MODIFY COLUMN `action` varchar(64) DEFAULT NULL COMMENT 'Description of action performed';

--
-- Transfer data from old to new structure
--

--
-- Add/remove sample/initial contents
--

--
-- Finally remove obsolete structures after moving data
--

