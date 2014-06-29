-- This script upgrades table structure, data, and privileges
-- from/to the exact version numbers specified in the filename.

--
-- First execute a check whether this upgrade should apply. The check
-- below should fail if this upgrade has already been applied, but
-- keep everything unchanged if not.
--

-- @UPGRADE-CHECK@
SELECT 1;

--
-- Create additional structures
--

--
-- Transfer data from old to new structure
--

--
-- Add/remove sample/initial contents
--

UPDATE `configuration` SET `name` = 'script_timelimit', `description` = 'Maximum seconds available for compile/compare scripts. This is a safeguard against malicious code and buggy scripts, so a reasonable but large amount should do.' WHERE `name` = 'compile_time';
UPDATE `configuration` SET `name` = 'script_memory_limit', `description` = 'Maximum memory usage (in kB) by compile/compare scripts. This is a safeguard against malicious code and buggy script, so a reasonable but large amount should do.' WHERE `name` = 'compile_memory';
UPDATE `configuration` SET `name` = 'script_filesize', `description` = 'Maximum filesize (in kB) compile/compare scripts may write. Submission will fail with compiler-error when trying to write more, so this should be greater than any *intermediate* result written by compilers.' WHERE `name` = 'compile_filesize';

--
-- Finally remove obsolete structures after moving data
--

