-- This provides a sample contest setup for the DOMjudge database.
--
-- You can pipe this file into the 'mysql' command to insert this
-- data, but preferably use 'dj_setup_database'. Database should be set
-- externally (e.g. to 'domjudge').

/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;

--
-- Dumping data for table `clarification`
--

INSERT INTO `clarification` (`clarid`, `externalid`, `cid`, `respid`, `submittime`, `sender`, `recipient`, `jury_member`, `probid`, `category`, `body`, `answered`) VALUES
(1, NULL, 2, NULL, '1518385638.901348000', 2, NULL, 'admin', 1, NULL, 'Can you tell me how to solve this problem?', 1),
(2, NULL, 2, 1, '1518385677.689197000', NULL, 2, 'admin', 1, NULL, '> Can you tell me how to solve this problem?\r\n\r\nNo, read the problem statement.', 1);

--
-- Dumping data for table `contest`
--

-- Temporarily use UTC as timezone, since UNIX_TIMESTAMP() uses the system timezone.
SET @old_time_zone = @@session.time_zone;
SET time_zone = '+00:00';

INSERT INTO `contest` (`cid`, `name`, `shortname`, `activatetime`, `starttime`, `freezetime`, `endtime`, `unfreezetime`, `deactivatetime`, `activatetime_string`, `starttime_string`, `freezetime_string`, `endtime_string`, `unfreezetime_string`, `deactivatetime_string`, `enabled`, `process_balloons`, `public`) VALUES
(1, 'Demo practice session', 'demoprac', UNIX_TIMESTAMP(CONCAT(YEAR(NOW()), '-01-01 08:00:00')), UNIX_TIMESTAMP(CONCAT(YEAR(NOW()),'-01-01 09:00:00')), NULL, UNIX_TIMESTAMP(CONCAT(YEAR(NOW()),'-01-01 11:00:00')), NULL, UNIX_TIMESTAMP(CONCAT(YEAR(NOW()),'-01-01 15:00:00')), '-1:00', CONCAT(YEAR(NOW()),'-01-01 09:00:00 Europe/Amsterdam'), NULL, '+2:00', NULL, '+6:00', 1, 1, 0),
(2, 'Demo contest', 'demo', UNIX_TIMESTAMP(CONCAT(YEAR(NOW()),'-01-01 10:29:59.877')), UNIX_TIMESTAMP(CONCAT(YEAR(NOW()),'-01-01 11:00:00')), UNIX_TIMESTAMP(CONCAT(YEAR(NOW())+2,'-01-01 15:00:00')), UNIX_TIMESTAMP(CONCAT(YEAR(NOW())+2,'-01-01 16:00:00')), UNIX_TIMESTAMP(CONCAT(YEAR(NOW())+2,'-01-01 16:30:00')), UNIX_TIMESTAMP(CONCAT(YEAR(NOW())+2,'-01-01 17:30:00')), '-00:30:00.123', CONCAT(YEAR(NOW()),'-01-01 12:00:00 Europe/Amsterdam'), CONCAT(YEAR(NOW())+2,'-01-01 16:00:00 Europe/Amsterdam'), CONCAT(YEAR(NOW())+2,'-01-01 17:00:00 Europe/Amsterdam'), CONCAT(YEAR(NOW())+2,'-01-01 17:30:00 Europe/Amsterdam'), CONCAT(YEAR(NOW())+2,'-01-01 18:30:00 Europe/Amsterdam'), 1, 1, 1);

SET time_zone = @old_time_zone;

--
-- Dumping data for table `contestproblem`
--

INSERT INTO `contestproblem` (`cid`, `probid`, `shortname`, `allow_submit`, `allow_judge`, `color`) VALUES
(1, 1, 'hello', 1, 1, NULL),
(2, 1, 'hello', 1, 1, 'magenta'),
(2, 2, 'fltcmp', 1, 1, 'yellow'),
(2, 3, 'boolfind', 1, 1, 'limegreen');

--
-- Dumping data for table `contestteam`
--

INSERT INTO `contestteam` (`cid`, `teamid`) VALUES
(1, 2);

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

INSERT INTO `problem` (`probid`, `externalid`, `name`, `timelimit`, `special_run`, `special_compare`, `special_compare_args`) VALUES
(1, 'hello', 'Hello World', 5, NULL, NULL, NULL),
(2, 'fltcmp', 'Float special compare test', 5, NULL, 'compare', 'float_tolerance 1E-6'),
(3, 'boolfind', 'Boolean switch search', 5, 'boolfind_run', 'boolfind_cmp', NULL);

--
-- Dumping data for table `team_affiliation`
--

INSERT INTO `team_affiliation` (`externalid`, `shortname`, `name`, `country`, `comments`) VALUES
('utrecht', 'UU', 'Utrecht University', 'NLD', NULL);

--
-- Dumping data for table `team_category`
--

INSERT INTO `team_category` (`categoryid`, `name`, `sortorder`, `color`, `visible`) VALUES
(3, 'Participants', 0, NULL, 1),
(4, 'Observers', 1, "#ffcc33", 1),
(5, 'Organisation', 1, "#ff99cc", 0);

--
-- Dumping data for table `team`
--

INSERT INTO `team` (`teamid`, `externalid`, `name`, `categoryid`, `affilid`, `hostname`, `room`, `comments`, `teampage_first_visited`) VALUES
(2, 'exteam', 'Example teamname', 3, 1, NULL, NULL, NULL, NULL);

--
-- Dumping data for table `testcase`
--

INSERT INTO `testcase` (`testcaseid`, `md5sum_input`, `md5sum_output`, `input`, `output`, `probid`, `rank`, `description`) VALUES
(1, 'b026324c6904b2a9cb4b88d6d61c81d1', '59ca0efa9f5633cb0371bbc0355478d8', 0x310a, 0x48656c6c6f20776f726c64210a, 1, 1, NULL),
(2, '9b05c566cf4d373cd23ffe75787c1f6d', '0b93bf53346750cc7e04c02f31443721', 0x330a312e300a3245300a330a, 0x312e300a302e35303030303030303030310a332e333333333333333333452d310a, 2, 1, 'Different floating formats'),
(3, 'a94c7fc1f4dac435f6fc5d5d4c7ba173', '2c266fa701a6034e02d928331d5bd4ef', 0x320a342e303030303030303030303030300a352e303030303030303030303030310a, 0x302e32350a32452d310a, 2, 2, 'High precision inputs'),
(4, 'fc157fa74267ba846e8ddc9c0747ad53', 'd38340056cc41e311beae85f906d7f24', 0x330a2b300a496e660a6e616e0a, 0x696e660a300a4e614e0a, 2, 3, 'Inf/NaN checks'),
(5, '90864a8759427d63b40f1f5f75e32308', '6267776644f5bd2bf0edccf5a210e087', 0x310a350a310a310a300a310a300a, 0x4f555450555420310a, 3, 1, NULL);

/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
