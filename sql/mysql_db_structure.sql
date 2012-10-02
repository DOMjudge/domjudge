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
  `logtime` datetime NOT NULL COMMENT 'Timestamp of the logentry',
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
  PRIMARY KEY (`balloonid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Balloons to be handed out';

--
-- Table structure for table `clarification`
--
CREATE TABLE `clarification` (
  `clarid` int(4) unsigned NOT NULL auto_increment COMMENT 'Unique ID',
  `cid` int(4) unsigned NOT NULL COMMENT 'Contest ID',
  `respid` int(4) unsigned default NULL COMMENT 'In reply to clarification ID',
  `submittime` datetime NOT NULL COMMENT 'Time sent',
  `sender` varchar(15) default NULL COMMENT 'Team login, null means jury',
  `recipient` varchar(15) default NULL COMMENT 'Team login, null means to jury or to all',
  `jury_member` varchar(15) default NULL COMMENT 'Name of jury member who answered this',
  `probid` varchar(8) default NULL COMMENT 'Problem associated to this clarification',
  `body` longtext NOT NULL COMMENT 'Clarification text',
  `answered` tinyint(1) unsigned NOT NULL default '0' COMMENT 'Has been answered by jury?',
  PRIMARY KEY  (`clarid`),
  KEY `cid` (`cid`,`answered`,`submittime`),
  KEY `respid` (`respid`),
  KEY `probid` (`probid`),
  CONSTRAINT `clarification_ibfk_1` FOREIGN KEY (`cid`) REFERENCES `contest` (`cid`) ON DELETE CASCADE,
  CONSTRAINT `clarification_ibfk_2` FOREIGN KEY (`respid`) REFERENCES `clarification` (`clarid`) ON DELETE SET NULL,
  CONSTRAINT `clarification_ibfk_3` FOREIGN KEY (`probid`) REFERENCES `problem` (`probid`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Clarification requests by teams and responses by the jury';

--
-- Table structure for table `configuration`
--

CREATE TABLE `configuration` (
  `configid` int(4) NOT NULL AUTO_INCREMENT COMMENT 'Configuration ID',
  `name` varchar(25) NOT NULL COMMENT 'Name of the configuration variable',
  `value` longtext NOT NULL COMMENT 'Content of the configuration variable',
  `type` varchar(25) default NULL COMMENT 'Type of the value (metatype for use in the webinterface)',
  `description` varchar(255) default NULL COMMENT 'Description for in the webinterface',
  PRIMARY KEY (`configid`),
  KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Global configuration variables';


--
-- Table structure for table `contest`
--

CREATE TABLE `contest` (
  `cid` int(4) unsigned NOT NULL auto_increment COMMENT 'Contest ID',
  `contestname` varchar(255) NOT NULL COMMENT 'Descriptive name',
  `activatetime` datetime NOT NULL COMMENT 'Time contest becomes visible in team/public views',
  `starttime` datetime NOT NULL COMMENT 'Time contest starts, submissions accepted',
  `freezetime` datetime default NULL COMMENT 'Time scoreboard is frozen',
  `endtime` datetime NOT NULL COMMENT 'Time after which no more submissions are accepted',
  `unfreezetime` datetime default NULL COMMENT 'Unfreeze a frozen scoreboard at this time',
  `activatetime_string` varchar(20) NOT NULL COMMENT 'Authoritative absolute or relative string representation of activatetime',
  `freezetime_string` varchar(20) default NULL COMMENT 'Authoritative absolute or relative string representation of freezetime',
  `endtime_string` varchar(20) NOT NULL COMMENT 'Authoritative absolute or relative string representation of endtime',
  `unfreezetime_string` varchar(20) default NULL COMMENT 'Authoritative absolute or relative string representation of unfreezetrime',
  `enabled` tinyint(1) unsigned NOT NULL default '1' COMMENT 'Whether this contest can be active',
  PRIMARY KEY (`cid`),
  KEY `cid` (`cid`,`enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Contests that will be run with this install';

--
-- Table structure for table `event`
--

CREATE TABLE `event` (
  `eventid` int(4) unsigned NOT NULL auto_increment COMMENT 'Unique ID',
  `eventtime` datetime NOT NULL COMMENT 'When the event occurred',
  `cid` int(4) unsigned NOT NULL COMMENT 'Contest ID',
  `clarid` int(4) unsigned default NULL COMMENT 'Clarification ID',
  `langid` varchar(8) default NULL COMMENT 'Language ID',
  `probid` varchar(8) default NULL COMMENT 'Problem ID',
  `submitid` int(4) unsigned default NULL COMMENT 'Submission ID',
  `judgingid` int(4) unsigned default NULL COMMENT 'Judging ID',
  `teamid` varchar(15) default NULL COMMENT 'Team login',
  `description` longtext NOT NULL COMMENT 'Event description',
  PRIMARY KEY  (`eventid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Log of all events during a contest';

--
-- Table structure for table `judgehost`
--

CREATE TABLE `judgehost` (
  `hostname` varchar(50) NOT NULL COMMENT 'Resolvable hostname of judgehost',
  `active` tinyint(1) unsigned NOT NULL default '1' COMMENT 'Should this host take on judgings?',
  `polltime` datetime default NULL COMMENT 'Time of last poll by autojudger',
  PRIMARY KEY  (`hostname`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Hostnames of the autojudgers';

--
-- Table structure for table `judging`
--

CREATE TABLE `judging` (
  `judgingid` int(4) unsigned NOT NULL auto_increment COMMENT 'Unique ID',
  `cid` int(4) unsigned NOT NULL default '0' COMMENT 'Contest ID',
  `submitid` int(4) unsigned NOT NULL COMMENT 'Submission ID being judged',
  `starttime` datetime NOT NULL COMMENT 'Time judging started',
  `endtime` datetime default NULL COMMENT 'Time judging ended, null = still busy',
  `judgehost` varchar(50) default NULL COMMENT 'Judgehost that performed the judging',
  `result` varchar(25) default NULL COMMENT 'Result string as defined in config.php',
  `verified` tinyint(1) unsigned NOT NULL default '0' COMMENT 'Result verified by jury member?',
  `jury_member` varchar(15) default NULL COMMENT 'Name of jury member who verified this',
  `verify_comment` varchar(255) DEFAULT NULL COMMENT 'Optional additional information provided by the verifier',
  `valid` tinyint(1) unsigned NOT NULL default '1' COMMENT 'Old judging is marked as invalid when rejudging',
  `output_compile` longblob COMMENT 'Output of the compiling the program',
  `seen` tinyint(1) unsigned NOT NULL default '0' COMMENT 'Whether the team has seen this judging',
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
  `runid` int(4) unsigned NOT NULL auto_increment COMMENT 'Unique identifier',
  `judgingid` int(4) unsigned NOT NULL COMMENT 'Judging ID',
  `testcaseid` int(4) unsigned NOT NULL COMMENT 'Testcase ID',
  `runresult` varchar(25) default NULL COMMENT 'Result of this run, NULL if not finished yet',
  `runtime` float DEFAULT NULL COMMENT 'Submission running time on this testcase',
  `output_run` longblob COMMENT 'Output of running the program',
  `output_diff` longblob COMMENT 'Diffing the program output and testcase output',
  `output_error` longblob COMMENT 'Standard error output of the program',
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
  `allow_submit` tinyint(1) unsigned NOT NULL default '1' COMMENT 'Are submissions accepted in this language?',
  `allow_judge` tinyint(1) unsigned NOT NULL default '1' COMMENT 'Are submissions in this language judged?',
  `time_factor` float NOT NULL default '1' COMMENT 'Language-specific factor multiplied by problem run times',
  PRIMARY KEY  (`langid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Programming languages in which teams can submit solutions';

--
-- Table structure for table `problem`
--

CREATE TABLE `problem` (
  `probid` varchar(8) NOT NULL COMMENT 'Unique ID (string)',
  `cid` int(4) unsigned NOT NULL COMMENT 'Contest ID',
  `name` varchar(255) NOT NULL COMMENT 'Descriptive name',
  `allow_submit` tinyint(1) unsigned NOT NULL default '0' COMMENT 'Are submissions accepted for this problem?',
  `allow_judge` tinyint(1) unsigned NOT NULL default '1' COMMENT 'Are submissions for this problem judged?',
  `timelimit` int(4) unsigned NOT NULL default '0' COMMENT 'Maximum run time for this problem',
  `special_run` varchar(25) default NULL COMMENT 'Script to run submissions for this problem',
  `special_compare` varchar(25) default NULL COMMENT 'Script to compare problem and jury output for this problem',
  `color` varchar(25) default NULL COMMENT 'Balloon colour to display on the scoreboard',
  `problemtext` longblob COMMENT 'Problem text in HTML/PDF/ASCII',
  PRIMARY KEY  (`probid`),
  KEY `cid` (`cid`),
  CONSTRAINT `problem_ibfk_1` FOREIGN KEY (`cid`) REFERENCES `contest` (`cid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Problems the teams can submit solutions for';

--
-- Table structure for table `scoreboard_jury`
--

CREATE TABLE `scoreboard_jury` (
  `cid` int(4) unsigned NOT NULL COMMENT 'Contest ID',
  `teamid` varchar(15) NOT NULL COMMENT 'Team login',
  `probid` varchar(8) NOT NULL COMMENT 'Problem ID',
  `submissions` int(4) unsigned NOT NULL default '0' COMMENT 'Number of submissions made',
  `pending` int(4) NOT NULL default '0' COMMENT 'Number of submissions pending judgement',
  `totaltime` int(4) unsigned NOT NULL default '0' COMMENT 'Total time spent',
  `is_correct` tinyint(1) unsigned NOT NULL default '0' COMMENT 'Has there been a correct submission?',
  PRIMARY KEY  (`cid`,`teamid`,`probid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Scoreboard cache (jury version)';

--
-- Table structure for table `scoreboard_public`
--

CREATE TABLE `scoreboard_public` (
  `cid` int(4) unsigned NOT NULL COMMENT 'Contest ID',
  `teamid` varchar(15) NOT NULL COMMENT 'Team login',
  `probid` varchar(8) NOT NULL COMMENT 'Problem ID',
  `submissions` int(4) unsigned NOT NULL default '0' COMMENT 'Number of submissions made',
  `pending` int(4) NOT NULL default '0' COMMENT 'Number of submissions pending judgement',
  `totaltime` int(4) unsigned NOT NULL default '0' COMMENT 'Total time spent',
  `is_correct` tinyint(1) unsigned NOT NULL default '0' COMMENT 'Has there been a correct submission?',
  PRIMARY KEY  (`cid`,`teamid`,`probid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Scoreboard cache (public/team version)';

--
-- Table structure for table `submission`
--

CREATE TABLE `submission` (
  `submitid` int(4) unsigned NOT NULL auto_increment COMMENT 'Unique ID',
  `origsubmitid` int(4) unsigned default NULL COMMENT 'If set, specifies original submission in case of edit/resubmit',
  `cid` int(4) unsigned NOT NULL COMMENT 'Contest ID',
  `teamid` varchar(15) NOT NULL COMMENT 'Team login',
  `probid` varchar(8) NOT NULL COMMENT 'Problem ID',
  `langid` varchar(8) NOT NULL COMMENT 'Language ID',
  `submittime` datetime NOT NULL COMMENT 'Time submitted',
  `judgehost` varchar(50) default NULL COMMENT 'Current/last judgehost judging this submission',
  `judgemark` varchar(255) default NULL COMMENT 'Unique identifier for taking a submission by a judgehost',
  `valid` tinyint(1) unsigned NOT NULL default '1' COMMENT 'If false ignore this submission in all scoreboard calculations',
  PRIMARY KEY  (`submitid`),
  UNIQUE KEY `judgemark` (`judgemark`),
  KEY `teamid` (`cid`,`teamid`),
  KEY `judgehost` (`cid`,`judgehost`),
  KEY `teamid_2` (`teamid`),
  KEY `probid` (`probid`),
  KEY `langid` (`langid`),
  KEY `judgehost_2` (`judgehost`),
  KEY `origsubmitid` (`origsubmitid`),
  CONSTRAINT `submission_ibfk_1` FOREIGN KEY (`cid`) REFERENCES `contest` (`cid`) ON DELETE CASCADE,
  CONSTRAINT `submission_ibfk_2` FOREIGN KEY (`teamid`) REFERENCES `team` (`login`) ON DELETE CASCADE,
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
  `login` varchar(15) NOT NULL COMMENT 'Team login name',
  `name` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL COMMENT 'Team name',
  `categoryid` int(4) unsigned NOT NULL default '0' COMMENT 'Team category ID',
  `affilid` varchar(10) default NULL COMMENT 'Team affiliation ID',
  `authtoken` varchar(255) default NULL COMMENT 'Identifying token for this team',
  `enabled` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT 'Whether the team is visible and operational',
  `members` longtext COMMENT 'Team member names (freeform)',
  `room` varchar(15) default NULL COMMENT 'Physical location of team',
  `comments` longtext COMMENT 'Comments about this team',
  `judging_last_started` datetime default NULL COMMENT 'Start time of last judging for priorization',
  `teampage_first_visited` datetime default NULL COMMENT 'Time of first teampage view',
  `hostname` varchar(255) default NULL COMMENT 'Teampage first visited from this address',
  PRIMARY KEY  (`login`),
  UNIQUE KEY `name` (`name`),
  KEY `affilid` (`affilid`),
  KEY `categoryid` (`categoryid`),
  CONSTRAINT `team_ibfk_1` FOREIGN KEY (`categoryid`) REFERENCES `team_category` (`categoryid`) ON DELETE CASCADE,
  CONSTRAINT `team_ibfk_2` FOREIGN KEY (`affilid`) REFERENCES `team_affiliation` (`affilid`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='All teams participating in the contest';

--
-- Table structure for table `team_affiliation`
--

CREATE TABLE `team_affiliation` (
  `affilid` varchar(10) NOT NULL COMMENT 'Unique ID',
  `name` varchar(255) NOT NULL COMMENT 'Descriptive name',
  `country` char(3) default NULL COMMENT 'ISO 3166-1 alpha-3 country code',
  `comments` longtext COMMENT 'Comments',
  PRIMARY KEY  (`affilid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Affilitations for teams (e.g.: university, company)';

--
-- Table structure for table `team_category`
--

CREATE TABLE `team_category` (
  `categoryid` int(4) unsigned NOT NULL auto_increment COMMENT 'Unique ID',
  `name` varchar(255) NOT NULL COMMENT 'Descriptive name',
  `sortorder` tinyint(1) unsigned NOT NULL default '0' COMMENT 'Where to sort this category on the scoreboard',
  `color` varchar(25) default NULL COMMENT 'Background colour on the scoreboard',
  `visible` tinyint(1) unsigned NOT NULL default '1' COMMENT 'Are teams in this category visible?',
  PRIMARY KEY  (`categoryid`),
  KEY `sortorder` (`sortorder`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Categories for teams (e.g.: participants, observers, ...)';

--
-- Table structure for table `team_unread`
--

CREATE TABLE `team_unread` (
  `teamid` varchar(15) NOT NULL default '' COMMENT 'Team login',
  `mesgid` int(4) unsigned NOT NULL default '0' COMMENT 'Clarification ID',
  `type` varchar(25) NOT NULL default 'clarification' COMMENT 'Type of message (now always "clarification")',
  PRIMARY KEY (`teamid`,`type`,`mesgid`),
  KEY `mesgid` (`mesgid`),
  CONSTRAINT `team_unread_ibfk_1` FOREIGN KEY (`teamid`) REFERENCES `team` (`login`) ON DELETE CASCADE,
  CONSTRAINT `team_unread_ibfk_2` FOREIGN KEY (`mesgid`) REFERENCES `clarification` (`clarid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='List of items a team has not viewed yet';

--
-- Table structure for table `testcase`
--

CREATE TABLE `testcase` (
  `testcaseid` int(4) unsigned NOT NULL auto_increment COMMENT 'Unique identifier',
  `md5sum_input` char(32) default NULL COMMENT 'Checksum of input data',
  `md5sum_output` char(32) default NULL COMMENT 'Checksum of output data',
  `input` longblob COMMENT 'Input data',
  `output` longblob COMMENT 'Output data',
  `probid` varchar(8) NOT NULL COMMENT 'Corresponding problem ID',
  `rank` int(4) NOT NULL COMMENT 'Determines order of the testcases in judging',
  `description` varchar(255) default NULL COMMENT 'Description of this testcase',
  PRIMARY KEY  (`testcaseid`),
  UNIQUE KEY `rank` (`probid`,`rank`),
  KEY `probid` (`probid`),
  CONSTRAINT `testcase_ibfk_1` FOREIGN KEY (`probid`) REFERENCES `problem` (`probid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Stores testcases per problem';

/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;

-- vim:tw=0:
