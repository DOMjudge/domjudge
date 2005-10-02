-- These are some example/default entries for the DOMjudge database.
-- This assumes database name 'domjudge'
--
-- You can pipe this file into the 'mysql' command to insert this data.
--
-- $Id$

USE domjudge;

-- 
-- Dumping data for table `category`
-- 

INSERT INTO `category` (`catid`, `name`) VALUES (1, 'Participants');
INSERT INTO `category` (`catid`, `name`) VALUES (2, 'Observers');
INSERT INTO `category` (`catid`, `name`) VALUES (3, 'Organisation');

-- 
-- Dumping data for table `clarification`
-- 


-- 
-- Dumping data for table `contest`
-- 

INSERT INTO `contest` (`cid`, `starttime`, `lastscoreupdate`, `endtime`, `contestname`) VALUES (1, '2005-01-01 12:00:00', '2007-01-01 16:00:00', '2007-01-01 17:00:00', 'Demo contest');

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
INSERT INTO `language` (`langid`, `name`, `extension`, `allow_submit`, `allow_judge`, `time_factor`) VALUES ('java', 'Java', 'java', 1, 1, 2.5);
INSERT INTO `language` (`langid`, `name`, `extension`, `allow_submit`, `allow_judge`, `time_factor`) VALUES ('pascal', 'Pascal', 'pas', 0, 1, 1);
INSERT INTO `language` (`langid`, `name`, `extension`, `allow_submit`, `allow_judge`, `time_factor`) VALUES ('haskell', 'Haskell', 'hs', 0, 1, 3);

-- 
-- Dumping data for table `problem`
-- 

INSERT INTO `problem` (`probid`, `cid`, `name`, `allow_submit`, `allow_judge`, `testdata`, `timelimit`) VALUES ('hello', 1, 'Hello World', 1, 1, 'hello', 5);

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

INSERT INTO `team` (`login`, `name`, `category`, `ipaddress`) VALUES ('domjudge', 'DOMjudge', 3, '127.0.0.1');
INSERT INTO `team` (`login`, `name`, `category`, `ipaddress`) VALUES ('team01', 'Some very cool teamname!', 1, '192.168.1.1');
