-- This script upgrades table structure, data, and privileges
-- from/to the exact version numbers specified in the filename.

--
-- First execute a check whether this upgrade should apply. The check
-- below should fail if this upgrade has already been applied, but
-- keep everything unchanged if not.
--

-- @UPGRADE-CHECK@
INSERT INTO `problem` (`probid`, `cid`, `name`) VALUES ('boolfind', 2, 'testinsert');
DELETE FROM `problem` WHERE `probid` = 'boolfind';

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

INSERT INTO `problem` (`probid`, `cid`, `name`, `allow_submit`, `allow_judge`, `timelimit`, `special_run`, `special_compare`, `color`) VALUES ('boolfind', 2, 'Boolean switch search', 1, 1, 5, 'boolfind', 'boolfind', 'limegreen');

INSERT INTO `testcase` (`md5sum_input`, `md5sum_output`, `input`, `output`, `probid`, `rank`, `description`) VALUES ('90864a8759427d63b40f1f5f75e32308', '6267776644f5bd2bf0edccf5a210e087', 0x310a350a310a310a300a310a300a, 0x4f555450555420310a, 'boolfind', 1, NULL);

--
-- Finally remove obsolete structures after moving data
--

