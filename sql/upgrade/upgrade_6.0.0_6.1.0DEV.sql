-- This script upgrades table structure, data, and privileges
-- from/to the exact version numbers specified in the filename.

--
-- First execute a check whether this upgrade should apply. The check
-- below should fail if this upgrade has already been applied, but
-- keep everything unchanged if not.
--

-- @UPGRADE-CHECK@
INSERT INTO `configuration` (`name`, `value`) VALUES ('data_source', '0');
DELETE FROM `configuration` WHERE `name` = 'data_source';

INSERT INTO `configuration` (`name`, `value`, `type`, `public`, `description`) VALUES
('data_source', '0', 'int', '0', 'Source of data. Choices: 0 = all local, 1 = configuration data external, 2 = configuration and live data external');
