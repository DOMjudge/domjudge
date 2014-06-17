-- These are the database tables needed for DOMjudge.
--
-- You can pipe this file into the 'mysql' command to create the
-- database tables, but preferably use 'dj-setup-database'. Database
-- should be set externally (e.g. to 'domjudge').

/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;

--
-- Table structure for table `auditlog`
--
CREATE TABLE `auditlog` (
  `logid` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
  `logtime` decimal(32,9) unsigned NOT NULL COMMENT 'Timestamp of the logentry',
  `cid` int(4) unsigned DEFAULT NULL COMMENT 'Contest ID associated to this entry',
  `user` varchar(255) DEFAULT NULL COMMENT 'User who performed this action',
  `datatype` varchar(25) DEFAULT NULL COMMENT 'Reference to DB table associated to this entry',
  `dataid` varchar(50) DEFAULT NULL COMMENT 'Identifier in reference table',
  `action` varchar(30) DEFAULT NULL COMMENT 'Description of action performed',
  `extrainfo` varchar(255) DEFAULT NULL COMMENT 'Optional additional description of the entry',
  PRIMARY KEY (`logid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Log of all actions performed';

--
-- Table structure for table `balloon`
--
CREATE TABLE `balloon` (
  `balloonid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
  `submitid` int(4) unsigned NOT NULL COMMENT 'Submission for which balloon was earned',
  `done` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'Has been handed out yet?',
  PRIMARY KEY (`balloonid`),
  KEY `submitid` (`submitid`),
  CONSTRAINT `balloon_ibfk_1` FOREIGN KEY (`submitid`) REFERENCES `submission` (`submitid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Balloons to be handed out';

--
-- Table structure for table `clarification`
--
CREATE TABLE `clarification` (
  `clarid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
  `cid` int(4) unsigned NOT NULL COMMENT 'Contest ID',
  `respid` int(4) unsigned DEFAULT NULL COMMENT 'In reply to clarification ID',
  `submittime` decimal(32,9) unsigned NOT NULL COMMENT 'Time sent',
  `sender` int(4) unsigned DEFAULT NULL COMMENT 'Team ID, null means jury',
  `recipient` int(4) unsigned DEFAULT NULL COMMENT 'Team ID, null means to jury or to all',
  `jury_member` varchar(15) DEFAULT NULL COMMENT 'Name of jury member who answered this',
  `probid` int(4) unsigned DEFAULT NULL COMMENT 'Problem associated to this clarification',
  `body` longtext NOT NULL COMMENT 'Clarification text',
  `answered` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'Has been answered by jury?',
  PRIMARY KEY  (`clarid`),
  KEY `respid` (`respid`),
  KEY `probid` (`probid`),
  KEY `cid` (`cid`),
  KEY `cid_2` (`cid`,`answered`,`submittime`),
  CONSTRAINT `clarification_ibfk_1` FOREIGN KEY (`cid`) REFERENCES `contest` (`cid`) ON DELETE CASCADE,
  CONSTRAINT `clarification_ibfk_2` FOREIGN KEY (`respid`) REFERENCES `clarification` (`clarid`) ON DELETE SET NULL,
  CONSTRAINT `clarification_ibfk_3` FOREIGN KEY (`probid`) REFERENCES `problem` (`probid`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Clarification requests by teams and responses by the jury';

--
-- Table structure for table `configuration`
--

CREATE TABLE `configuration` (
  `configid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
  `name` varchar(25) NOT NULL COMMENT 'Name of the configuration variable',
  `value` longtext NOT NULL COMMENT 'Content of the configuration variable (JSON encoded)',
  `type` varchar(25) DEFAULT NULL COMMENT 'Type of the value (metatype for use in the webinterface)',
  `description` varchar(255) DEFAULT NULL COMMENT 'Description for in the webinterface',
  PRIMARY KEY (`configid`),
  KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Global configuration variables';

--
-- Table structure for table `contest`
--

CREATE TABLE `contest` (
  `cid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
  `contestname` varchar(255) NOT NULL COMMENT 'Descriptive name',
  `activatetime` decimal(32,9) unsigned NOT NULL COMMENT 'Time contest becomes visible in team/public views',
  `starttime` decimal(32,9) unsigned NOT NULL COMMENT 'Time contest starts, submissions accepted',
  `freezetime` decimal(32,9) unsigned DEFAULT NULL COMMENT 'Time scoreboard is frozen',
  `endtime` decimal(32,9) unsigned NOT NULL COMMENT 'Time after which no more submissions are accepted',
  `unfreezetime` decimal(32,9) unsigned DEFAULT NULL COMMENT 'Unfreeze a frozen scoreboard at this time',
  `activatetime_string` varchar(20) NOT NULL COMMENT 'Authoritative absolute or relative string representation of activatetime',
  `starttime_string` varchar(20) NOT NULL COMMENT 'Authoritative absolute (only!) string representation of starttime',
  `freezetime_string` varchar(20) DEFAULT NULL COMMENT 'Authoritative absolute or relative string representation of freezetime',
  `endtime_string` varchar(20) NOT NULL COMMENT 'Authoritative absolute or relative string representation of endtime',
  `unfreezetime_string` varchar(20) DEFAULT NULL COMMENT 'Authoritative absolute or relative string representation of unfreezetrime',
  `enabled` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT 'Whether this contest can be active',
  PRIMARY KEY (`cid`),
  KEY `cid` (`cid`,`enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Contests that will be run with this install';

--
-- Table structure for table `event`
--

CREATE TABLE `event` (
  `eventid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
  `eventtime` decimal(32,9) unsigned NOT NULL COMMENT 'When the event occurred',
  `cid` int(4) unsigned NOT NULL COMMENT 'Contest ID',
  `clarid` int(4) unsigned DEFAULT NULL COMMENT 'Clarification ID',
  `langid` varchar(8) DEFAULT NULL COMMENT 'Language ID',
  `probid` int(4) unsigned DEFAULT NULL COMMENT 'Problem ID',
  `submitid` int(4) unsigned DEFAULT NULL COMMENT 'Submission ID',
  `judgingid` int(4) unsigned DEFAULT NULL COMMENT 'Judging ID',
  `teamid` int(4) unsigned DEFAULT NULL COMMENT 'Team ID',
  `description` longtext NOT NULL COMMENT 'Event description',
  PRIMARY KEY  (`eventid`),
  KEY `cid` (`cid`),
  KEY `clarid` (`clarid`),
  KEY `langid` (`langid`),
  KEY `probid` (`probid`),
  KEY `submitid` (`submitid`),
  KEY `judgingid` (`judgingid`),
  KEY `teamid` (`teamid`),
  CONSTRAINT `event_ibfk_1` FOREIGN KEY (`cid`) REFERENCES `contest` (`cid`) ON DELETE CASCADE,
  CONSTRAINT `event_ibfk_2` FOREIGN KEY (`clarid`) REFERENCES `clarification` (`clarid`) ON DELETE CASCADE,
  CONSTRAINT `event_ibfk_3` FOREIGN KEY (`langid`) REFERENCES `language` (`langid`) ON DELETE CASCADE,
  CONSTRAINT `event_ibfk_4` FOREIGN KEY (`probid`) REFERENCES `problem` (`probid`) ON DELETE CASCADE,
  CONSTRAINT `event_ibfk_5` FOREIGN KEY (`submitid`) REFERENCES `submission` (`submitid`) ON DELETE CASCADE,
  CONSTRAINT `event_ibfk_6` FOREIGN KEY (`judgingid`) REFERENCES `judging` (`judgingid`) ON DELETE CASCADE,
  CONSTRAINT `event_ibfk_7` FOREIGN KEY (`teamid`) REFERENCES `team` (`teamid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Log of all events during a contest';

--
-- Table structure for table `executable`
--

CREATE TABLE `executable` (
  `execid` varchar(32) NOT NULL COMMENT 'Unique ID (string)',
  `md5sum` char(32) DEFAULT NULL COMMENT 'Md5sum of zip file',
  `zipfile` longblob COMMENT 'Zip file',
  `description` varchar(255) DEFAULT NULL COMMENT 'Description of this executable',
  `type` varchar(8) NOT NULL COMMENT 'Type of executable',
  PRIMARY KEY (`execid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Compile, compare, and run script executable bundles';

--
-- Table structure for table `judgehost`
--

CREATE TABLE `judgehost` (
  `hostname` varchar(50) NOT NULL COMMENT 'Resolvable hostname of judgehost',
  `active` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT 'Should this host take on judgings?',
  `polltime` decimal(32,9) unsigned DEFAULT NULL COMMENT 'Time of last poll by autojudger',
  PRIMARY KEY  (`hostname`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Hostnames of the autojudgers';

--
-- Table structure for table `judging`
--

CREATE TABLE `judging` (
  `judgingid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
  `cid` int(4) unsigned NOT NULL DEFAULT '0' COMMENT 'Contest ID',
  `submitid` int(4) unsigned NOT NULL COMMENT 'Submission ID being judged',
  `starttime` decimal(32,9) unsigned NOT NULL COMMENT 'Time judging started',
  `endtime` decimal(32,9) unsigned DEFAULT NULL COMMENT 'Time judging ended, null = still busy',
  `judgehost` varchar(50) DEFAULT NULL COMMENT 'Judgehost that performed the judging',
  `result` varchar(25) DEFAULT NULL COMMENT 'Result string as defined in config.php',
  `verified` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'Result verified by jury member?',
  `jury_member` varchar(15) DEFAULT NULL COMMENT 'Name of jury member who verified this',
  `verify_comment` varchar(255) DEFAULT NULL COMMENT 'Optional additional information provided by the verifier',
  `valid` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT 'Old judging is marked as invalid when rejudging',
  `output_compile` longblob COMMENT 'Output of the compiling the program',
  `seen` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'Whether the team has seen this judging',
  PRIMARY KEY  (`judgingid`),
  KEY `submitid` (`submitid`),
  KEY `judgehost` (`judgehost`),
  KEY `cid` (`cid`),
  CONSTRAINT `judging_ibfk_1` FOREIGN KEY (`cid`) REFERENCES `contest` (`cid`) ON DELETE CASCADE,
  CONSTRAINT `judging_ibfk_2` FOREIGN KEY (`submitid`) REFERENCES `submission` (`submitid`) ON DELETE CASCADE,
  CONSTRAINT `judging_ibfk_3` FOREIGN KEY (`judgehost`) REFERENCES `judgehost` (`hostname`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Result of judging a submission';

--
-- Table structure for table `judging_run`
--

CREATE TABLE `judging_run` (
  `runid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
  `judgingid` int(4) unsigned NOT NULL COMMENT 'Judging ID',
  `testcaseid` int(4) unsigned NOT NULL COMMENT 'Testcase ID',
  `runresult` varchar(25) DEFAULT NULL COMMENT 'Result of this run, NULL if not finished yet',
  `runtime` float DEFAULT NULL COMMENT 'Submission running time on this testcase',
  `output_run` longblob COMMENT 'Output of running the program',
  `output_diff` longblob COMMENT 'Diffing the program output and testcase output',
  `output_error` longblob COMMENT 'Standard error output of the program',
  `output_system` longblob COMMENT 'Judging system output',
  PRIMARY KEY  (`runid`),
  UNIQUE KEY `testcaseid` (`judgingid`, `testcaseid`),
  KEY `judgingid` (`judgingid`),
  KEY `testcaseid_2` (`testcaseid`),
  CONSTRAINT `judging_run_ibfk_1` FOREIGN KEY (`testcaseid`) REFERENCES `testcase` (`testcaseid`),
  CONSTRAINT `judging_run_ibfk_2` FOREIGN KEY (`judgingid`) REFERENCES `judging` (`judgingid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Result of a testcase run within a judging';

--
-- Table structure for table `language`
--

CREATE TABLE `language` (
  `langid` varchar(8) NOT NULL COMMENT 'Unique ID (string), used for source file extension',
  `name` varchar(255) NOT NULL COMMENT 'Descriptive language name',
  `extensions` longtext COMMENT 'List of recognized extensions (JSON encoded)',
  `allow_submit` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT 'Are submissions accepted in this language?',
  `allow_judge` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT 'Are submissions in this language judged?',
  `time_factor` float NOT NULL DEFAULT '1' COMMENT 'Language-specific factor multiplied by problem run times',
  `compile_script` varchar(32) DEFAULT NULL COMMENT 'Script to compile source code for this language',
  PRIMARY KEY  (`langid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Programming languages in which teams can submit solutions';

--
-- Table structure for table `problem`
--

CREATE TABLE `problem` (
  `probid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
  `shortname` varchar(8) NOT NULL COMMENT 'Unique ID (string)',
  `cid` int(4) unsigned NOT NULL COMMENT 'Contest ID',
  `name` varchar(255) NOT NULL COMMENT 'Descriptive name',
  `allow_submit` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'Are submissions accepted for this problem?',
  `allow_judge` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT 'Are submissions for this problem judged?',
  `timelimit` int(4) unsigned NOT NULL DEFAULT '0' COMMENT 'Maximum run time for this problem',
  `special_run` varchar(32) DEFAULT NULL COMMENT 'Script to run submissions for this problem',
  `special_compare` varchar(32) DEFAULT NULL COMMENT 'Script to compare problem and jury output for this problem',
  `color` varchar(25) DEFAULT NULL COMMENT 'Balloon colour to display on the scoreboard',
  `problemtext` longblob COMMENT 'Problem text in HTML/PDF/ASCII',
  `problemtext_type` varchar(4) DEFAULT NULL COMMENT 'File type of problem text',
  PRIMARY KEY  (`probid`),
  UNIQUE KEY `shortname` (`shortname`,`cid`),
  KEY `cid` (`cid`),
  CONSTRAINT `problem_ibfk_1` FOREIGN KEY (`cid`) REFERENCES `contest` (`cid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Problems the teams can submit solutions for';

--
-- Table structure for table `rankcache_jury`
--

CREATE TABLE `rankcache_jury` (
  `cid` int(4) unsigned NOT NULL COMMENT 'Contest ID',
  `teamid` int(4) unsigned NOT NULL COMMENT 'Team ID',
  `correct` int(4) unsigned NOT NULL DEFAULT '0' COMMENT 'Number of problems solved',
  `totaltime` int(4) unsigned NOT NULL DEFAULT '0' COMMENT 'Total time spent',
  PRIMARY KEY  (`cid`,`teamid`),
  KEY `order` (`cid`,`correct`, `totaltime`) USING BTREE,
  CONSTRAINT `rankcache_jury_ibfk_1` FOREIGN KEY (`cid`) REFERENCES `contest` (`cid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Rank cache (jury version)';

--
-- Table structure for table `rankcache_public`
--

CREATE TABLE `rankcache_public` (
  `cid` int(4) unsigned NOT NULL COMMENT 'Contest ID',
  `teamid` int(4) unsigned NOT NULL COMMENT 'Team ID',
  `correct` int(4) unsigned NOT NULL DEFAULT '0' COMMENT 'Number of problems solved',
  `totaltime` int(4) unsigned NOT NULL DEFAULT '0' COMMENT 'Total time spent',
  PRIMARY KEY  (`cid`,`teamid`),
  KEY `order` (`cid`,`correct`,`totaltime`) USING BTREE,
  CONSTRAINT `rankcache_public_ibfk_1` FOREIGN KEY (`cid`) REFERENCES `contest` (`cid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Rank cache (public/team version)';

--
-- Table structure for table `role`
--

CREATE TABLE `role` (
  `roleid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
  `role` varchar(15) NOT NULL COMMENT 'Role name',
  `description` varchar(255) NOT NULL COMMENT 'Description for the web interface',
  PRIMARY KEY (`roleid`),
  UNIQUE KEY `role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Possible user roles';

--
-- Table structure for table `scorecache_jury`
--

CREATE TABLE `scorecache_jury` (
  `cid` int(4) unsigned NOT NULL COMMENT 'Contest ID',
  `teamid` int(4) unsigned NOT NULL COMMENT 'Team ID',
  `probid` int(4) unsigned NOT NULL COMMENT 'Problem ID',
  `submissions` int(4) unsigned NOT NULL DEFAULT '0' COMMENT 'Number of submissions made',
  `pending` int(4) NOT NULL DEFAULT '0' COMMENT 'Number of submissions pending judgement',
  `totaltime` int(4) unsigned NOT NULL DEFAULT '0' COMMENT 'Total time spent',
  `is_correct` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'Has there been a correct submission?',
  PRIMARY KEY  (`cid`,`teamid`,`probid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Scoreboard cache (jury version)';

--
-- Table structure for table `scorecache_public`
--

CREATE TABLE `scorecache_public` (
  `cid` int(4) unsigned NOT NULL COMMENT 'Contest ID',
  `teamid` int(4) unsigned NOT NULL COMMENT 'Team ID',
  `probid` int(4) unsigned NOT NULL COMMENT 'Problem ID',
  `submissions` int(4) unsigned NOT NULL DEFAULT '0' COMMENT 'Number of submissions made',
  `pending` int(4) NOT NULL DEFAULT '0' COMMENT 'Number of submissions pending judgement',
  `totaltime` int(4) unsigned NOT NULL DEFAULT '0' COMMENT 'Total time spent',
  `is_correct` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'Has there been a correct submission?',
  PRIMARY KEY  (`cid`,`teamid`,`probid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Scoreboard cache (public/team version)';

--
-- Table structure for table `submission`
--

CREATE TABLE `submission` (
  `submitid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
  `origsubmitid` int(4) unsigned DEFAULT NULL COMMENT 'If set, specifies original submission in case of edit/resubmit',
  `cid` int(4) unsigned NOT NULL COMMENT 'Contest ID',
  `teamid` int(4) unsigned NOT NULL COMMENT 'Team ID',
  `probid` int(4) unsigned NOT NULL COMMENT 'Problem ID',
  `langid` varchar(8) NOT NULL COMMENT 'Language ID',
  `submittime` decimal(32,9) unsigned NOT NULL COMMENT 'Time submitted',
  `judgehost` varchar(50) DEFAULT NULL COMMENT 'Current/last judgehost judging this submission',
  `valid` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT 'If false ignore this submission in all scoreboard calculations',
  PRIMARY KEY  (`submitid`),
  KEY `teamid` (`cid`,`teamid`),
  KEY `judgehost` (`cid`,`judgehost`),
  KEY `teamid_2` (`teamid`),
  KEY `probid` (`probid`),
  KEY `langid` (`langid`),
  KEY `judgehost_2` (`judgehost`),
  KEY `origsubmitid` (`origsubmitid`),
  CONSTRAINT `submission_ibfk_1` FOREIGN KEY (`cid`) REFERENCES `contest` (`cid`) ON DELETE CASCADE,
  CONSTRAINT `submission_ibfk_2` FOREIGN KEY (`teamid`) REFERENCES `team` (`teamid`) ON DELETE CASCADE,
  CONSTRAINT `submission_ibfk_3` FOREIGN KEY (`probid`) REFERENCES `problem` (`probid`) ON DELETE CASCADE,
  CONSTRAINT `submission_ibfk_4` FOREIGN KEY (`langid`) REFERENCES `language` (`langid`) ON DELETE CASCADE,
  CONSTRAINT `submission_ibfk_5` FOREIGN KEY (`judgehost`) REFERENCES `judgehost` (`hostname`) ON DELETE SET NULL,
  CONSTRAINT `submission_ibfk_6` FOREIGN KEY (`origsubmitid`) REFERENCES `submission` (`submitid`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='All incoming submissions';

--
-- Table structure for table `submission_file`
--

CREATE TABLE `submission_file` (
  `submitfileid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
  `submitid` int(4) unsigned NOT NULL COMMENT 'Submission this file belongs to',
  `sourcecode` longblob NOT NULL COMMENT 'Full source code',
  `filename` varchar(255) NOT NULL COMMENT 'Filename as submitted',
  `rank` int(4) unsigned NOT NULL COMMENT 'Order of the submission files, zero-indexed',
  PRIMARY KEY (`submitfileid`),
  UNIQUE KEY `filename` (`submitid`,`filename`),
  UNIQUE KEY `rank` (`submitid`,`rank`),
  KEY `submitid` (`submitid`),
  CONSTRAINT `submission_file_ibfk_1` FOREIGN KEY (`submitid`) REFERENCES `submission` (`submitid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Files associated to a submission';

--
-- Table structure for table `team`
--

CREATE TABLE `team` (
  `teamid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
  `externalid` varchar(128) DEFAULT NULL COMMENT 'Team ID in an external system',
  `name` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL COMMENT 'Team name',
  `categoryid` int(4) unsigned NOT NULL DEFAULT '0' COMMENT 'Team category ID',
  `affilid` int(4) unsigned DEFAULT NULL COMMENT 'Team affiliation ID',
  `enabled` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT 'Whether the team is visible and operational',
  `members` longtext COMMENT 'Team member names (freeform)',
  `room` varchar(15) DEFAULT NULL COMMENT 'Physical location of team',
  `comments` longtext COMMENT 'Comments about this team',
  `judging_last_started` decimal(32,9) unsigned DEFAULT NULL COMMENT 'Start time of last judging for priorization',
  `teampage_first_visited` decimal(32,9) unsigned DEFAULT NULL COMMENT 'Time of first teampage view',
  `hostname` varchar(255) DEFAULT NULL COMMENT 'Teampage first visited from this address',
  PRIMARY KEY  (`teamid`),
  UNIQUE KEY `name` (`name`),
  KEY `affilid` (`affilid`),
  KEY `categoryid` (`categoryid`),
  KEY `externalid` (`externalid`),
  CONSTRAINT `team_ibfk_1` FOREIGN KEY (`categoryid`) REFERENCES `team_category` (`categoryid`) ON DELETE CASCADE,
  CONSTRAINT `team_ibfk_2` FOREIGN KEY (`affilid`) REFERENCES `team_affiliation` (`affilid`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='All teams participating in the contest';

--
-- Table structure for table `team_affiliation`
--

CREATE TABLE `team_affiliation` (
  `affilid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
  `shortname` varchar(30) NOT NULL COMMENT 'Short descriptive name',
  `name` varchar(255) NOT NULL COMMENT 'Descriptive name',
  `country` char(3) DEFAULT NULL COMMENT 'ISO 3166-1 alpha-3 country code',
  `comments` longtext COMMENT 'Comments',
  PRIMARY KEY  (`affilid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Affilitations for teams (e.g.: university, company)';

--
-- Table structure for table `team_category`
--

CREATE TABLE `team_category` (
  `categoryid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
  `name` varchar(255) NOT NULL COMMENT 'Descriptive name',
  `sortorder` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'Where to sort this category on the scoreboard',
  `color` varchar(25) DEFAULT NULL COMMENT 'Background colour on the scoreboard',
  `visible` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT 'Are teams in this category visible?',
  PRIMARY KEY  (`categoryid`),
  KEY `sortorder` (`sortorder`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Categories for teams (e.g.: participants, observers, ...)';

--
-- Table structure for table `team_unread`
--

CREATE TABLE `team_unread` (
  `teamid` int(4) unsigned NOT NULL COMMENT 'Team ID',
  `mesgid` int(4) unsigned NOT NULL COMMENT 'Clarification ID',
  PRIMARY KEY (`teamid`,`mesgid`),
  KEY `mesgid` (`mesgid`),
  CONSTRAINT `team_unread_ibfk_1` FOREIGN KEY (`teamid`) REFERENCES `team` (`teamid`) ON DELETE CASCADE,
  CONSTRAINT `team_unread_ibfk_2` FOREIGN KEY (`mesgid`) REFERENCES `clarification` (`clarid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='List of items a team has not viewed yet';

--
-- Table structure for table `testcase`
--

CREATE TABLE `testcase` (
  `testcaseid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
  `md5sum_input` char(32) DEFAULT NULL COMMENT 'Checksum of input data',
  `md5sum_output` char(32) DEFAULT NULL COMMENT 'Checksum of output data',
  `input` longblob COMMENT 'Input data',
  `output` longblob COMMENT 'Output data',
  `probid` int(4) unsigned NOT NULL COMMENT 'Corresponding problem ID',
  `rank` int(4) NOT NULL COMMENT 'Determines order of the testcases in judging',
  `description` varchar(255) DEFAULT NULL COMMENT 'Description of this testcase',
  `sample` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'Sample testcases that can be shared with teams',
  PRIMARY KEY  (`testcaseid`),
  UNIQUE KEY `rank` (`probid`,`rank`),
  KEY `probid` (`probid`),
  CONSTRAINT `testcase_ibfk_1` FOREIGN KEY (`probid`) REFERENCES `problem` (`probid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Stores testcases per problem';

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `userid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
  `username` varchar(255) NOT NULL COMMENT 'User login name',
  `name` varchar(255) NOT NULL COMMENT 'Name',
  `email` varchar(255) DEFAULT NULL COMMENT 'Email address',
  `last_login` decimal(32,9) unsigned DEFAULT NULL COMMENT 'Time of last successful login',
  `last_ip_address` varchar(255) DEFAULT NULL COMMENT 'Last IP address of successful login',
  `password` varchar(32) DEFAULT NULL COMMENT 'Password hash',
  `ip_address` varchar(255) DEFAULT NULL COMMENT 'IP Address used to autologin',
  `enabled` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT 'Whether the user is able to log in',
  `teamid` int(4) unsigned DEFAULT NULL COMMENT 'Team associated with',
  PRIMARY KEY (`userid`),
  UNIQUE KEY `username` (`username`),
  KEY `teamid` (`teamid`),
  CONSTRAINT `user_ibfk_1` FOREIGN KEY (`teamid`) REFERENCES `team` (`teamid`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Users that have access to DOMjudge';


--
-- Table structure for table `userrole`
--
CREATE TABLE `userrole` (
  `userid` int(4) unsigned NOT NULL COMMENT 'User ID',
  `roleid` int(4) unsigned NOT NULL COMMENT 'Role ID',
  PRIMARY KEY (`userid`, `roleid`),
  KEY `userid` (`userid`),
  KEY `roleid` (`roleid`),
  CONSTRAINT `userrole_ibfk_1` FOREIGN KEY (`userid`) REFERENCES `user` (`userid`) ON DELETE CASCADE,
  CONSTRAINT `userrole_ibfk_2` FOREIGN KEY (`roleid`) REFERENCES `role` (`roleid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Many-to-Many mapping of users and roles';

/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;

-- vim:tw=0:
