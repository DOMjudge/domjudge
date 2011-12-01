-- This script upgrades table structure, data, and privileges
-- from/to the exact version numbers specified in the filename.

--
-- First execute a check whether this upgrade should apply. The check
-- below should fail if this upgrade has already been applied, but
-- keep everything unchanged if not.
--

-- @UPGRADE-CHECK@
ALTER TABLE `clarification` ADD  COLUMN `probid` varchar(8) default NULL;
ALTER TABLE `clarification` DROP COLUMN `probid`;

--
-- Create additional structures
--

ALTER TABLE `clarification`
  ADD COLUMN `probid` varchar(8) default NULL COMMENT 'Problem associated to this clarification' AFTER `recipient`;

ALTER TABLE `contest`
  ADD COLUMN `enabled` tinyint(1) unsigned NOT NULL default '1' COMMENT 'Whether this contest can be active',
  ADD KEY `cid` (`cid`, `enabled`);

CREATE TABLE `judging_run` (
  `runid` int(4) unsigned NOT NULL auto_increment COMMENT 'Unique identifier',
  `judgingid` int(4) unsigned NOT NULL COMMENT 'Judging ID',
  `testcaseid` int(4) unsigned NOT NULL COMMENT 'Testcase ID',
  `runresult` varchar(25) default NULL COMMENT 'Result of this run, NULL if not finished yet',
  `runtime` float DEFAULT NULL COMMENT 'Submission running time on this testcase',
  `output_run` text COMMENT 'Output of running the program',
  `output_diff` text COMMENT 'Diffing the program output and testcase output',
  `output_error` text COMMENT 'Standard error output of the program',
  PRIMARY KEY  (`runid`),
  UNIQUE KEY `testcaseid` (`judgingid`, `testcaseid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Result of a testcase run within a judging';

ALTER TABLE `team`
  CHANGE COLUMN `passwd` `authtoken` varchar(255) default NULL COMMENT 'Identifying token for this team',
  CHANGE COLUMN `hostname` `hostname` varchar(255) default NULL COMMENT 'Teampage first visited from this address' AFTER `teampage_first_visited`;

ALTER TABLE `testcase`
  CHANGE COLUMN `id` `testcaseid` int(4) unsigned NOT NULL auto_increment COMMENT 'Unique identifier',
  ADD COLUMN `rank` int(4) NOT NULL COMMENT 'Determines order of the testcases in judging' AFTER `probid`,
  DROP PRIMARY KEY,
  ADD PRIMARY KEY (`testcaseid`),
  ADD UNIQUE KEY `rank` (`probid`,`rank`);

--
-- Add/remove privileges
--

GRANT SELECT (langid, name, extension, allow_submit) ON language TO `domjudge_plugin`;
REVOKE UPDATE (ipaddress) ON team FROM `domjudge_team`;
GRANT  UPDATE (authtoken) ON team TO   `domjudge_team`;

FLUSH PRIVILEGES;

--
-- Transfer data from old to new structure
--

INSERT INTO `judging_run`
        (judgingid, testcaseid, runresult, output_run, output_diff, output_error)
  SELECT judgingid, testcaseid,    result, output_run, output_diff, output_error
  FROM `judging`
  LEFT JOIN `submission` USING (submitid)
  LEFT JOIN `testcase` USING (probid);

--
-- Add/remove sample/initial contents
--

INSERT INTO `language` (`langid`, `name`, `extension`, `allow_submit`, `allow_judge`, `time_factor`) VALUES ('cs', 'C#', 'cs', 0, 1, 1);
INSERT INTO `language` (`langid`, `name`, `extension`, `allow_submit`, `allow_judge`, `time_factor`) VALUES ('awk', 'AWK', 'awk', 0, 1, 1);
INSERT INTO `language` (`langid`, `name`, `extension`, `allow_submit`, `allow_judge`, `time_factor`) VALUES ('python', 'Python', 'py', 0, 1, 1);

-- Updates to testcases for fltcmp: cannot remove old single testcase,
-- because that might violate foreign key constraints and also cannot
-- specify testcaseid auto_increment keys.
INSERT INTO `testcase` (`md5sum_input`, `md5sum_output`, `input`, `output`, `probid`, `rank`, `description`) VALUES ('9b05c566cf4d373cd23ffe75787c1f6d', '0b93bf53346750cc7e04c02f31443721', 0x330a312e300a3245300a330a, 0x312e300a302e35303030303030303030310a332e333333333333333333452d310a, 'fltcmp', 1, 'Different floating formats');
INSERT INTO `testcase` (`md5sum_input`, `md5sum_output`, `input`, `output`, `probid`, `rank`, `description`) VALUES ('a94c7fc1f4dac435f6fc5d5d4c7ba173', '2c266fa701a6034e02d928331d5bd4ef', 0x320a342e303030303030303030303030300a352e303030303030303030303030310a, 0x302e32350a32452d310a, 'fltcmp', 2, 'High precision inputs');
INSERT INTO `testcase` (`md5sum_input`, `md5sum_output`, `input`, `output`, `probid`, `rank`, `description`) VALUES ('fc157fa74267ba846e8ddc9c0747ad53', 'd38340056cc41e311beae85f906d7f24', 0x330a2b300a496e660a6e616e0a, 0x696e660a300a4e614e0a, 'fltcmp', 3, 'Inf/NaN checks');

--
-- Finally remove obsolete structures after moving data
--

ALTER TABLE `judging`
  DROP COLUMN `output_run`,
  DROP COLUMN `output_diff`,
  DROP COLUMN `output_error`;

ALTER TABLE `team`
  DROP COLUMN `ipaddress`;

ALTER TABLE `scoreboard_jury`
  DROP COLUMN `penalty`;

ALTER TABLE `scoreboard_public`
  DROP COLUMN `penalty`;
