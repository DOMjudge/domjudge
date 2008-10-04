-- These are the database tables needed for DOMjudge.
--
-- You can pipe this file into the 'mysql' command to create the
-- database tables, but preferably use 'make install'. Database should
-- be set externally (e.g. to 'domjudge').
--
-- $Id$


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
  `body` text NOT NULL COMMENT 'Clarification text',
  `answered` tinyint(1) unsigned NOT NULL default '0' COMMENT 'Has been answered by jury?',
  PRIMARY KEY  (`clarid`),
  KEY `cid` (`cid`,`answered`,`submittime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Clarification requests by teams and responses by the jury';

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
  PRIMARY KEY  (`cid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Contests that will be run with this install';

-- 
-- Table structure for table `event`
-- 

CREATE TABLE `event` (
  `eventid` int(4) unsigned NOT NULL auto_increment COMMENT 'Unique ID',
  `eventtime` timestamp NOT NULL default CURRENT_TIMESTAMP COMMENT 'When the event occurred',
  `cid` int(4) unsigned NOT NULL COMMENT 'Contest ID',
  `clarid` int(4) unsigned default NULL COMMENT 'Clarification ID',
  `langid` varchar(8) default NULL COMMENT 'Language ID',
  `probid` varchar(8) default NULL COMMENT 'Problem ID',
  `submitid` int(4) unsigned default NULL COMMENT 'Submission ID',
  `judgingid` int(4) unsigned default NULL COMMENT 'Judging ID',
  `teamid` varchar(15) default NULL COMMENT 'Team login',
  `description` text NOT NULL COMMENT 'Event description',
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
  `judgehost` varchar(50) NOT NULL COMMENT 'Judgehost that performed the judging',
  `result` varchar(25) default NULL COMMENT 'Result string as defined in config.php',
  `verified` tinyint(1) unsigned NOT NULL default '0' COMMENT 'Result verified by jury member?',
  `verifier` varchar(15) NOT NULL default '' COMMENT 'Name of jury member who verified this',
  `valid` tinyint(1) unsigned NOT NULL default '1' COMMENT 'Old judging is marked as invalid when rejudging',
  `output_compile` text COMMENT 'Output of the compiling the program',
  `output_run` text COMMENT 'Output of running the program',
  `output_diff` text COMMENT 'Diffing the program output and testcase output',
  `output_error` text COMMENT 'Standard error output of the program',
  PRIMARY KEY  (`judgingid`),
  KEY `submitid` (`submitid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Result of judging a submission';

-- 
-- Table structure for table `language`
-- 

CREATE TABLE `language` (
  `langid` varchar(8) NOT NULL COMMENT 'Unique ID (string)',
  `name` varchar(255) NOT NULL COMMENT 'Descriptive language name',
  `extension` varchar(5) NOT NULL COMMENT 'Filename extension for this language',
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
  PRIMARY KEY  (`probid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Problems the teams can submit solutions for';

-- 
-- Table structure for table `scoreboard_jury`
-- 

CREATE TABLE `scoreboard_jury` (
  `cid` int(4) unsigned NOT NULL COMMENT 'Contest ID',
  `teamid` varchar(15) NOT NULL COMMENT 'Team login',
  `probid` varchar(8) NOT NULL COMMENT 'Problem ID',
  `submissions` int(4) unsigned NOT NULL default '0' COMMENT 'Number of submissions made',
  `totaltime` int(4) unsigned NOT NULL default '0' COMMENT 'Total time spent',
  `penalty` int(4) unsigned NOT NULL default '0' COMMENT 'Penalty time scored',
  `is_correct` tinyint(1) unsigned NOT NULL default '0' COMMENT 'Has there been a correct submission?',
  `balloon` tinyint(1) unsigned NOT NULL default '0' COMMENT 'Has a balloon been handed out?',
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
  `totaltime` int(4) unsigned NOT NULL default '0' COMMENT 'Total time spent',
  `penalty` int(4) unsigned NOT NULL default '0' COMMENT 'Penalty time scored',
  `is_correct` tinyint(1) unsigned NOT NULL default '0' COMMENT 'Has there been a correct submission?',
  PRIMARY KEY  (`cid`,`teamid`,`probid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Scoreboard cache (public/team version)';

-- 
-- Table structure for table `submission`
-- 

CREATE TABLE `submission` (
  `submitid` int(4) unsigned NOT NULL auto_increment COMMENT 'Unique ID',
  `cid` int(4) unsigned NOT NULL COMMENT 'Contest ID',
  `teamid` varchar(15) NOT NULL COMMENT 'Team login',
  `probid` varchar(8) NOT NULL COMMENT 'Problem ID',
  `langid` varchar(8) NOT NULL COMMENT 'Language ID',
  `submittime` datetime NOT NULL COMMENT 'Time submitted',
  `sourcecode` mediumblob NOT NULL COMMENT 'Full source code',
  `judgehost` varchar(50) default NULL COMMENT 'Current/last judgehost judging this submission',
  `judgemark` varchar(255) default NULL COMMENT 'Unique identifier for taking a submission by a judgehost' ,
  `valid` tinyint(1) unsigned NOT NULL default '1' COMMENT 'If false ignore this submission in all scoreboard calculations' ,
  PRIMARY KEY  (`submitid`),
  UNIQUE KEY `judgemark` (`judgemark`),
  KEY `teamid` (`cid`,`teamid`),
  KEY `judgehost` (`cid`,`judgehost`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='All incoming submissions';

-- 
-- Table structure for table `team`
-- 

CREATE TABLE `team` (
  `login` varchar(15) NOT NULL COMMENT 'Team login name',
  `name` varchar(255) NOT NULL COMMENT 'Team name',
  `categoryid` int(4) unsigned NOT NULL default '0' COMMENT 'Team category ID',
  `affilid` varchar(10) default NULL COMMENT 'Team affiliation ID',
  `ipaddress` varchar(50) default NULL COMMENT 'IP address of team workstation',
  `hostname` varchar(255) default NULL COMMENT 'Cached hostname associated with IP address',
  `passwd` varchar(32) default NULL COMMENT 'Team password (salted md5 hash)',
  `members` text COMMENT 'Team member names (freeform)',
  `room` varchar(15) default NULL COMMENT 'Physical location of team',
  `comments` text COMMENT 'Comments about this team',
  `teampage_first_visited` datetime default NULL COMMENT 'Time of first teampage view',
  PRIMARY KEY  (`login`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `ipaddress` (`ipaddress`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='All teams participating in the contest';

-- 
-- Table structure for table `team_affiliation`
-- 

CREATE TABLE `team_affiliation` (
  `affilid` varchar(10) NOT NULL COMMENT 'Unique ID',
  `name` varchar(255) NOT NULL COMMENT 'Descriptive name',
  `country` char(2) default NULL COMMENT 'ISO country code',
  `comments` text COMMENT 'Comments',
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
  PRIMARY KEY  (`teamid`,`type`,`mesgid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='List of items a team has not viewed yet';

-- 
-- Table structure for table `testcase`
-- 

CREATE TABLE `testcase` (
  `id` int(4) unsigned NOT NULL auto_increment COMMENT 'Unique identifier',
  `md5sum_input` char(32) default NULL COMMENT 'Checksum of input data',
  `md5sum_output` char(32) default NULL COMMENT 'Checksum of output data',
  `input` longblob COMMENT 'Input data',
  `output` longblob COMMENT 'Output data',
  `probid` varchar(8) NOT NULL COMMENT 'Corresponding problem ID',
  `description` varchar(255) default NULL COMMENT 'Description of this testcase',
  PRIMARY KEY  (`id`),
  KEY `probid` (`probid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Stores testcases per problem';
