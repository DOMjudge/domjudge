-- This script upgrades table structure, data, and privileges
-- from/to the exact version numbers specified in the filename.

--
-- First execute a check whether this upgrade should apply. The check
-- below should fail if this upgrade has already been applied, but
-- keep everything unchanged if not.
--

-- @UPGRADE-CHECK@
INSERT INTO `language` (`langid`, `name`) VALUES ('kt', 'Kotlin');
DELETE FROM `language` WHERE `langid` = 'kt';

--
-- Create additional structures
--


--
-- Transfer data from old to new structure
--

--
-- Add/remove sample/initial contents
--

INSERT INTO `configuration` (`name`, `value`, `type`, `description`) VALUES
('score_in_seconds', '0', 'bool', 'Should the scoreboard resolution be measured in seconds instead of minutes?'),
('show_teams_submissions', '1', 'bool', 'Show problem columns with submission information on the public and team scoreboards?');

INSERT INTO `language` (`langid`, `name`, `extensions`, `allow_submit`, `allow_judge`, `time_factor`, `compile_script`) VALUES
('kt', 'Kotlin', '["kt"]', 0, 1, 1, 'kt');

--
-- Finally remove obsolete structures after moving data
--

