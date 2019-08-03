<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

final class Version20190803123217 extends AbstractMigration implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    public function getDescription(): string
    {
        return 'Migrate database to DOMjudge version 7.0.0';
    }

    public function up(Schema $schema): void
    {
        $this->skipIf(
            $this->connection->getSchemaManager()->tablesExist(['contest']),
            'table contest already exists'
        );

        $this->addSql('SET FOREIGN_KEY_CHECKS = 0');
        $this->createTables();
        $this->addSql('SET FOREIGN_KEY_CHECKS = 1');
        $this->loadDefaultData();
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            true,
            'Downgrading to schemas before DOMjudge 7.0 not supported'
        );
    }

    protected function createTables()
    {
        // Table structure for table `auditlog`
        $this->addSql(<<<SQL
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Log of all actions performed'
SQL
        );

        // Table structure for table `balloon`
        $this->addSql(<<<SQL
CREATE TABLE `balloon` (
    `balloonid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
    `submitid` int(4) unsigned NOT NULL COMMENT 'Submission for which balloon was earned',
    `done` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'Has been handed out yet?',
    PRIMARY KEY (`balloonid`),
    KEY `submitid` (`submitid`),
    CONSTRAINT `balloon_ibfk_1` FOREIGN KEY (`submitid`) REFERENCES `submission` (`submitid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Balloons to be handed out'
SQL
        );

        // Table structure for table `clarification`
        $this->addSql(<<<SQL
CREATE TABLE `clarification` (
    `clarid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
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
    `answered` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'Has been answered by jury?',
    PRIMARY KEY  (`clarid`),
    UNIQUE KEY `externalid` (`cid`,`externalid`(190)),
    KEY `respid` (`respid`),
    KEY `probid` (`probid`),
    KEY `cid` (`cid`),
    KEY `cid_2` (`cid`,`answered`,`submittime`),
    CONSTRAINT `clarification_ibfk_1` FOREIGN KEY (`cid`) REFERENCES `contest` (`cid`) ON DELETE CASCADE,
    CONSTRAINT `clarification_ibfk_2` FOREIGN KEY (`respid`) REFERENCES `clarification` (`clarid`) ON DELETE SET NULL,
    CONSTRAINT `clarification_ibfk_3` FOREIGN KEY (`probid`) REFERENCES `problem` (`probid`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Clarification requests by teams and responses by the jury'
SQL
        );

        // Table structure for table `configuration`
        $this->addSql(<<<SQL
CREATE TABLE `configuration` (
    `configid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
    `name` varchar(32) NOT NULL COMMENT 'Name of the configuration variable',
    `value` longtext NOT NULL COMMENT 'Content of the configuration variable (JSON encoded)',
    `type` varchar(32) DEFAULT NULL COMMENT 'Type of the value (metatype for use in the webinterface)',
    `public` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'Is this variable publicly visible?',
    `category` varchar(32) NOT NULL DEFAULT 'Uncategorized' COMMENT 'Option category of the configuration variable',
    `description` varchar(255) DEFAULT NULL COMMENT 'Description for in the webinterface',
    PRIMARY KEY (`configid`),
    UNIQUE KEY `name` (`name`),
    KEY `public` (`public`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Global configuration variables'
SQL
        );

        // Table structure for table `contest`
        $this->addSql(<<<SQL
CREATE TABLE `contest` (
    `cid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
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
    `finalizetime` decimal(32,9) DEFAULT NULL COMMENT 'Time when contest was finalized, null if not yet',
    `finalizecomment` text COMMENT 'Comments by the finalizer',
    `b` smallint(3) unsigned NOT NULL default '0' COMMENT 'Number of extra bronze medals',
    `enabled` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT 'Whether this contest can be active',
    `starttime_enabled` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT 'If disabled, starttime is not used, e.g. to delay contest start',
    `process_balloons` tinyint(1) UNSIGNED DEFAULT '1' COMMENT 'Will balloons be processed for this contest?',
    `public` tinyint(1) UNSIGNED DEFAULT '1' COMMENT 'Is this contest visible for the public and non-associated teams?',
    PRIMARY KEY (`cid`),
    UNIQUE KEY `externalid` (`externalid`(190)),
    UNIQUE KEY `shortname` (`shortname`(190)),
    KEY `cid` (`cid`,`enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Contests that will be run with this install'
SQL
        );

        // Table structure for table `contestproblem`
        $this->addSql(<<<SQL
CREATE TABLE `contestproblem` (
    `cid` int(4) unsigned NOT NULL COMMENT 'Contest ID',
    `probid` int(4) unsigned NOT NULL COMMENT 'Problem ID',
    `shortname` varchar(255) NOT NULL COMMENT 'Unique problem ID within contest, used to sort problems in the scoreboard and typically a single letter',
    `points` int(4) unsigned NOT NULL DEFAULT '1' COMMENT 'Number of points earned by solving this problem',
    `allow_submit` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT 'Are submissions accepted for this problem?',
    `allow_judge` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT 'Are submissions for this problem judged?',
    `color` varchar(32) DEFAULT NULL COMMENT 'Balloon colour to display on the scoreboard',
    `lazy_eval_results` tinyint(1) unsigned DEFAULT NULL COMMENT 'Whether to do lazy evaluation for this problem; if set this overrides the global configuration setting',
    PRIMARY KEY (`cid`,`probid`),
    UNIQUE KEY `shortname` (`cid`,`shortname`(190)),
    KEY `cid` (`cid`),
    KEY `probid` (`probid`),
    CONSTRAINT `contestproblem_ibfk_1` FOREIGN KEY (`cid`) REFERENCES `contest` (`cid`) ON DELETE CASCADE,
    CONSTRAINT `contestproblem_ibfk_2` FOREIGN KEY (`probid`) REFERENCES `problem` (`probid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Many-to-Many mapping of contests and problems'
SQL
        );

        // Table structure for table `contestteam`
        $this->addSql(<<<SQL
CREATE TABLE `contestteam` (
    `cid` int(4) unsigned NOT NULL COMMENT 'Contest ID',
    `teamid` int(4) unsigned NOT NULL COMMENT 'Team ID',
    PRIMARY KEY (`cid`,`teamid`),
    KEY `cid` (`cid`),
    KEY `teamid` (`teamid`),
    CONSTRAINT `contestteam_ibfk_1` FOREIGN KEY (`cid`) REFERENCES `contest` (`cid`) ON DELETE CASCADE,
    CONSTRAINT `contestteam_ibfk_2` FOREIGN KEY (`teamid`) REFERENCES `team` (`teamid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Many-to-Many mapping of contests and teams'
SQL
        );

        // Table structure for table `event`
        $this->addSql(<<<SQL
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Log of all events during a contest'
SQL
        );

        // Table structure for table `executable`
        $this->addSql(<<<SQL
CREATE TABLE `executable` (
    `execid` varchar(32) NOT NULL COMMENT 'Unique ID (string)',
    `md5sum` char(32) DEFAULT NULL COMMENT 'Md5sum of zip file',
    `zipfile` longblob COMMENT 'Zip file',
    `description` varchar(255) DEFAULT NULL COMMENT 'Description of this executable',
    `type` varchar(32) NOT NULL COMMENT 'Type of executable',
    PRIMARY KEY (`execid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Compile, compare, and run script executable bundles'
SQL
        );

        // Table structure for table `internal_error`
        $this->addSql(<<<SQL
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Log of judgehost internal errors'
SQL
        );

        // Table structure for table `judgehost`
        $this->addSql(<<<SQL
CREATE TABLE `judgehost` (
    `hostname` varchar(64) NOT NULL COMMENT 'Resolvable hostname of judgehost',
    `active` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT 'Should this host take on judgings?',
    `polltime` decimal(32,9) unsigned DEFAULT NULL COMMENT 'Time of last poll by autojudger',
    `restrictionid` int(4) unsigned DEFAULT NULL COMMENT 'Optional set of restrictions for this judgehost',
    PRIMARY KEY  (`hostname`),
    KEY `restrictionid` (`restrictionid`),
    CONSTRAINT `judgehost_ibfk_1` FOREIGN KEY (`restrictionid`) REFERENCES `judgehost_restriction` (`restrictionid`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Hostnames of the autojudgers'
SQL
        );

        // Table structure for table `judgehost_restriction`

        $this->addSql(<<<SQL
CREATE TABLE `judgehost_restriction` (
    `restrictionid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
    `name` varchar(255) NOT NULL COMMENT 'Descriptive name',
    `restrictions` longtext COMMENT 'JSON-encoded restrictions',
    PRIMARY KEY  (`restrictionid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Restrictions for judgehosts'
SQL
        );

        // Table structure for table `judging`
        $this->addSql(<<<SQL
CREATE TABLE `judging` (
    `judgingid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
    `cid` int(4) unsigned NOT NULL DEFAULT '0' COMMENT 'Contest ID',
    `submitid` int(4) unsigned NOT NULL COMMENT 'Submission ID being judged',
    `starttime` decimal(32,9) unsigned NOT NULL COMMENT 'Time judging started',
    `endtime` decimal(32,9) unsigned DEFAULT NULL COMMENT 'Time judging ended, null = still busy',
    `judgehost` varchar(64) NOT NULL COMMENT 'Judgehost that performed the judging',
    `result` varchar(32) DEFAULT NULL COMMENT 'Result string as defined in config.php',
    `verified` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'Result verified by jury member?',
    `jury_member` varchar(255) DEFAULT NULL COMMENT 'Name of jury member who verified this',
    `verify_comment` varchar(255) DEFAULT NULL COMMENT 'Optional additional information provided by the verifier',
    `valid` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT 'Old judging is marked as invalid when rejudging',
    `output_compile` longblob COMMENT 'Output of the compiling the program',
    `seen` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'Whether the team has seen this judging',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Result of judging a submission'
SQL
        );

        // Table structure for table `judging_run`
        $this->addSql(<<<SQL
CREATE TABLE `judging_run` (
    `runid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
    `judgingid` int(4) unsigned NOT NULL COMMENT 'Judging ID',
    `testcaseid` int(4) unsigned NOT NULL COMMENT 'Testcase ID',
    `runresult` varchar(32) DEFAULT NULL COMMENT 'Result of this run, NULL if not finished yet',
    `runtime` float DEFAULT NULL COMMENT 'Submission running time on this testcase',
    `endtime` decimal(32,9) unsigned NOT NULL COMMENT 'Time run judging ended',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Result of a testcase run within a judging'
SQL
        );

        // Table structure for table `language`
        $this->addSql(<<<SQL
CREATE TABLE `language` (
    `langid` varchar(32) NOT NULL COMMENT 'Unique ID (string)',
    `externalid` varchar(255) DEFAULT NULL COMMENT 'Language ID to expose in the REST API',
    `name` varchar(255) NOT NULL COMMENT 'Descriptive language name',
    `extensions` longtext COMMENT 'List of recognized extensions (JSON encoded)',
    `require_entry_point` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Whether submissions require a code entry point to be specified.',
    `entry_point_description` varchar(255) DEFAULT NULL COMMENT 'The description used in the UI for the entry point field.',
    `allow_submit` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT 'Are submissions accepted in this language?',
    `allow_judge` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT 'Are submissions in this language judged?',
    `time_factor` float NOT NULL DEFAULT '1' COMMENT 'Language-specific factor multiplied by problem run times',
    `compile_script` varchar(32) DEFAULT NULL COMMENT 'Script to compile source code for this language',
    PRIMARY KEY  (`langid`),
    UNIQUE KEY `externalid` (`externalid`(190))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Programming languages in which teams can submit solutions'
SQL
        );

        // Table structure for table `problem`
        $this->addSql(<<<SQL
CREATE TABLE `problem` (
    `probid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
    `externalid` varchar(255) DEFAULT NULL COMMENT 'Problem ID in an external system, should be unique inside a single contest',
    `name` varchar(255) NOT NULL COMMENT 'Descriptive name',
    `timelimit` float unsigned NOT NULL DEFAULT '0' COMMENT 'Maximum run time (in seconds) for this problem',
    `memlimit` int(4) unsigned DEFAULT NULL COMMENT 'Maximum memory available (in kB) for this problem',
    `outputlimit` int(4) unsigned DEFAULT NULL COMMENT 'Maximum output size (in kB) for this problem',
    `special_run` varchar(32) DEFAULT NULL COMMENT 'Script to run submissions for this problem',
    `special_compare` varchar(32) DEFAULT NULL COMMENT 'Script to compare problem and jury output for this problem',
    `special_compare_args` varchar(255) DEFAULT NULL COMMENT 'Optional arguments to special_compare script',
    `combined_run_compare` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'Use the exit code of the run script to compute the verdict',
    `problemtext` longblob COMMENT 'Problem text in HTML/PDF/ASCII',
    `problemtext_type` varchar(4) DEFAULT NULL COMMENT 'File type of problem text',
    PRIMARY KEY  (`probid`),
    KEY `externalid` (`externalid`(190))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Problems the teams can submit solutions for'
SQL
        );

        // Table structure for table `rankcache`
        // Note that we explicitly use BTREEs for some indices here to make sure we can sort efficiently.
        $this->addSql(<<<SQL
CREATE TABLE `rankcache` (
    `cid` int(4) unsigned NOT NULL COMMENT 'Contest ID',
    `teamid` int(4) unsigned NOT NULL COMMENT 'Team ID',
    `points_restricted` int(4) unsigned NOT NULL DEFAULT '0' COMMENT 'Total correctness points (restricted audience)',
    `totaltime_restricted` int(4) NOT NULL DEFAULT '0' COMMENT 'Total penalty time in minutes (restricted audience)',
    `points_public` int(4) unsigned NOT NULL DEFAULT '0' COMMENT 'Total correctness points (public)',
    `totaltime_public` int(4) NOT NULL DEFAULT '0' COMMENT 'Total penalty time in minutes (public)',
    PRIMARY KEY (`cid`,`teamid`),
    KEY `order_restricted` (`cid`,`points_restricted`,`totaltime_restricted`) USING BTREE,
    KEY `order_public` (`cid`,`points_public`,`totaltime_public`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Scoreboard rank cache'
SQL
        );

        // Table structure for table `rejudging`
        $this->addSql(<<<SQL
CREATE TABLE `rejudging` (
    `rejudgingid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
    `userid_start` int(4) unsigned DEFAULT NULL COMMENT 'User ID of user who started the rejudge',
    `userid_finish` int(4) unsigned DEFAULT NULL COMMENT 'User ID of user who accepted or canceled the rejudge',
    `starttime` decimal(32,9) unsigned NOT NULL COMMENT 'Time rejudging started',
    `endtime` decimal(32,9) unsigned DEFAULT NULL COMMENT 'Time rejudging ended, null = still busy',
    `reason` varchar(255) NOT NULL COMMENT 'Reason to start this rejudge',
    `valid` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT 'Rejudging is marked as invalid if canceled',
    PRIMARY KEY  (`rejudgingid`),
    KEY `userid_start` (`userid_start`),
    KEY `userid_finish` (`userid_finish`),
    CONSTRAINT `rejudging_ibfk_1` FOREIGN KEY (`userid_start`) REFERENCES `user` (`userid`) ON DELETE SET NULL,
    CONSTRAINT `rejudging_ibfk_2` FOREIGN KEY (`userid_finish`) REFERENCES `user` (`userid`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Rejudge group'
SQL
        );

        // Table structure for table `removed_interval`
        $this->addSql(<<<SQL
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Time intervals removed from the contest for scoring'
SQL
        );

        // Table structure for table `role`
        $this->addSql(<<<SQL
CREATE TABLE `role` (
    `roleid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
    `role` varchar(32) NOT NULL COMMENT 'Role name',
    `description` varchar(255) NOT NULL COMMENT 'Description for the web interface',
    PRIMARY KEY (`roleid`),
    UNIQUE KEY `role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Possible user roles'
SQL
        );

        // Table structure for table `scorecache`
        $this->addSql(<<<SQL
CREATE TABLE `scorecache` (
    `cid` int(4) unsigned NOT NULL COMMENT 'Contest ID',
    `teamid` int(4) unsigned NOT NULL COMMENT 'Team ID',
    `probid` int(4) unsigned NOT NULL COMMENT 'Problem ID',
    `submissions_restricted` int(4) unsigned NOT NULL DEFAULT '0' COMMENT 'Number of submissions made (restricted audiences)',
    `pending_restricted` int(4) unsigned NOT NULL DEFAULT '0' COMMENT 'Number of submissions pending judgement (restricted audience)',
    `solvetime_restricted`  decimal(32,9) NOT NULL DEFAULT '0.000000000' COMMENT 'Seconds into contest when problem solved (restricted audience)',
    `is_correct_restricted` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'Has there been a correct submission? (restricted audience)',
    `submissions_public` int(4) unsigned NOT NULL DEFAULT '0' COMMENT 'Number of submissions made (public)',
    `pending_public` int(4) unsigned NOT NULL DEFAULT '0' COMMENT 'Number of submissions pending judgement (public)',
    `solvetime_public` decimal(32,9) NOT NULL DEFAULT '0.000000000' COMMENT 'Seconds into contest when problem solved (public)',
    `is_correct_public` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'Has there been a correct submission? (public)',
    `is_first_to_solve` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'Is this the first solution to this problem?',
    PRIMARY KEY (`cid`,`teamid`,`probid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Scoreboard cache'
SQL
        );

        // Table structure for table `submission`
        $this->addSql(<<<SQL
CREATE TABLE `submission` (
    `submitid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
    `origsubmitid` int(4) unsigned DEFAULT NULL COMMENT 'If set, specifies original submission in case of edit/resubmit',
    `cid` int(4) unsigned NOT NULL COMMENT 'Contest ID',
    `teamid` int(4) unsigned NOT NULL COMMENT 'Team ID',
    `probid` int(4) unsigned NOT NULL COMMENT 'Problem ID',
    `langid` varchar(32) NOT NULL COMMENT 'Language ID',
    `submittime` decimal(32,9) unsigned NOT NULL COMMENT 'Time submitted',
    `judgehost` varchar(64) DEFAULT NULL COMMENT 'Current/last judgehost judging this submission',
    `valid` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT 'If false ignore this submission in all scoreboard calculations',
    `rejudgingid` int(4) unsigned DEFAULT NULL COMMENT 'Rejudging ID (if rejudge)',
    `expected_results` varchar(255) DEFAULT NULL COMMENT 'JSON encoded list of expected results - used to validate jury submissions',
    `externalid` varchar(255) DEFAULT NULL COMMENT 'Specifies ID of submission if imported from external CCS, e.g. Kattis',
    `externalresult` varchar(32) DEFAULT NULL COMMENT 'Result string as returned from external CCS, e.g. Kattis',
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
    CONSTRAINT `submission_ibfk_1` FOREIGN KEY (`cid`) REFERENCES `contest` (`cid`) ON DELETE CASCADE,
    CONSTRAINT `submission_ibfk_2` FOREIGN KEY (`teamid`) REFERENCES `team` (`teamid`) ON DELETE CASCADE,
    CONSTRAINT `submission_ibfk_3` FOREIGN KEY (`probid`) REFERENCES `problem` (`probid`) ON DELETE CASCADE,
    CONSTRAINT `submission_ibfk_4` FOREIGN KEY (`langid`) REFERENCES `language` (`langid`) ON DELETE CASCADE,
    CONSTRAINT `submission_ibfk_5` FOREIGN KEY (`judgehost`) REFERENCES `judgehost` (`hostname`) ON DELETE SET NULL,
    CONSTRAINT `submission_ibfk_6` FOREIGN KEY (`origsubmitid`) REFERENCES `submission` (`submitid`) ON DELETE SET NULL,
    CONSTRAINT `submission_ibfk_7` FOREIGN KEY (`rejudgingid`) REFERENCES `rejudging` (`rejudgingid`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='All incoming submissions'
SQL
        );

        // Table structure for table `submission_file`
        $this->addSql(<<<SQL
CREATE TABLE `submission_file` (
    `submitfileid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
    `submitid` int(4) unsigned NOT NULL COMMENT 'Submission this file belongs to',
    `sourcecode` longblob NOT NULL COMMENT 'Full source code',
    `filename` varchar(255) NOT NULL COMMENT 'Filename as submitted',
    `rank` int(4) unsigned NOT NULL COMMENT 'Order of the submission files, zero-indexed',
    PRIMARY KEY (`submitfileid`),
    UNIQUE KEY `filename` (`submitid`,`filename`(190)),
    UNIQUE KEY `rank` (`submitid`,`rank`),
    KEY `submitid` (`submitid`),
    CONSTRAINT `submission_file_ibfk_1` FOREIGN KEY (`submitid`) REFERENCES `submission` (`submitid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Files associated to a submission'
SQL
        );

        // Table structure for table `team`
        $this->addSql(<<<SQL
CREATE TABLE `team` (
    `teamid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
    `externalid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Team ID in an external system',
    `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Team name',
    `categoryid` int(4) unsigned NOT NULL DEFAULT '0' COMMENT 'Team category ID',
    `affilid` int(4) unsigned DEFAULT NULL COMMENT 'Team affiliation ID',
    `enabled` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT 'Whether the team is visible and operational',
    `members` longtext COMMENT 'Team member names (freeform)',
    `room` varchar(255) DEFAULT NULL COMMENT 'Physical location of team',
    `comments` longtext COMMENT 'Comments about this team',
    `judging_last_started` decimal(32,9) unsigned DEFAULT NULL COMMENT 'Start time of last judging for priorization',
    `penalty` int(4) NOT NULL DEFAULT '0' COMMENT 'Additional penalty time in minutes',
    PRIMARY KEY  (`teamid`),
    UNIQUE KEY `externalid` (`externalid`(190)),
    KEY `affilid` (`affilid`),
    KEY `categoryid` (`categoryid`),
    CONSTRAINT `team_ibfk_1` FOREIGN KEY (`categoryid`) REFERENCES `team_category` (`categoryid`) ON DELETE CASCADE,
    CONSTRAINT `team_ibfk_2` FOREIGN KEY (`affilid`) REFERENCES `team_affiliation` (`affilid`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='All teams participating in the contest'
SQL
        );

        // Table structure for table `team_affiliation`
        $this->addSql(<<<SQL
CREATE TABLE `team_affiliation` (
    `affilid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
    `externalid` varchar(255) DEFAULT NULL COMMENT 'Team affiliation ID in an external system',
    `shortname` varchar(32) NOT NULL COMMENT 'Short descriptive name',
    `name` varchar(255) NOT NULL COMMENT 'Descriptive name',
    `country` char(3) DEFAULT NULL COMMENT 'ISO 3166-1 alpha-3 country code',
    `comments` longtext COMMENT 'Comments',
    PRIMARY KEY  (`affilid`),
    UNIQUE KEY `externalid` (`externalid`(190))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Affilitations for teams (e.g.: university, company)'
SQL
        );

        // Table structure for table `team_category`
        $this->addSql(<<<SQL
CREATE TABLE `team_category` (
    `categoryid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
    `name` varchar(255) NOT NULL COMMENT 'Descriptive name',
    `sortorder` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'Where to sort this category on the scoreboard',
    `color` varchar(32) DEFAULT NULL COMMENT 'Background colour on the scoreboard',
    `visible` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT 'Are teams in this category visible?',
    PRIMARY KEY  (`categoryid`),
    KEY `sortorder` (`sortorder`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Categories for teams (e.g.: participants, observers, ...)'
SQL
        );

        // Table structure for table `team_unread`
        $this->addSql(<<<SQL
CREATE TABLE `team_unread` (
    `teamid` int(4) unsigned NOT NULL COMMENT 'Team ID',
    `mesgid` int(4) unsigned NOT NULL COMMENT 'Clarification ID',
    PRIMARY KEY (`teamid`,`mesgid`),
    KEY `mesgid` (`mesgid`),
    CONSTRAINT `team_unread_ibfk_1` FOREIGN KEY (`teamid`) REFERENCES `team` (`teamid`) ON DELETE CASCADE,
    CONSTRAINT `team_unread_ibfk_2` FOREIGN KEY (`mesgid`) REFERENCES `clarification` (`clarid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='List of items a team has not viewed yet'
SQL
        );

        // Table structure for table `testcase`
        $this->addSql(<<<SQL
CREATE TABLE `testcase` (
    `testcaseid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
    `md5sum_input` char(32) DEFAULT NULL COMMENT 'Checksum of input data',
    `md5sum_output` char(32) DEFAULT NULL COMMENT 'Checksum of output data',
    `input` longblob COMMENT 'Input data',
    `output` longblob COMMENT 'Output data',
    `probid` int(4) unsigned NOT NULL COMMENT 'Corresponding problem ID',
    `rank` int(4) NOT NULL COMMENT 'Determines order of the testcases in judging',
    `description` longblob COMMENT 'Description of this testcase',
    `image` longblob COMMENT 'A graphical representation of this testcase',
    `image_thumb` longblob COMMENT 'Aumatically created thumbnail of the image',
    `image_type` varchar(4) DEFAULT NULL COMMENT 'File type of the image and thumbnail',
    `sample` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'Sample testcases that can be shared with teams',
    PRIMARY KEY  (`testcaseid`),
    UNIQUE KEY `rank` (`probid`,`rank`),
    KEY `probid` (`probid`),
    KEY `sample` (`sample`),
    CONSTRAINT `testcase_ibfk_1` FOREIGN KEY (`probid`) REFERENCES `problem` (`probid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores testcases per problem'
SQL
        );

        // Table structure for table `user`
        $this->addSql(<<<SQL
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
    `enabled` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT 'Whether the user is able to log in',
    `teamid` int(4) unsigned DEFAULT NULL COMMENT 'Team associated with',
    PRIMARY KEY (`userid`),
    UNIQUE KEY `username` (`username`(190)),
    KEY `teamid` (`teamid`),
    CONSTRAINT `user_ibfk_1` FOREIGN KEY (`teamid`) REFERENCES `team` (`teamid`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Users that have access to DOMjudge'
SQL
        );

        // Table structure for table `userrole`
        $this->addSql(<<<SQL
CREATE TABLE `userrole` (
    `userid` int(4) unsigned NOT NULL COMMENT 'User ID',
    `roleid` int(4) unsigned NOT NULL COMMENT 'Role ID',
    PRIMARY KEY (`userid`, `roleid`),
    KEY `userid` (`userid`),
    KEY `roleid` (`roleid`),
    CONSTRAINT `userrole_ibfk_1` FOREIGN KEY (`userid`) REFERENCES `user` (`userid`) ON DELETE CASCADE,
    CONSTRAINT `userrole_ibfk_2` FOREIGN KEY (`roleid`) REFERENCES `role` (`roleid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Many-to-Many mapping of users and roles'
SQL
        );
    }

    protected function loadDefaultData()
    {
        // Data for table `configuration`
        $this->addSql(<<<SQL
INSERT INTO `configuration` (`name`, `value`, `type`, `public`, `category`, `description`) VALUES
    ('verification_required', '0', 'bool', '0', 'Scoring', 'Is verification of judgings by jury required before publication?'),
    ('compile_penalty', '1', 'bool', '1', 'Scoring', 'Should submissions with compiler-error incur penalty time (and show on the scoreboard)?'),
    ('penalty_time', '20', 'int', '1', 'Scoring', 'Penalty time in minutes per wrong submission (if finally solved).'),
    ('results_prio', '{"memory-limit":99,"output-limit":99,"run-error":99,"timelimit":99,"wrong-answer":30,"no-output":10,"correct":1}', 'array_keyval', '0', 'Scoring', 'Priorities of results for determining final result with multiple testcases. Higher priority is used first as final result. With equal priority, the first occurring result determines the final result.'),
    ('results_remap', '{}', 'array_keyval', '0', 'Scoring', 'Remap testcase result, e.g. to disable a specific result type such as ''no-output''.'),
    ('score_in_seconds', '0', 'bool', '1', 'Scoring', 'Should the scoreboard resolution be measured in seconds instead of minutes?'),
    ('memory_limit', '524288', 'int', '0', 'Judging', 'Maximum memory usage (in kB) by submissions. This includes the shell which starts the compiled solution and also any interpreter like the Java VM, which takes away approx. 300MB! Can be overridden per problem.'),
    ('output_limit', '4096', 'int', '0', 'Judging', 'Maximum output (in kB) submissions may generate. Any excessive output is truncated, so this should be greater than the maximum testdata output.'),
    ('process_limit', '64', 'int', '0', 'Judging', 'Maximum number of processes that the submission is allowed to start (including shell and possibly interpreters).'),
    ('sourcesize_limit', '256', 'int', '1', 'Judging', 'Maximum source code size (in kB) of a submission. This setting should be kept in sync with that in "etc/submit-config.h.in".'),
    ('sourcefiles_limit', '100', 'int', '1', 'Judging', 'Maximum number of source files in one submission. Set to one to disable multiple file submissions.'),
    ('script_timelimit', '30', 'int', '0', 'Judging', 'Maximum seconds available for compile/compare scripts. This is a safeguard against malicious code and buggy scripts, so a reasonable but large amount should do.'),
    ('script_memory_limit', '2097152', 'int', '0', 'Judging', 'Maximum memory usage (in kB) by compile/compare scripts. This is a safeguard against malicious code and buggy script, so a reasonable but large amount should do.'),
    ('script_filesize_limit', '540672', 'int', '0', 'Judging', 'Maximum filesize (in kB) compile/compare scripts may write. Submission will fail with compiler-error when trying to write more, so this should be greater than any *intermediate or final* result written by compilers.'),
    ('timelimit_overshoot', '"1s|10%"', 'string', '0', 'Judging', 'Time that submissions are kept running beyond timelimit before being killed. Specify as "Xs" for X seconds, "Y%" as percentage, or a combination of both separated by one of "+|&" for the sum, maximum, or minimum of both.'),
    ('output_storage_limit', '50000', 'int', '0', 'Judging', 'Maximum size of error/system output stored in the database (in bytes); use "-1" to disable any limits.'),
    ('output_display_limit', '2000', 'int', '0', 'Judging', 'Maximum size of run/diff/error/system output shown in the jury interface (in bytes); use "-1" to disable any limits.'),
    ('lazy_eval_results', '1', 'bool', '0', 'Judging', 'Lazy evaluation of results? If enabled, stops judging as soon as a highest priority result is found, otherwise always all testcases will be judged.'),
    ('judgehost_warning', '30', 'int', '0', 'Judging', 'Time in seconds after a judgehost last checked in before showing its status as "warning".'),
    ('judgehost_critical', '120', 'int', '0', 'Judging', 'Time in seconds after a judgehost last checked in before showing its status as "critical".'),
    ('diskspace_error', '1048576', 'int', '0', 'Judging', 'Minimum free disk space (in kB) on judgehosts.'),
    ('update_judging_seconds', '0', 'int', '0', 'Judging', 'Post updates to a judging every X seconds. Set to 0 to update after each judging_run.'),
    ('default_compare', '"compare"', 'string', '0', 'Judging', 'The script used to compare outputs if no special compare script specified.'),
    ('default_run', '"run"', 'string', '0', 'Judging', 'The script used to run submissions if no special run script specified.'),
    ('clar_categories', '{"general":"General issue","tech":"Technical issue"}', 'array_keyval', '1', 'Clarification', 'List of additional clarification categories'),
    ('clar_answers', '["No comment","Read the problem statement carefully"]', 'array_val', '0', 'Clarification', 'List of predefined clarification answers'),
    ('clar_queues', '{}', 'array_keyval', '1', 'Clarification', 'List of clarification queues'),
    ('clar_default_problem_queue', '""', 'string', '1', 'Clarification', 'Queue to assign to problem clarifications'),
    ('show_pending', '0', 'bool', '1', 'Display', 'Show pending submissions on the scoreboard?'),
    ('show_flags', '1', 'bool', '1', 'Display', 'Show country flags in the interfaces?'),
    ('show_affiliations', '1', 'bool', '1', 'Display', 'Show affiliation names in the interfaces?'),
    ('show_affiliation_logos', '0', 'bool', '1', 'Display', 'Show affiliation logos on the scoreboard?'),
    ('show_teams_submissions', '1', 'bool', '1', 'Display', 'Show problem columns with submission information on the public and team scoreboards?'),
    ('show_compile', '2', 'int', '1', 'Display', 'Show compile output in team webinterface? Choices: 0 = never, 1 = only on compilation error(s), 2 = always.'),
    ('show_sample_output', '0', 'bool', '1', 'Display', 'Should teams be able to view a diff of their and the reference output to sample testcases?'),
    ('show_balloons_postfreeze', '0', 'bool', '1', 'Display', 'Give out balloon notifications after the scoreboard has been frozen?'),
    ('show_relative_time', '0', 'bool', '1', 'Display', 'Print times of contest events relative to contest start (instead of absolute).'),
    ('time_format', '"%H:%M"', 'string', '0', 'Display', 'The format used to print times. For formatting options see the PHP \'strftime\' function.'),
    ('thumbnail_size', '128', 'int', '0', 'Display', 'Maximum width/height of a thumbnail for uploaded testcase images.'),
    ('show_limits_on_team_page', '0', 'bool', '1', 'Display', 'Show time and memory limit on the team problems page'),
    ('team_column_width', '0', 'int', '0', 'Display', 'Maximum width of team column on scoreboard. Leave 0 for no maximum.'),
    ('enable_printing', '0', 'bool', '1', 'Misc', 'Enable teams and jury to send source code to a printer via the DOMjudge web interface.'),
    ('registration_category_name', '""', 'string', '1', 'Misc', 'Team category for users that register themselves with the system. Self-registration is disabled if this field is left empty.'),
    ('data_source', '0', 'int', '0', 'Misc', 'Source of data. Choices: 0 = all local, 1 = configuration data external, 2 = configuration and live data external'),
    ('auth_methods', '[]', 'array_val', '0', 'Authentication', 'List of allowed additional authentication methods. Supported values are \'ipaddress\', and \'xheaders\''),
    ('allow_openid_auth', '0', 'bool', '1', 'Authentication', 'Allow users to log in using OpenID'),
    ('openid_autocreate_team', '1', 'bool', '1', 'Authentication', 'Create a team for each user that logs in with OpenID'),
    ('openid_provider', '"https://accounts.google.com"', 'string', '1', 'Authentication', 'OpenID Provider URL'),
    ('openid_clientid', '""', 'string', '0','Authentication', 'OpenID Connect client id'),
    ('openid_clientsecret', '""', 'string', '0', 'Authentication', 'OpenID Connect client secret'),
    ('ip_autologin', '0', 'bool', '0', 'Authentication', 'Enable to skip the login page when using IP authentication.')
SQL
        );

        // Data for table `executable`
        $this->addSql(<<<SQL
INSERT INTO `executable` (`execid`, `description`, `type`) VALUES
    ('adb', 'adb', 'compile'),
    ('awk', 'awk', 'compile'),
    ('bash', 'bash', 'compile'),
    ('c', 'c', 'compile'),
    ('compare', 'default compare script', 'compare'),
    ('cpp', 'cpp', 'compile'),
    ('csharp', 'csharp', 'compile'),
    ('f95', 'f95', 'compile'),
    ('float', 'default compare script for floats with prec 1E-7', 'compare'),
    ('hs', 'hs', 'compile'),
    ('kt', 'kt', 'compile'),
    ('java_javac', 'java_javac', 'compile'),
    ('java_javac_detect', 'java_javac_detect', 'compile'),
    ('js', 'js', 'compile'),
    ('lua', 'lua', 'compile'),
    ('pas', 'pas', 'compile'),
    ('pl', 'pl', 'compile'),
    ('plg', 'plg', 'compile'),
    ('py2', 'py2', 'compile'),
    ('py3', 'py3', 'compile'),
    ('r', 'r', 'compile'),
    ('rb', 'rb', 'compile'),
    ('run', 'default run script', 'run'),
    ('scala', 'scala', 'compile'),
    ('sh', 'sh', 'compile'),
    ('swift', 'swift', 'compile')
SQL
        );

        // Load executable contents
        $dir = sprintf(
            '%s/files/defaultdata/',
            $this->container->getParameter('domjudge.sqldir')
        );

        foreach (glob($dir . '*.zip') as $zipFile) {
            $id     = pathinfo($zipFile)['filename'];
            $params = [
                ':execid' => $id,
                ':md5sum' => md5_file($zipFile),
            ];
            // We use sprintf and insert the zip contents directly because otherwise
            // it would be printed on stdout and that will break terminals
            $content = strtoupper(bin2hex(file_get_contents($zipFile)));
            $this->addSql(
                sprintf(
                    'UPDATE executable SET zipfile = 0x%s, md5sum = :md5sum WHERE execid = :execid',
                    $content
                ),
                $params
            );
        }

        // Data for table `language`
        $this->addSql(<<<SQL
INSERT INTO `language` (`langid`, `externalid`, `name`, `extensions`, `require_entry_point`, `entry_point_description`, `allow_submit`, `allow_judge`, `time_factor`, `compile_script`) VALUES
    ('adb', 'ada', 'Ada', '["adb","ads"]', 0, NULL, 0, 1, 1, 'adb'),
    ('awk', 'awk', 'AWK', '["awk"]', 0, NULL, 0, 1, 1, 'awk'),
    ('bash', 'bash', 'Bash shell', '["bash"]', 0, "Main file", 0, 1, 1, 'bash'),
    ('c', 'c', 'C', '["c"]', 0, NULL, 1, 1, 1, 'c'),
    ('cpp', 'cpp', 'C++', '["cpp","cc","cxx","c++"]', 0, NULL, 1, 1, 1, 'cpp'),
    ('csharp', 'csharp', 'C#', '["csharp","cs"]', 0, NULL, 0, 1, 1, 'csharp'),
    ('f95', 'f95', 'Fortran', '["f95","f90"]', 0, NULL, 0, 1, 1, 'f95'),
    ('hs', 'haskell', 'Haskell', '["hs","lhs"]', 0, NULL, 0, 1, 1, 'hs'),
    ('java', 'java', 'Java', '["java"]', 0, "Main class", 1, 1, 1, 'java_javac_detect'),
    ('js', 'javascript', 'JavaScript', '["js"]', 0, "Main file", 0, 1, 1, 'js'),
    ('lua', 'lua', 'Lua', '["lua"]', 0, NULL, 0, 1, 1, 'lua'),
    ('kt', 'kotlin', 'Kotlin', '["kt"]', 1, "Main class", 0, 1, 1, 'kt'),
    ('pas', 'pascal', 'Pascal', '["pas","p"]', 0, "Main file", 0, 1, 1, 'pas'),
    ('pl', 'pl', 'Perl', '["pl"]', 0, "Main file", 0, 1, 1, 'pl'),
    ('plg', 'prolog', 'Prolog', '["plg"]', 0, "Main file", 0, 1, 1, 'plg'),
    ('py2', 'python2', 'Python 2', '["py2","py"]', 0, "Main file", 0, 1, 1, 'py2'),
    ('py3', 'python3', 'Python 3', '["py3"]', 0, "Main file", 0, 1, 1, 'py3'),
    ('r', 'r', 'R', '["R"]', 0, "Main file", 0, 1, 1, 'r'),
    ('rb', 'ruby', 'Ruby', '["rb"]', 0, "Main file", 0, 1, 1, 'rb'),
    ('scala', 'scala', 'Scala', '["scala"]', 0, NULL, 0, 1, 1, 'scala'),
    ('sh', 'sh', 'POSIX shell', '["sh"]', 0, "Main file", 0, 1, 1, 'sh'),
    ('swift', 'swift', 'Swift', '["swift"]', 0, "Main file", 0, 1, 1, 'swift')
SQL
        );

        // Data for table `role`
        $this->addSql(<<<SQL
INSERT INTO `role` (`roleid`, `role`, `description`) VALUES
    (1,  'admin',             'Administrative User'),
    (2,  'jury',              'Jury User'),
    (3,  'team',              'Team Member'),
    (4,  'balloon',           'Balloon runner'),
    (6,  'judgehost',         '(Internal/System) Judgehost'),
    (9,  'api_reader',        'API reader'),
    (10, 'api_writer',        'API writer'),
    (11, 'api_source_reader', 'Source code reader')
SQL
        );

        // Data for table `team_category`
        $this->addSql(<<<SQL
INSERT INTO `team_category` (`categoryid`, `name`, `sortorder`, `color`, `visible`) VALUES
    (1, 'System', 9, '#ff2bea', 0),
    (2, 'Self-Registered', 8, '#33cc44', 1)
SQL
        );

        // Data for table `team`
        $this->addSql(<<<SQL
INSERT INTO `team` (`teamid`, `name`, `categoryid`, `affilid`, `room`, `comments`) VALUES
    (1, 'DOMjudge', 1, NULL, NULL, NULL)
SQL
        );

        // Set admin and judgehost credentials to bcrypt and generated values
        $adminPasswordFile      = sprintf(
            '%s/%s',
            $this->container->getParameter('domjudge.etcdir'),
            'initial_admin_password.secret'
        );
        $encryptedAdminPassword = password_hash(
            trim(file_get_contents($adminPasswordFile)),
            PASSWORD_BCRYPT);

        $restapiCredentialsFile   = sprintf(
            '%s/%s',
            $this->container->getParameter('domjudge.etcdir'),
            'restapi.secret'
        );
        $restapiPassword          = exec(
            sprintf(
                'grep -v ^# %s | cut -f4',
                escapeshellarg($restapiCredentialsFile)
            )
        );
        $encryptedRestapiPassword = password_hash(
            $restapiPassword, PASSWORD_BCRYPT
        );

        // Data for table `user`
        $this->addSql(<<<SQL
INSERT INTO `user` (`userid`, `username`, `name`, `password`) VALUES
    (1, 'admin', 'Administrator', :adminpass),
    (2, 'judgehost', 'User for judgedaemons', :judgehostpass)
SQL
            , [
                  ':adminpass'     => $encryptedAdminPassword,
                  ':judgehostpass' => $encryptedRestapiPassword
              ]
        );

        // Data for table `userrole`
        $this->addSql(<<<SQL
INSERT INTO `userrole` (`userid`, `roleid`) VALUES
    (1, 1),
    (2, 6)
SQL
        );
    }
}
