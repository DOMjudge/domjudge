-- These are some example/default entries for the DOMjudge database.
-- This assumes database name 'domjudge'
--
-- You can pipe this file into the 'mysql' command to insert this data.
--
-- $Id$

USE domjudge;

-- 
-- Dumping data for table `clarification`
-- 


-- 
-- Dumping data for table `contest`
-- 

INSERT INTO `contest` (`cid`, `contestname`, `starttime`, `lastscoreupdate`, `endtime`, `unfreezetime`) VALUES (1, 'Demo practice session', '2006-01-01 09:00:00', NULL, '2006-01-01 11:00:00', NULL);
INSERT INTO `contest` (`cid`, `contestname`, `starttime`, `lastscoreupdate`, `endtime`, `unfreezetime`) VALUES (2, 'Demo contest', '2006-01-01 12:00:00', '2008-01-01 16:00:00', '2008-01-01 17:00:00', '2008-01-01 17:30:00');

-- 
-- Dumping data for table `judger`
-- 

INSERT INTO `judger` (`judgerid`, `active`) VALUES ('judgehost1', 1);
INSERT INTO `judger` (`judgerid`, `active`) VALUES ('judgehost2', 1);
INSERT INTO `judger` (`judgerid`, `active`) VALUES ('sparehost', 0);

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
-- Dumping data for table `team`
-- 

INSERT INTO `team` (`login`, `name`, `categoryid`, `affilid`, `ipaddress`, `passwd`, `room`, `comments`, `teampage_first_visited`) VALUES ('domjudge', 'DOMjudge', 3, 'UU', '127.0.0.1', NULL, NULL, NULL, NULL);
INSERT INTO `team` (`login`, `name`, `categoryid`, `affilid`, `ipaddress`, `passwd`, `room`, `comments`, `teampage_first_visited`) VALUES ('team01', 'Some very cool teamname!', 1, NULL, NULL, MD5('mypassword'), NULL, NULL, NULL);

-- 
-- Dumping data for table `team_affiliation`
-- 

INSERT INTO `team_affiliation` (`affilid`, `name`, `country`, `has_logo`, `comments`) VALUES ('UU', 'Utrecht University', 'NL', 0, NULL);

-- 
-- Dumping data for table `team_category`
-- 

INSERT INTO `team_category` (`categoryid`, `name`, `sortorder`, `color`) VALUES (1, 'Participants', 0, NULL);
INSERT INTO `team_category` (`categoryid`, `name`, `sortorder`, `color`) VALUES (2, 'Observers', 1, "#FFFFCC");
INSERT INTO `team_category` (`categoryid`, `name`, `sortorder`, `color`) VALUES (3, 'Organisation', 1, "#FFCCFF");
