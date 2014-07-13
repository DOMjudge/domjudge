-- This provides a sample contest setup for the DOMjudge database.
--
-- You can pipe this file into the 'mysql' command to insert this
-- data, but preferably use 'dj-setup-database'. Database should be set
-- externally (e.g. to 'domjudge').

-- 
-- Dumping data for table `contest`
-- 

INSERT INTO `contest` (`cid`, `contestname`, `activatetime`, `starttime`, `freezetime`, `endtime`, `unfreezetime`, `activatetime_string`, `starttime_string`, `freezetime_string`, `endtime_string`, `unfreezetime_string`, `enabled`) VALUES
(1, 'Demo practice session', UNIX_TIMESTAMP('2014-01-01 08:00:00'), UNIX_TIMESTAMP('2014-01-01 09:00:00'), NULL, UNIX_TIMESTAMP('2014-01-01 11:00:00'), NULL, '-1:00', '2014-01-01 09:00:00', NULL, '+2:00', NULL, 1),
(2, 'Demo contest', UNIX_TIMESTAMP('2014-01-01 11:30:00'), UNIX_TIMESTAMP('2014-01-01 12:00:00'), UNIX_TIMESTAMP('2016-01-01 16:00:00'), UNIX_TIMESTAMP('2016-01-01 17:00:00'), UNIX_TIMESTAMP('2016-01-01 17:30:00'), '-00:30', '2014-01-01 12:00:00', '2016-01-01 16:00:00', '2016-01-01 17:00:00', '2016-01-01 17:30:00', 1);

--
-- Dumping data for table `executable`
--

INSERT INTO `executable` (`execid`, `description`, `type`) VALUES
('boolfind_cmp', 'boolfind comparator', 'compare'),
('boolfind_run', 'boolfind run script', 'run');

-- 
-- Dumping data for table `judgehost`
-- 

INSERT INTO `judgehost` (`hostname`, `active`) VALUES ('example-judgehost1', 0);

-- 
-- Dumping data for table `problem`
-- 

INSERT INTO `problem` (`probid`, `shortname`, `cid`, `name`, `allow_submit`, `allow_judge`, `timelimit`, `special_run`, `special_compare`, `color`) VALUES (1, 'hello', 2, 'Hello World', 1, 1, 5, NULL, NULL, 'magenta');
INSERT INTO `problem` (`probid`, `shortname`, `cid`, `name`, `allow_submit`, `allow_judge`, `timelimit`, `special_run`, `special_compare`, `color`) VALUES (2, 'fltcmp', 2, 'Float special compare test', 1, 1, 5, NULL, 'float', 'yellow');
INSERT INTO `problem` (`probid`, `shortname`, `cid`, `name`, `allow_submit`, `allow_judge`, `timelimit`, `special_run`, `special_compare`, `color`) VALUES (3, 'boolfind', 2, 'Boolean switch search', 1, 1, 5, 'boolfind_run', 'boolfind_cmp', 'limegreen');

-- 
-- Dumping data for table `team_affiliation`
-- 

INSERT INTO `team_affiliation` (`shortname`, `name`, `country`, `comments`) VALUES ('UU', 'Utrecht University', 'NLD', NULL);

-- 
-- Dumping data for table `team_category`
-- 

INSERT INTO `team_category` (`categoryid`, `name`, `sortorder`, `color`, `visible`) VALUES (2, 'Participants', 0, NULL, 1);
INSERT INTO `team_category` (`categoryid`, `name`, `sortorder`, `color`, `visible`) VALUES (3, 'Observers', 1, "#ffcc33", 1);
INSERT INTO `team_category` (`categoryid`, `name`, `sortorder`, `color`, `visible`) VALUES (4, 'Organisation', 1, "#ff99cc", 0);


-- 
-- Dumping data for table `team`
-- 

INSERT INTO `team` (`teamid`, `name`, `categoryid`, `affilid`, `hostname`, `room`, `comments`, `teampage_first_visited`) VALUES (2, 'Example teamname', 2, 1, NULL, NULL, NULL, NULL);

-- 
-- Dumping data for table `testcase`
-- 

INSERT INTO `testcase` (`testcaseid`, `md5sum_input`, `md5sum_output`, `input`, `output`, `probid`, `rank`, `description`) VALUES (1, 'b026324c6904b2a9cb4b88d6d61c81d1', '59ca0efa9f5633cb0371bbc0355478d8', 0x310a, 0x48656c6c6f20776f726c64210a, 1, 1, NULL);
INSERT INTO `testcase` (`testcaseid`, `md5sum_input`, `md5sum_output`, `input`, `output`, `probid`, `rank`, `description`) VALUES (2, '9b05c566cf4d373cd23ffe75787c1f6d', '0b93bf53346750cc7e04c02f31443721', 0x330a312e300a3245300a330a, 0x312e300a302e35303030303030303030310a332e333333333333333333452d310a, 2, 1, 'Different floating formats');
INSERT INTO `testcase` (`testcaseid`, `md5sum_input`, `md5sum_output`, `input`, `output`, `probid`, `rank`, `description`) VALUES (3, 'a94c7fc1f4dac435f6fc5d5d4c7ba173', '2c266fa701a6034e02d928331d5bd4ef', 0x320a342e303030303030303030303030300a352e303030303030303030303030310a, 0x302e32350a32452d310a, 2, 2, 'High precision inputs');
INSERT INTO `testcase` (`testcaseid`, `md5sum_input`, `md5sum_output`, `input`, `output`, `probid`, `rank`, `description`) VALUES (4, 'fc157fa74267ba846e8ddc9c0747ad53', 'd38340056cc41e311beae85f906d7f24', 0x330a2b300a496e660a6e616e0a, 0x696e660a300a4e614e0a, 2, 3, 'Inf/NaN checks');
INSERT INTO `testcase` (`testcaseid`, `md5sum_input`, `md5sum_output`, `input`, `output`, `probid`, `rank`, `description`) VALUES (5, '90864a8759427d63b40f1f5f75e32308', '6267776644f5bd2bf0edccf5a210e087', 0x310a350a310a310a300a310a300a, 0x4f555450555420310a, 3, 1, NULL);
