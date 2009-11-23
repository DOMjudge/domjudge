-- This script upgrades table structure, data, and privileges
-- from/to the exact version numbers specified in the filename.

--
-- First create additional structures
--

ALTER TABLE `clarification`
  ADD COLUMN `probid` varchar(8) default NULL COMMENT 'Problem associated to this clarification' AFTER `recipient`;

ALTER TABLE `contest`
  ADD KEY `cid` (`cid`, `enabled`);

CREATE TABLE `judging_run` (
  `runid` int(4) unsigned NOT NULL auto_increment COMMENT 'Unique identifier',
  `judgingid` int(4) unsigned NOT NULL COMMENT 'Judging ID',
  `testcaseid` int(4) unsigned NOT NULL COMMENT 'Testcase ID',
  `runresult` varchar(25) default NULL COMMENT 'Result of this run, NULL if not finished yet',
  `output_run` text COMMENT 'Output of running the program',
  `output_diff` text COMMENT 'Diffing the program output and testcase output',
  `output_error` text COMMENT 'Standard error output of the program',
  PRIMARY KEY  (`runid`),
  UNIQUE KEY `testcaseid` (`judgingid`, `testcaseid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Result of a testcase run within a judging';

ALTER TABLE `testcase`
  CHANGE COLUMN `id` `testcaseid` int(4) unsigned NOT NULL auto_increment COMMENT 'Unique identifier',
  ADD COLUMN `rank` int(4) NOT NULL COMMENT 'Determines order of the testcases in judging',
  DROP PRIMARY KEY,
  ADD PRIMARY KEY (`testcaseid`);

--
-- Add/remove privileges
--

GRANT SELECT (langid, name, extension, allow_submit) ON language TO domjudge_plugin;

FLUSH PRIVILEGES;

--
-- Now transfer data from old to new structure
--

INSERT INTO `judging_run`
        (judgingid, testcaseid, runresult, output_run, output_diff, output_error)
  SELECT judgingid, testcaseid,    result, output_run, output_diff, output_error
  FROM `judging`
  LEFT JOIN `submission` USING (submitid)
  LEFT JOIN `testcase` USING (probid);

--
-- Finally remove obsolete structures after moving data
--

ALTER TABLE `judging`
  DROP COLUMN `output_run`,
  DROP COLUMN `output_diff`,
  DROP COLUMN `output_error`;

ALTER TABLE `scoreboard_jury`
  DROP COLUMN `penalty`;

ALTER TABLE `scoreboard_public`
  DROP COLUMN `penalty`;
