-- This script upgrades table structure, data, and privileges
-- from/to the exact version numbers specified in the filename.

--
-- First execute a check whether this upgrade should apply. The check
-- below should fail if this upgrade has already been applied, but
-- keep everything unchanged if not.
--

-- @UPGRADE-CHECK@
SELECT '1'; -- No check available yet.

-- Before any upgrades, update from utf8 (3-byte character) to utf8mb4
-- full UTF-8 unicode support. This requires MySQL/MariaDB >= 5.5.3.
-- Comment out to disable this change.
source upgrade/convert_to_utf8mb4_5.0.sql
