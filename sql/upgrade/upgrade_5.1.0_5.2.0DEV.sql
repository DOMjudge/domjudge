-- This script upgrades table structure, data, and privileges
-- from/to the exact version numbers specified in the filename.

--
-- First execute a check whether this upgrade should apply. The check
-- below should fail if this upgrade has already been applied, but
-- keep everything unchanged if not.
--

-- @UPGRADE-CHECK@
SELECT '1'; -- No check possible yet.

--
-- Create additional structures
--

--
-- Transfer data from old to new structure
--

-- First assign a newly created judgehost to all judgings to guarantee
-- that we can add a constraint later.
REPLACE INTO `judgehost` (`hostname`, `active`) VALUES ('host-created-by-SQL-upgrade', '0');
UPDATE `judging` SET `judgehost` = 'host-created-by-SQL-upgrade' WHERE `judgehost` IS NULL;

--
-- Add/remove sample/initial contents
--

--
-- Finally remove obsolete structures after moving data
--

ALTER TABLE `judging`
  DROP FOREIGN KEY `judging_ibfk_3`;
ALTER TABLE `judging`
  MODIFY COLUMN `judgehost` varchar(50) NOT NULL COMMENT 'Judgehost that performed the judging',
  ADD CONSTRAINT `judging_ibfk_3` FOREIGN KEY (`judgehost`) REFERENCES `judgehost` (`hostname`);
