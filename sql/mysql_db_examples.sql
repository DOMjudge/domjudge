-- These are some example/default entries for the DOMjudge database.
--
-- You can pipe this file into the 'mysql' command to insert this
-- data, but preferably use 'make install'. Database should be set
-- externally (e.g. to 'domjudge').
--
-- $Id$


-- 
-- Dumping data for table `clarification`
-- 


-- 
-- Dumping data for table `contest`
-- 

INSERT INTO `contest` (`cid`, `contestname`, `activatetime`, `starttime`, `freezetime`, `endtime`, `unfreezetime`) VALUES (1, 'Demo practice session', '2006-01-01 09:00:00', '2006-01-01 09:00:00', NULL, '2006-01-01 11:00:00', NULL);
INSERT INTO `contest` (`cid`, `contestname`, `activatetime`, `starttime`, `freezetime`, `endtime`, `unfreezetime`) VALUES (2, 'Demo contest', '2006-01-01 11:30:00', '2006-01-01 12:00:00', '2008-01-01 16:00:00', '2008-01-01 17:00:00', '2008-01-01 17:30:00');

-- 
-- Dumping data for table `judgehost`
-- 

INSERT INTO `judgehost` (`hostname`, `active`) VALUES ('judgehost1', 1);
INSERT INTO `judgehost` (`hostname`, `active`) VALUES ('judgehost2', 1);
INSERT INTO `judgehost` (`hostname`, `active`) VALUES ('sparehost', 0);

-- 
-- Dumping data for table `judging`
-- 


-- 
-- Dumping data for table `language`
-- 

INSERT INTO `language` (`langid`, `name`, `extension`, `allow_submit`, `allow_judge`, `time_factor`) VALUES ('c', 'C', 'c', 1, 1, 1);
INSERT INTO `language` (`langid`, `name`, `extension`, `allow_submit`, `allow_judge`, `time_factor`) VALUES ('cpp', 'C++', 'cpp', 1, 1, 1);
INSERT INTO `language` (`langid`, `name`, `extension`, `allow_submit`, `allow_judge`, `time_factor`) VALUES ('java', 'Java', 'java', 1, 1, 1.5);
INSERT INTO `language` (`langid`, `name`, `extension`, `allow_submit`, `allow_judge`, `time_factor`) VALUES ('pascal', 'Pascal', 'pas', 0, 1, 1);
INSERT INTO `language` (`langid`, `name`, `extension`, `allow_submit`, `allow_judge`, `time_factor`) VALUES ('haskell', 'Haskell', 'hs', 0, 1, 2);
INSERT INTO `language` (`langid`, `name`, `extension`, `allow_submit`, `allow_judge`, `time_factor`) VALUES ('perl', 'Perl', 'pl', 0, 1, 1);
INSERT INTO `language` (`langid`, `name`, `extension`, `allow_submit`, `allow_judge`, `time_factor`) VALUES ('bash', 'Bash', 'sh', 0, 1, 1);

-- 
-- Dumping data for table `problem`
-- 

INSERT INTO `problem` (`probid`, `cid`, `name`, `allow_submit`, `allow_judge`, `testdata`, `timelimit`, `special_run`, `special_compare`, `color`) VALUES ('hello', 2, 'Hello World', 1, 1, 'hello', 5, NULL, NULL, 'magenta');
INSERT INTO `problem` (`probid`, `cid`, `name`, `allow_submit`, `allow_judge`, `testdata`, `timelimit`, `special_run`, `special_compare`, `color`) VALUES ('fltcmp', 2, 'Float special compare test', 1, 1, 'fltcmp', 5, NULL, 'program.sh', 'yellow');

-- 
-- Dumping data for table `scoreboard_jury`
-- 


-- 
-- Dumping data for table `scoreboard_public`
-- 


-- 
-- Dumping data for table `submission`
-- 

-- 
-- Dumping data for table `team_affiliation`
-- 

INSERT INTO `team_affiliation` (`affilid`, `name`, `country`, `comments`) VALUES ('UU', 'Utrecht University', 'NL', NULL);

-- 
-- Dumping data for table `team_category`
-- 

INSERT INTO `team_category` (`categoryid`, `name`, `sortorder`, `color`, `visible`) VALUES (1, 'Participants', 0, NULL, 1);
INSERT INTO `team_category` (`categoryid`, `name`, `sortorder`, `color`, `visible`) VALUES (2, 'Observers', 1, "#ffcc33", 1);
INSERT INTO `team_category` (`categoryid`, `name`, `sortorder`, `color`, `visible`) VALUES (3, 'Organisation', 1, "#ff99cc", 0);


-- 
-- Dumping data for table `team`
-- 

INSERT INTO `team` (`login`, `name`, `categoryid`, `affilid`, `ipaddress`, `passwd`, `room`, `comments`, `teampage_first_visited`) VALUES ('domjudge', 'DOMjudge', 3, 'UU', '127.0.0.1', NULL, NULL, NULL, NULL);
INSERT INTO `team` (`login`, `name`, `categoryid`, `affilid`, `ipaddress`, `passwd`, `room`, `comments`, `teampage_first_visited`) VALUES ('team01', 'Some very cool teamname!', 1, NULL, NULL, MD5('mypassword'), NULL, NULL, NULL);

