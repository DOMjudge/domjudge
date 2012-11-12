-- This script upgrades table structure, data, and privileges
-- from/to the exact version numbers specified in the filename.

--
-- First execute a check whether this upgrade should apply. The check
-- below should fail if this upgrade has already been applied, but
-- keep everything unchanged if not.
--

-- @UPGRADE-CHECK@
ALTER TABLE `balloon` ADD  KEY `submitid` (`submitid`);
ALTER TABLE `balloon` DROP KEY `submitid`;

--
-- Create additional structures
--

ALTER TABLE `balloon`
  ADD KEY `submitid` (`submitid`),
  ADD FOREIGN KEY (`submitid`) REFERENCES `submission` (`submitid`) ON DELETE CASCADE;

--
-- Transfer data from old to new structure
--

--
-- Add/remove sample/initial contents
--

--
-- Finally remove obsolete structures after moving data
--

