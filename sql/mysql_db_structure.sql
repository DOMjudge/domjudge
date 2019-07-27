-- These are the database tables needed for DOMjudge.
--
-- You can pipe this file into the 'mysql' command to create the
-- database tables, but preferably use 'dj_setup_database'. Database
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
  `datatype` varchar(32) DEFAULT NULL COMMENT 'Reference to DB table associated to this entry',
  `dataid` varchar(64) DEFAULT NULL COMMENT 'Identifier in reference table',
  `action` varchar(64) DEFAULT NULL COMMENT 'Description of action performed',
  `extrainfo` varchar(255) DEFAULT NULL COMMENT 'Optional additional description of the entry',
  PRIMARY KEY (`logid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Log of all actions performed';

--
-- Table structure for table `balloon`
--
CREATE TABLE `balloon` (
  `balloonid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
  `submitid` int(4) unsigned NOT NULL COMMENT 'Submission for which balloon was earned',
  `done` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT 'Has been handed out yet?',
  PRIMARY KEY (`balloonid`),
  KEY `submitid` (`submitid`),
  CONSTRAINT `balloon_ibfk_1` FOREIGN KEY (`submitid`) REFERENCES `submission` (`submitid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Balloons to be handed out';

--
-- Table structure for table `clarification`
--
CREATE TABLE `clarification` (
  `clarid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique clarification ID',
  `externalid` varchar(255) DEFAULT NULL COMMENT 'Clarification ID in an external system, should be unique inside a single contest',
  `cid` int(4) unsigned NOT NULL COMMENT 'Contest ID',
  `respid` int(4) unsigned DEFAULT NULL COMMENT 'In reply to clarification ID',
  `submittime` decimal(32,9) unsigned NOT NULL COMMENT 'Time sent',
  `sender` int(4) unsigned DEFAULT NULL COMMENT 'Team ID, null means jury',
  `recipient` int(4) unsigned DEFAULT NULL COMMENT 'Team ID, null means to jury or to all',
  `jury_member` varchar(255) DEFAULT NULL COMMENT 'Name of jury member who answered this',
  `probid` int(4) unsigned DEFAULT NULL COMMENT 'Problem associated to this clarification',
  `category` varchar(255) DEFAULT NULL COMMENT 'Category associated to this clarification; only set for non problem clars',
  `queue` varchar(255) DEFAULT NULL COMMENT 'Queue associated to this clarification',
  `body` longtext NOT NULL COMMENT 'Clarification text',
  `answered` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT 'Has been answered by jury?',
  PRIMARY KEY  (`clarid`),
  UNIQUE KEY `externalid` (`cid`,`externalid`(190)),
  KEY `respid` (`respid`),
  KEY `probid` (`probid`),
  KEY `cid` (`cid`),
  KEY `cid_2` (`cid`,`answered`,`submittime`),
  KEY `sender` (`sender`),
  KEY `recipient` (`recipient`),
  CONSTRAINT `clarification_ibfk_1` FOREIGN KEY (`cid`) REFERENCES `contest` (`cid`) ON DELETE CASCADE,
  CONSTRAINT `clarification_ibfk_2` FOREIGN KEY (`respid`) REFERENCES `clarification` (`clarid`) ON DELETE SET NULL,
  CONSTRAINT `clarification_ibfk_3` FOREIGN KEY (`probid`) REFERENCES `problem` (`probid`) ON DELETE SET NULL,
  CONSTRAINT `clarification_ibfk_4` FOREIGN KEY (`sender`) REFERENCES `team` (`teamid`) ON DELETE CASCADE,
  CONSTRAINT `clarification_ibfk_5` FOREIGN KEY (`recipient`) REFERENCES `team` (`teamid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Clarification requests by teams and responses by the jury';

--
-- Table structure for table `configuration`
--

CREATE TABLE `configuration` (
  `configid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
  `name` varchar(32) NOT NULL COMMENT 'Name of the configuration variable',
  `value` longtext NOT NULL COMMENT 'Content of the configuration variable (JSON encoded)',
  `type` varchar(32) DEFAULT NULL COMMENT 'Type of the value (metatype for use in the webinterface)',
  `public` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT 'Is this variable publicly visible?',
  `category` varchar(32) NOT NULL DEFAULT 'Uncategorized' COMMENT 'Option category of the configuration variable',
  `description` varchar(255) DEFAULT NULL COMMENT 'Description for in the webinterface',
  PRIMARY KEY (`configid`),
  UNIQUE KEY `name` (`name`),
  KEY `public` (`public`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Global configuration variables';

--
-- Table structure for table `contest`
--

CREATE TABLE `contest` (
  `cid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique contest ID',
  `externalid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Contest ID in an external system',
  `name` varchar(255) NOT NULL COMMENT 'Descriptive name',
  `shortname` varchar(255) NOT NULL COMMENT 'Short name for this contest',
  `activatetime` decimal(32,9) unsigned NOT NULL COMMENT 'Time contest becomes visible in team/public views',
  `starttime` decimal(32,9) unsigned NOT NULL COMMENT 'Time contest starts, submissions accepted',
  `freezetime` decimal(32,9) unsigned DEFAULT NULL COMMENT 'Time scoreboard is frozen',
  `endtime` decimal(32,9) unsigned NOT NULL COMMENT 'Time after which no more submissions are accepted',
  `unfreezetime` decimal(32,9) unsigned DEFAULT NULL COMMENT 'Unfreeze a frozen scoreboard at this time',
  `deactivatetime` decimal(32,9) UNSIGNED DEFAULT NULL COMMENT 'Time contest becomes invisible in team/public views',
  `activatetime_string` varchar(64) NOT NULL COMMENT 'Authoritative absolute or relative string representation of activatetime',
  `starttime_string` varchar(64) NOT NULL COMMENT 'Authoritative absolute (only!) string representation of starttime',
  `freezetime_string` varchar(64) DEFAULT NULL COMMENT 'Authoritative absolute or relative string representation of freezetime',
  `endtime_string` varchar(64) NOT NULL COMMENT 'Authoritative absolute or relative string representation of endtime',
  `unfreezetime_string` varchar(64) DEFAULT NULL COMMENT 'Authoritative absolute or relative string representation of unfreezetime',
  `deactivatetime_string` varchar(64) DEFAULT NULL COMMENT 'Authoritative absolute or relative string representation of deactivatetime',
  `finalizetime` decimal(32,9) UNSIGNED DEFAULT NULL COMMENT 'Time when contest was finalized, null if not yet',
  `finalizecomment` text DEFAULT NULL COMMENT 'Comments by the finalizer',
  `b` smallint(3) unsigned NOT NULL default 0 COMMENT 'Number of extra bronze medals',
  `enabled` tinyint(1) unsigned NOT NULL DEFAULT 1 COMMENT 'Whether this contest can be active',
  `starttime_enabled` tinyint(1) unsigned NOT NULL DEFAULT 1 COMMENT 'If disabled, starttime is not used, e.g. to delay contest start',
  `process_balloons` tinyint(1) UNSIGNED DEFAULT 1 NOT NULL COMMENT 'Will balloons be processed for this contest?',
  `public` tinyint(1) UNSIGNED DEFAULT 1 NOT NULL COMMENT 'Is this contest visible for the public?',
  `open_to_all_teams` tinyint(1) UNSIGNED DEFAULT 1 COMMENT 'Is this contest open to all teams?',
  PRIMARY KEY (`cid`),
  UNIQUE KEY `externalid` (`externalid`(190)),
  UNIQUE KEY `shortname` (`shortname`(190)),
  KEY `cid` (`cid`,`enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Contests that will be run with this install';

--
-- Table structure for table `contestproblem`
--

CREATE TABLE `contestproblem` (
  `cid` int(4) unsigned NOT NULL COMMENT 'Unique contest ID',
  `probid` int(4) unsigned NOT NULL COMMENT 'Unique problem ID',
  `shortname` varchar(255) NOT NULL COMMENT 'Unique problem ID within contest, used to sort problems in the scoreboard and typically a single letter',
  `points` int(4) unsigned NOT NULL DEFAULT 1 COMMENT 'Number of points earned by solving this problem',
  `allow_submit` tinyint(1) unsigned NOT NULL DEFAULT 1 COMMENT 'Are submissions accepted for this problem?',
  `allow_judge` tinyint(1) unsigned NOT NULL DEFAULT 1 COMMENT 'Are submissions for this problem judged?',
  `color` varchar(32) DEFAULT NULL COMMENT 'Balloon colour to display on the scoreboard',
  `lazy_eval_results` tinyint(1) unsigned DEFAULT NULL COMMENT 'Whether to do lazy evaluation for this problem; if set this overrides the global configuration setting',
  PRIMARY KEY (`cid`,`probid`),
  UNIQUE KEY `shortname` (`cid`,`shortname`(190)),
  KEY `cid` (`cid`),
  KEY `probid` (`probid`),
  CONSTRAINT `contestproblem_ibfk_1` FOREIGN KEY (`cid`) REFERENCES `contest` (`cid`) ON DELETE CASCADE,
  CONSTRAINT `contestproblem_ibfk_2` FOREIGN KEY (`probid`) REFERENCES `problem` (`probid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Many-to-Many mapping of contests and problems';

--
-- Table structure for table `contestteam`
--

CREATE TABLE `contestteam` (
  `cid` int(4) unsigned NOT NULL COMMENT 'Unique contest ID',
  `teamid` int(4) unsigned NOT NULL COMMENT 'Unique team ID',
  PRIMARY KEY (`cid`,`teamid`),
  KEY `IDX_8328F8554B30D9C4` (`cid`),
  KEY `IDX_8328F8554DD6ABF3` (`teamid`),
  CONSTRAINT `contestteam_ibfk_1` FOREIGN KEY (`cid`) REFERENCES `contest` (`cid`) ON DELETE CASCADE,
  CONSTRAINT `contestteam_ibfk_2` FOREIGN KEY (`teamid`) REFERENCES `team` (`teamid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Many-to-Many mapping of contests and teams';

--
-- Table structure for table `event`
--

CREATE TABLE `event` (
  `eventid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
  `eventtime` decimal(32,9) unsigned NOT NULL COMMENT 'When the event occurred',
  `cid` int(4) unsigned NOT NULL COMMENT 'Contest ID',
  `endpointtype` varchar(32) NOT NULL COMMENT 'API endpoint associated to this entry',
  `endpointid` varchar(64) NOT NULL COMMENT 'API endpoint (external) ID',
  `action` varchar(32) NOT NULL COMMENT 'Description of action performed',
  `content` longblob NOT NULL COMMENT 'JSON encoded content of the change, as provided in the event feed',
  PRIMARY KEY (`eventid`),
  KEY `eventtime` (`cid`,`eventtime`),
  KEY `cid` (`cid`),
  CONSTRAINT `event_ibfk_1` FOREIGN KEY (`cid`) REFERENCES `contest` (`cid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Log of all events during a contest';

--
-- Table structure for table `executable`
--

CREATE TABLE `executable` (
  `execid` varchar(32) NOT NULL COMMENT 'Unique ID (string)',
  `md5sum` char(32) DEFAULT NULL COMMENT 'Md5sum of zip file',
  `zipfile` longblob DEFAULT NULL COMMENT 'Zip file',
  `description` varchar(255) DEFAULT NULL COMMENT 'Description of this executable',
  `type` varchar(32) NOT NULL COMMENT 'Type of executable',
  PRIMARY KEY (`execid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Compile, compare, and run script executable bundles';

--
-- Table structure for table `external_judgement`
--

CREATE TABLE `external_judgement` (
  `extjudgementid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
  `externalid` varchar(255) DEFAULT NULL COMMENT 'Judgement ID in external system, should be unique inside a single contest',
  `cid` int(4) unsigned NOT NULL COMMENT 'Contest ID',
  `submitid` int(4) unsigned NOT NULL COMMENT 'Submission ID being judged by external system',
  `result` varchar(32) DEFAULT NULL COMMENT 'Result string as obtained from external system. null if not finished yet',
  `starttime` decimal(32,9) unsigned NOT NULL COMMENT 'Time judging started',
  `endtime` decimal(32,9) unsigned DEFAULT NULL COMMENT 'Time judging ended, null = still busy',
  `valid` tinyint(1) unsigned NOT NULL DEFAULT 1 COMMENT 'Old external judgement is marked as invalid when receiving a new one',
  PRIMARY KEY  (`extjudgementid`),
  UNIQUE KEY `externalid` (`cid`,`externalid`(190)),
  KEY `submitid` (`submitid`),
  CONSTRAINT `external_judgement_ibfk_1` FOREIGN KEY (`submitid`) REFERENCES `submission` (`submitid`) ON DELETE CASCADE,
  CONSTRAINT `external_judgement_ibfk_2` FOREIGN KEY (`cid`) REFERENCES `contest` (`cid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Judgement in external system';

CREATE TABLE `external_run` (
  `extrunid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
  `extjudgementid` int(4) unsigned NOT NULL COMMENT 'Judging ID this run belongs to',
  `testcaseid` int(4) unsigned NOT NULL COMMENT 'Testcase ID',
  `externalid` varchar(255) DEFAULT NULL COMMENT 'Run ID in external system, should be unique inside a single contest',
  `cid` int(4) unsigned NOT NULL COMMENT 'Contest ID',
  `result` varchar(32) NOT NULL COMMENT 'Result string as obtained from external system',
  `endtime` decimal(32,9) unsigned NOT NULL COMMENT 'Time run ended',
  `runtime` float NOT NULL COMMENT 'Running time on this testcase',
  PRIMARY KEY  (`extrunid`),
  UNIQUE KEY `externalid` (`cid`,`externalid`(190)),
  KEY `extjudgementid` (`extjudgementid`),
  KEY `testcaseid` (`testcaseid`),
  CONSTRAINT `external_run_ibfk_1` FOREIGN KEY (`extjudgementid`) REFERENCES `external_judgement` (`extjudgementid`) ON DELETE CASCADE,
  CONSTRAINT `external_run_ibfk_2` FOREIGN KEY (`testcaseid`) REFERENCES `testcase` (`testcaseid`) ON DELETE CASCADE,
  CONSTRAINT `external_run_ibfk_3` FOREIGN KEY (`cid`) REFERENCES `contest` (`cid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Run in external system';

--
-- Table structure for table `internal_error`
--

CREATE TABLE `internal_error` (
  `errorid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
  `judgingid` int(4) unsigned DEFAULT NULL COMMENT 'Judging ID',
  `cid` int(4) unsigned DEFAULT NULL COMMENT 'Contest ID',
  `description` varchar(255) NOT NULL COMMENT 'Description of the error',
  `judgehostlog` text NOT NULL COMMENT 'Last N lines of the judgehost log',
  `time` decimal(32,9) unsigned NOT NULL COMMENT 'Timestamp of the internal error',
  `disabled` text NOT NULL COMMENT 'Disabled stuff, JSON-encoded',
  `status` ENUM('open', 'resolved', 'ignored')  NOT NULL DEFAULT 'open' COMMENT 'Status of internal error',
  PRIMARY KEY (`errorid`),
  KEY `judgingid` (`judgingid`),
  KEY `cid` (`cid`),
  CONSTRAINT `internal_error_ibfk_1` FOREIGN KEY (`judgingid`) REFERENCES `judging` (`judgingid`) ON DELETE SET NULL,
  CONSTRAINT `internal_error_ibfk_2` FOREIGN KEY (`cid`) REFERENCES `contest` (`cid`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Log of judgehost internal errors';

--
-- Table structure for table `judgehost`
--

CREATE TABLE `judgehost` (
  `hostname` varchar(64) NOT NULL COMMENT 'Resolvable hostname of judgehost',
  `active` tinyint(1) unsigned NOT NULL DEFAULT 1 COMMENT 'Should this host take on judgings?',
  `polltime` decimal(32,9) unsigned DEFAULT NULL COMMENT 'Time of last poll by autojudger',
  `restrictionid` int(4) unsigned DEFAULT NULL COMMENT 'Optional set of restrictions for this judgehost',
  PRIMARY KEY  (`hostname`),
  KEY `restrictionid` (`restrictionid`),
  CONSTRAINT `judgehost_ibfk_1` FOREIGN KEY (`restrictionid`) REFERENCES `judgehost_restriction` (`restrictionid`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Hostnames of the autojudgers';

--
-- Table structure for table `judgehost_restriction`
--

CREATE TABLE `judgehost_restriction` (
  `restrictionid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
  `name` varchar(255) NOT NULL COMMENT 'Descriptive name',
  `restrictions` longtext DEFAULT NULL COMMENT 'JSON-encoded restrictions',
  PRIMARY KEY  (`restrictionid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Restrictions for judgehosts';

--
-- Table structure for table `judging`
--

CREATE TABLE `judging` (
  `judgingid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
  `cid` int(4) unsigned NOT NULL DEFAULT 0 COMMENT 'Contest ID',
  `submitid` int(4) unsigned NOT NULL COMMENT 'Submission ID being judged',
  `starttime` decimal(32,9) unsigned NOT NULL COMMENT 'Time judging started',
  `endtime` decimal(32,9) unsigned DEFAULT NULL COMMENT 'Time judging ended, null = still busy',
  `judgehost` varchar(64) NOT NULL COMMENT 'Judgehost that performed the judging',
  `result` varchar(32) DEFAULT NULL COMMENT 'Result string as defined in config.php',
  `verified` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT 'Result verified by jury member?',
  `jury_member` varchar(255) DEFAULT NULL COMMENT 'Name of jury member who verified this',
  `verify_comment` varchar(255) DEFAULT NULL COMMENT 'Optional additional information provided by the verifier',
  `valid` tinyint(1) unsigned NOT NULL DEFAULT 1 COMMENT 'Old judging is marked as invalid when rejudging',
  `output_compile` longblob DEFAULT NULL COMMENT 'Output of the compiling the program',
  `seen` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT 'Whether the team has seen this judging',
  `rejudgingid` int(4) unsigned DEFAULT NULL COMMENT 'Rejudging ID (if rejudge)',
  `prevjudgingid` int(4) unsigned DEFAULT NULL COMMENT 'Previous valid judging (if rejudge)',
  PRIMARY KEY  (`judgingid`),
  KEY `submitid` (`submitid`),
  KEY `judgehost` (`judgehost`),
  KEY `cid` (`cid`),
  KEY `rejudgingid` (`rejudgingid`),
  KEY `prevjudgingid` (`prevjudgingid`),
  CONSTRAINT `judging_ibfk_1` FOREIGN KEY (`cid`) REFERENCES `contest` (`cid`) ON DELETE CASCADE,
  CONSTRAINT `judging_ibfk_2` FOREIGN KEY (`submitid`) REFERENCES `submission` (`submitid`) ON DELETE CASCADE,
  CONSTRAINT `judging_ibfk_3` FOREIGN KEY (`judgehost`) REFERENCES `judgehost` (`hostname`),
  CONSTRAINT `judging_ibfk_4` FOREIGN KEY (`rejudgingid`) REFERENCES `rejudging` (`rejudgingid`) ON DELETE SET NULL,
  CONSTRAINT `judging_ibfk_5` FOREIGN KEY (`prevjudgingid`) REFERENCES `judging` (`judgingid`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Result of judging a submission';

--
-- Table structure for table `judging_run`
--

CREATE TABLE `judging_run` (
  `runid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
  `judgingid` int(4) unsigned NOT NULL COMMENT 'Judging ID',
  `testcaseid` int(4) unsigned NOT NULL COMMENT 'Testcase ID',
  `runresult` varchar(32) DEFAULT NULL COMMENT 'Result of this run, NULL if not finished yet',
  `runtime` float DEFAULT NULL COMMENT 'Submission running time on this testcase',
  `endtime` decimal(32,9) unsigned NOT NULL COMMENT 'Time run judging ended',
  PRIMARY KEY  (`runid`),
  UNIQUE KEY `testcaseid` (`judgingid`, `testcaseid`),
  KEY `judgingid` (`judgingid`),
  KEY `testcaseid_2` (`testcaseid`),
  CONSTRAINT `judging_run_ibfk_1` FOREIGN KEY (`testcaseid`) REFERENCES `testcase` (`testcaseid`),
  CONSTRAINT `judging_run_ibfk_2` FOREIGN KEY (`judgingid`) REFERENCES `judging` (`judgingid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Result of a testcase run within a judging';

--
-- Table structure for table `judging_run_output`
--

CREATE TABLE `judging_run_output` (
  `runid` int(4) unsigned NOT NULL COMMENT 'Run ID',
  `output_run` longblob DEFAULT NULL COMMENT 'Output of running the program',
  `output_diff` longblob DEFAULT NULL COMMENT 'Diffing the program output and testcase output',
  `output_error` longblob DEFAULT NULL COMMENT 'Standard error output of the program',
  `output_system` longblob DEFAULT NULL COMMENT 'Judging system output',
  PRIMARY KEY  (`runid`),
  CONSTRAINT `judging_run_output_ibfk_1` FOREIGN KEY (`runid`) REFERENCES `judging_run` (`runid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores output of judging run';

--
-- Table structure for table `language`
--

CREATE TABLE `language` (
  `langid` varchar(32) NOT NULL COMMENT 'Unique ID (string)',
  `externalid` varchar(255) DEFAULT NULL COMMENT 'Language ID to expose in the REST API',
  `name` varchar(255) NOT NULL COMMENT 'Descriptive language name',
  `extensions` longtext DEFAULT NULL COMMENT 'List of recognized extensions (JSON encoded)(DC2Type:json)',
  `require_entry_point` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Whether submissions require a code entry point to be specified.',
  `entry_point_description` varchar(255) DEFAULT NULL COMMENT 'The description used in the UI for the entry point field.',
  `allow_submit` tinyint(1) unsigned NOT NULL DEFAULT 1 COMMENT 'Are submissions accepted in this language?',
  `allow_judge` tinyint(1) unsigned NOT NULL DEFAULT 1 COMMENT 'Are submissions in this language judged?',
  `time_factor` float NOT NULL DEFAULT 1 COMMENT 'Language-specific factor multiplied by problem run times',
  `compile_script` varchar(32) DEFAULT NULL COMMENT 'Script to compile source code for this language',
  PRIMARY KEY  (`langid`),
  UNIQUE KEY `externalid` (`externalid`(190)),
  KEY `compile_script` (`compile_script`),
  CONSTRAINT `language_ibfk_1` FOREIGN KEY (`compile_script`) REFERENCES `executable` (`execid`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Programming languages in which teams can submit solutions';

--
-- Table structure for table `problem`
--

CREATE TABLE `problem` (
  `probid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique problem ID',
  `externalid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Problem ID in an external system, should be unique inside a single contest',
  `name` varchar(255) NOT NULL COMMENT 'Descriptive name',
  `timelimit` float unsigned NOT NULL DEFAULT 0 COMMENT 'Maximum run time (in seconds) for this problem',
  `memlimit` int(4) unsigned DEFAULT NULL COMMENT 'Maximum memory available (in kB) for this problem',
  `outputlimit` int(4) unsigned DEFAULT NULL COMMENT 'Maximum output size (in kB) for this problem',
  `special_run` varchar(32) DEFAULT NULL COMMENT 'Script to run submissions for this problem',
  `special_compare` varchar(32) DEFAULT NULL COMMENT 'Script to compare problem and jury output for this problem',
  `special_compare_args` varchar(255) DEFAULT NULL COMMENT 'Optional arguments to special_compare script',
  `combined_run_compare` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT 'Use the exit code of the run script to compute the verdict',
  `problemtext` longblob DEFAULT NULL COMMENT 'Problem text in HTML/PDF/ASCII',
  `problemtext_type` varchar(4) DEFAULT NULL COMMENT 'File type of problem text',
  PRIMARY KEY  (`probid`),
  KEY `externalid` (`externalid`(190)),
  KEY `special_run` (`special_run`),
  KEY `special_compare` (`special_compare`),
  CONSTRAINT `problem_ibfk_1` FOREIGN KEY (`special_run`) REFERENCES `executable` (`execid`) ON DELETE SET NULL,
  CONSTRAINT `problem_ibfk_2` FOREIGN KEY (`special_compare`) REFERENCES `executable` (`execid`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Problems the teams can submit solutions for';

--
-- Table structure for table `rankcache`
--

CREATE TABLE `rankcache` (
  `cid` int(4) unsigned NOT NULL COMMENT 'Contest ID',
  `teamid` int(4) unsigned NOT NULL COMMENT 'Team ID',
  `points_restricted` int(4) unsigned NOT NULL DEFAULT 0 COMMENT 'Total correctness points (restricted audience)',
  `totaltime_restricted` int(4) NOT NULL DEFAULT 0 COMMENT 'Total penalty time in minutes (restricted audience)',
  `points_public` int(4) unsigned NOT NULL DEFAULT 0 COMMENT 'Total correctness points (public)',
  `totaltime_public` int(4) NOT NULL DEFAULT 0 COMMENT 'Total penalty time in minutes (public)',
  PRIMARY KEY (`cid`,`teamid`),
  KEY `order_restricted` (`cid`,`points_restricted`,`totaltime_restricted`) USING BTREE,
  KEY `order_public` (`cid`,`points_public`,`totaltime_public`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Scoreboard rank cache';

--
-- Table structure for table `rejudging`
--
CREATE TABLE `rejudging` (
  `rejudgingid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
  `userid_start` int(4) unsigned DEFAULT NULL COMMENT 'User ID of user who started the rejudge',
  `userid_finish` int(4) unsigned DEFAULT NULL COMMENT 'User ID of user who accepted or canceled the rejudge',
  `starttime` decimal(32,9) unsigned NOT NULL COMMENT 'Time rejudging started',
  `endtime` decimal(32,9) unsigned DEFAULT NULL COMMENT 'Time rejudging ended, null = still busy',
  `reason` varchar(255) NOT NULL COMMENT 'Reason to start this rejudge',
  `valid` tinyint(1) unsigned NOT NULL DEFAULT 1 COMMENT 'Rejudging is marked as invalid if canceled',
  PRIMARY KEY  (`rejudgingid`),
  KEY `userid_start` (`userid_start`),
  KEY `userid_finish` (`userid_finish`),
  CONSTRAINT `rejudging_ibfk_1` FOREIGN KEY (`userid_start`) REFERENCES `user` (`userid`) ON DELETE SET NULL,
  CONSTRAINT `rejudging_ibfk_2` FOREIGN KEY (`userid_finish`) REFERENCES `user` (`userid`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Rejudge group';

--
-- Table structure for table `removed_interval`
--

CREATE TABLE `removed_interval` (
  `intervalid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
  `cid` int(4) unsigned NOT NULL COMMENT 'Contest ID',
  `starttime` decimal(32,9) unsigned NOT NULL COMMENT 'Initial time of removed interval',
  `endtime` decimal(32,9) unsigned NOT NULL COMMENT 'Final time of removed interval',
  `starttime_string` varchar(64) NOT NULL COMMENT 'Authoritative (absolute only) string representation of starttime',
  `endtime_string` varchar(64) NOT NULL COMMENT 'Authoritative (absolute only) string representation of endtime',
  PRIMARY KEY (`intervalid`),
  KEY `cid` (`cid`),
  CONSTRAINT `removed_interval_ibfk_1` FOREIGN KEY (`cid`) REFERENCES `contest` (`cid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Time intervals removed from the contest for scoring';

--
-- Table structure for table `role`
--

CREATE TABLE `role` (
  `roleid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
  `role` varchar(32) NOT NULL COMMENT 'Role name',
  `description` varchar(255) NOT NULL COMMENT 'Description for the web interface',
  PRIMARY KEY (`roleid`),
  UNIQUE KEY `role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Possible user roles';

--
-- Table structure for table `scorecache`
--

CREATE TABLE `scorecache` (
  `cid` int(4) unsigned NOT NULL COMMENT 'Contest ID',
  `teamid` int(4) unsigned NOT NULL COMMENT 'Team ID',
  `probid` int(4) unsigned NOT NULL COMMENT 'Problem ID',
  `submissions_restricted` int(4) unsigned NOT NULL DEFAULT 0 COMMENT 'Number of submissions made (restricted audiences)',
  `pending_restricted` int(4) unsigned NOT NULL DEFAULT 0 COMMENT 'Number of submissions pending judgement (restricted audience)',
  `solvetime_restricted`  decimal(32,9) NOT NULL DEFAULT 0.000000000 COMMENT 'Seconds into contest when problem solved (restricted audience)',
  `is_correct_restricted` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT 'Has there been a correct submission? (restricted audience)',
  `submissions_public` int(4) unsigned NOT NULL DEFAULT 0 COMMENT 'Number of submissions made (public)',
  `pending_public` int(4) unsigned NOT NULL DEFAULT 0 COMMENT 'Number of submissions pending judgement (public)',
  `solvetime_public` decimal(32,9) NOT NULL DEFAULT 0.000000000 COMMENT 'Seconds into contest when problem solved (public)',
  `is_correct_public` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT 'Has there been a correct submission? (public)',
  `is_first_to_solve` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT 'Is this the first solution to this problem?',
  PRIMARY KEY (`cid`,`teamid`,`probid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Scoreboard cache';

--
-- Table structure for table `submission`
--

CREATE TABLE `submission` (
  `submitid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
  `origsubmitid` int(4) unsigned DEFAULT NULL COMMENT 'If set, specifies original submission in case of edit/resubmit',
  `cid` int(4) unsigned NOT NULL COMMENT 'Contest ID',
  `teamid` int(4) unsigned NOT NULL COMMENT 'Team ID',
  `probid` int(4) unsigned NOT NULL COMMENT 'Problem ID',
  `langid` varchar(32) NOT NULL COMMENT 'Language ID',
  `submittime` decimal(32,9) unsigned NOT NULL COMMENT 'Time submitted',
  `judgehost` varchar(64) DEFAULT NULL COMMENT 'Current/last judgehost judging this submission',
  `valid` tinyint(1) unsigned NOT NULL DEFAULT 1 COMMENT 'If false ignore this submission in all scoreboard calculations',
  `rejudgingid` int(4) unsigned DEFAULT NULL COMMENT 'Rejudging ID (if rejudge)',
  `expected_results` varchar(255) DEFAULT NULL COMMENT 'JSON encoded list of expected results - used to validate jury submissions(DC2Type:json)',
  `externalid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Specifies ID of submission if imported from external CCS, e.g. Kattis',
  `entry_point` varchar(255) DEFAULT NULL COMMENT 'Optional entry point. Can be used e.g. for java main class.',
  PRIMARY KEY  (`submitid`),
  UNIQUE KEY `externalid` (`cid`,`externalid`(190)),
  KEY `teamid` (`cid`,`teamid`),
  KEY `judgehost` (`cid`,`judgehost`),
  KEY `teamid_2` (`teamid`),
  KEY `probid` (`probid`),
  KEY `langid` (`langid`),
  KEY `judgehost_2` (`judgehost`),
  KEY `origsubmitid` (`origsubmitid`),
  KEY `rejudgingid` (`rejudgingid`),
  KEY `probid_2` (`cid`,`probid`),
  CONSTRAINT `submission_ibfk_1` FOREIGN KEY (`cid`) REFERENCES `contest` (`cid`) ON DELETE CASCADE,
  CONSTRAINT `submission_ibfk_2` FOREIGN KEY (`teamid`) REFERENCES `team` (`teamid`) ON DELETE CASCADE,
  CONSTRAINT `submission_ibfk_3` FOREIGN KEY (`probid`) REFERENCES `problem` (`probid`) ON DELETE CASCADE,
  CONSTRAINT `submission_ibfk_4` FOREIGN KEY (`langid`) REFERENCES `language` (`langid`) ON DELETE CASCADE,
  CONSTRAINT `submission_ibfk_5` FOREIGN KEY (`judgehost`) REFERENCES `judgehost` (`hostname`) ON DELETE SET NULL,
  CONSTRAINT `submission_ibfk_6` FOREIGN KEY (`origsubmitid`) REFERENCES `submission` (`submitid`) ON DELETE SET NULL,
  CONSTRAINT `submission_ibfk_7` FOREIGN KEY (`rejudgingid`) REFERENCES `rejudging` (`rejudgingid`) ON DELETE SET NULL,
  CONSTRAINT `submission_ibfk_8` FOREIGN KEY (`cid`,`probid`) REFERENCES `contestproblem` (`cid`,`probid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='All incoming submissions';

--
-- Table structure for table `submission_file`
--

CREATE TABLE `submission_file` (
  `submitfileid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
  `submitid` int(4) unsigned NOT NULL COMMENT 'Submission this file belongs to',
  `sourcecode` longblob NOT NULL COMMENT 'Full source code(DC2Type:blobtext)',
  `filename` varchar(255) NOT NULL COMMENT 'Filename as submitted',
  `rank` int(4) unsigned NOT NULL COMMENT 'Order of the submission files, zero-indexed',
  PRIMARY KEY (`submitfileid`),
  UNIQUE KEY `filename` (`submitid`,`filename`(190)),
  UNIQUE KEY `rank` (`submitid`,`rank`),
  KEY `submitid` (`submitid`),
  CONSTRAINT `submission_file_ibfk_1` FOREIGN KEY (`submitid`) REFERENCES `submission` (`submitid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Files associated to a submission';

--
-- Table structure for table `team`
--

CREATE TABLE `team` (
  `teamid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique team ID',
  `externalid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Team affiliation ID in an external system',
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Team name',
  `categoryid` int(4) unsigned NOT NULL DEFAULT 0 COMMENT 'Team category ID',
  `affilid` int(4) unsigned DEFAULT NULL COMMENT 'Team affiliation ID',
  `enabled` tinyint(1) unsigned NOT NULL DEFAULT 1 COMMENT 'Whether the team is visible and operational',
  `members` longtext DEFAULT NULL COMMENT 'Team member names (freeform)',
  `room` varchar(255) DEFAULT NULL COMMENT 'Physical location of team',
  `comments` longtext DEFAULT NULL COMMENT 'Comments about this team',
  `judging_last_started` decimal(32,9) unsigned DEFAULT NULL COMMENT 'Start time of last judging for priorization',
  `penalty` int(4) NOT NULL DEFAULT 0 COMMENT 'Additional penalty time in minutes',
  PRIMARY KEY  (`teamid`),
  UNIQUE KEY `externalid` (`externalid`(190)),
  KEY `affilid` (`affilid`),
  KEY `categoryid` (`categoryid`),
  CONSTRAINT `team_ibfk_1` FOREIGN KEY (`categoryid`) REFERENCES `team_category` (`categoryid`) ON DELETE CASCADE,
  CONSTRAINT `team_ibfk_2` FOREIGN KEY (`affilid`) REFERENCES `team_affiliation` (`affilid`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='All teams participating in the contest';

--
-- Table structure for table `team_affiliation`
--

CREATE TABLE `team_affiliation` (
  `affilid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
  `externalid` varchar(255) DEFAULT NULL COMMENT 'Team affiliation ID in an external system',
  `shortname` varchar(32) NOT NULL COMMENT 'Short descriptive name',
  `name` varchar(255) NOT NULL COMMENT 'Descriptive name',
  `country` char(3) DEFAULT NULL COMMENT 'ISO 3166-1 alpha-3 country code',
  `comments` longtext DEFAULT NULL COMMENT 'Comments',
  PRIMARY KEY  (`affilid`),
  UNIQUE KEY `externalid` (`externalid`(190))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Affilitations for teams (e.g.: university, company)';

--
-- Table structure for table `team_category`
--

CREATE TABLE `team_category` (
  `categoryid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
  `name` varchar(255) NOT NULL COMMENT 'Descriptive name',
  `sortorder` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT 'Where to sort this category on the scoreboard(DC2Type:tinyint)',
  `color` varchar(32) DEFAULT NULL COMMENT 'Background colour on the scoreboard',
  `visible` tinyint(1) unsigned NOT NULL DEFAULT 1 COMMENT 'Are teams in this category visible?',
  PRIMARY KEY  (`categoryid`),
  KEY `sortorder` (`sortorder`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Categories for teams (e.g.: participants, observers, ...)';

--
-- Table structure for table `team_unread`
--

CREATE TABLE `team_unread` (
  `teamid` int(4) unsigned NOT NULL COMMENT 'Unique team ID',
  `mesgid` int(4) unsigned NOT NULL COMMENT 'Unique clarification ID',
  PRIMARY KEY (`teamid`,`mesgid`),
  KEY `IDX_3272D5F4DD6ABF3` (`teamid`),
  KEY `IDX_3272D5F9E88E262` (`mesgid`),
  CONSTRAINT `team_unread_ibfk_1` FOREIGN KEY (`teamid`) REFERENCES `team` (`teamid`) ON DELETE CASCADE,
  CONSTRAINT `team_unread_ibfk_2` FOREIGN KEY (`mesgid`) REFERENCES `clarification` (`clarid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='List of items a team has not viewed yet';

--
-- Table structure for table `testcase`
--

CREATE TABLE `testcase` (
  `testcaseid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
  `md5sum_input` char(32) DEFAULT NULL COMMENT 'Checksum of input data',
  `md5sum_output` char(32) DEFAULT NULL COMMENT 'Checksum of output data',
  `probid` int(4) unsigned NOT NULL COMMENT 'Corresponding problem ID',
  `rank` int(4) NOT NULL COMMENT 'Determines order of the testcases in judging',
  `description` longblob DEFAULT NULL COMMENT 'Description of this testcase',
  `image_type` varchar(4) DEFAULT NULL COMMENT 'File type of the image and thumbnail',
  `sample` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT 'Sample testcases that can be shared with teams',
  PRIMARY KEY  (`testcaseid`),
  UNIQUE KEY `rank` (`probid`,`rank`),
  KEY `probid` (`probid`),
  KEY `sample` (`sample`),
  CONSTRAINT `testcase_ibfk_1` FOREIGN KEY (`probid`) REFERENCES `problem` (`probid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores testcases per problem';

CREATE TABLE `testcase_content` (
  `testcaseid` int(4) unsigned NOT NULL COMMENT 'Testcase ID',
  `input` longblob DEFAULT NULL COMMENT 'Input data',
  `output` longblob DEFAULT NULL COMMENT 'Output data',
  `image` longblob DEFAULT NULL COMMENT 'A graphical representation of the testcase',
  `image_thumb` longblob DEFAULT NULL COMMENT 'Aumatically created thumbnail of the image',
  PRIMARY KEY  (`testcaseid`),
  CONSTRAINT `testcase_content_ibfk_1` FOREIGN KEY (`testcaseid`) REFERENCES `testcase` (`testcaseid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores contents of testcase';

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `userid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
  `username` varchar(255) NOT NULL COMMENT 'User login name',
  `name` varchar(255) NOT NULL COMMENT 'Name',
  `email` varchar(255) DEFAULT NULL COMMENT 'Email address',
  `last_login` decimal(32,9) unsigned DEFAULT NULL COMMENT 'Time of last successful login',
  `first_login` decimal(32,9) unsigned DEFAULT NULL COMMENT 'Time of first login',
  `last_ip_address` varchar(255) DEFAULT NULL COMMENT 'Last IP address of successful login',
  `password` varchar(255) DEFAULT NULL COMMENT 'Password hash',
  `ip_address` varchar(255) DEFAULT NULL COMMENT 'IP Address used to autologin',
  `enabled` tinyint(1) unsigned NOT NULL DEFAULT 1 COMMENT 'Whether the user is able to log in',
  `teamid` int(4) unsigned DEFAULT NULL COMMENT 'Team associated with',
  PRIMARY KEY (`userid`),
  UNIQUE KEY `username` (`username`(190)),
  KEY `teamid` (`teamid`),
  CONSTRAINT `user_ibfk_1` FOREIGN KEY (`teamid`) REFERENCES `team` (`teamid`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Users that have access to DOMjudge';


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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Many-to-Many mapping of users and roles';

/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;

-- vim:tw=0:
