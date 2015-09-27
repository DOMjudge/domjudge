-- This updates the DOMjudge database to use the utf8mb4 character set
-- that supports full UTF-8, instead of the old utf8 that only
-- supports up to 3-byte characters. This requires MySQL version >= 5.5.3.
--
-- Note that utf8mb4 is backwards compatible with utf8, so no data is
-- lost in the conversion. There is also no problem to keep using the
-- old utf8 character set either, but then the MySQL character set and
-- collation settings in etc/domserver-config.php have to be changed.
--
-- This snippet is included by default in the upgrade file from DOMjudge 5.0.0.

-- Temporarily disable foreign key checks to allow changing indices:
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;

-- First update defaults, this doesn't change anything.
ALTER DATABASE DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

ALTER TABLE `auditlog`              DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
ALTER TABLE `balloon`               DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
ALTER TABLE `clarification`         DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
ALTER TABLE `configuration`         DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
ALTER TABLE `contest`               DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
ALTER TABLE `contestproblem`        DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
ALTER TABLE `contestteam`           DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
ALTER TABLE `event`                 DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
ALTER TABLE `executable`            DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
ALTER TABLE `judgehost`             DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
ALTER TABLE `judgehost_restriction` DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
ALTER TABLE `judging`               DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
ALTER TABLE `judging_run`           DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
ALTER TABLE `language`              DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
ALTER TABLE `problem`               DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
ALTER TABLE `rankcache_jury`        DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
ALTER TABLE `rankcache_public`      DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
ALTER TABLE `rejudging`             DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
ALTER TABLE `role`                  DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
ALTER TABLE `scorecache_jury`       DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
ALTER TABLE `scorecache_public`     DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
ALTER TABLE `submission`            DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
ALTER TABLE `submission_file`       DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
ALTER TABLE `team`                  DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
ALTER TABLE `team_affiliation`      DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
ALTER TABLE `team_category`         DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
ALTER TABLE `team_unread`           DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
ALTER TABLE `testcase`              DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
ALTER TABLE `user`                  DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
ALTER TABLE `userrole`              DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- First we have to manually convert all VARCHAR data types, because
-- otherwise MySQL tries to adjust their length.
ALTER TABLE `auditlog`
  MODIFY `user` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'User who performed this action',
  MODIFY `datatype` varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Reference to DB table associated to this entry',
  MODIFY `dataid` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Identifier in reference table',
  MODIFY `action` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Description of action performed',
  MODIFY `extrainfo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Optional additional description of the entry';

ALTER TABLE `clarification`
  MODIFY `jury_member` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Name of jury member who answered this';

ALTER TABLE `configuration`
  MODIFY `name` varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Name of the configuration variable',
  MODIFY `type` varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Type of the value (metatype for use in the webinterface)',
  MODIFY `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Description for in the webinterface';

ALTER TABLE `contest`
  DROP KEY `shortname`,
  MODIFY `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Descriptive name',
  MODIFY `shortname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Short name for this contest',
  MODIFY `activatetime_string` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Authoritative absolute or relative string representation of activatetime',
  MODIFY `starttime_string` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Authoritative absolute (only!) string representation of starttime',
  MODIFY `freezetime_string` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Authoritative absolute or relative string representation of freezetime',
  MODIFY `endtime_string` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Authoritative absolute or relative string representation of endtime',
  MODIFY `unfreezetime_string` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Authoritative absolute or relative string representation of unfreezetrime',
  MODIFY `deactivatetime_string` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Authoritative absolute or relative string representation of deactivatetime',
  ADD UNIQUE KEY `shortname` (`shortname`(190));

ALTER TABLE `contestproblem`
  DROP KEY `shortname`,
  MODIFY `shortname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Unique problem ID within contest (string)',
  MODIFY `color` varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Balloon colour to display on the scoreboard',
  ADD UNIQUE KEY `shortname` (`cid`,`shortname`(190));

ALTER TABLE `event`
  MODIFY `langid` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Language ID';

ALTER TABLE `executable`
  MODIFY `execid` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Unique ID (string)',
  MODIFY `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Description of this executable',
  MODIFY `type` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Type of executable';

ALTER TABLE `judgehost`
  MODIFY `hostname` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Resolvable hostname of judgehost';

ALTER TABLE `judgehost_restriction`
  MODIFY `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Descriptive name';

ALTER TABLE `judging`
  MODIFY `judgehost` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Judgehost that performed the judging',
  MODIFY `result` varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Result string as defined in config.php',
  MODIFY `jury_member` varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Name of jury member who verified this',
  MODIFY `verify_comment` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Optional additional information provided by the verifier';

ALTER TABLE `judging_run`
  MODIFY `runresult` varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Result of this run, NULL if not finished yet';

ALTER TABLE `language`
  MODIFY `langid` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Unique ID (string), used for source file extension',
  MODIFY `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Descriptive language name',
  MODIFY `compile_script` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Script to compile source code for this language';

ALTER TABLE `problem`
  MODIFY `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Descriptive name',
  MODIFY `special_run` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Script to run submissions for this problem',
  MODIFY `special_compare` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Script to compare problem and jury output for this problem',
  MODIFY `special_compare_args` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Optional arguments to special_compare script',
  MODIFY `problemtext_type` varchar(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'File type of problem text';

ALTER TABLE `rejudging`
  MODIFY `reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Reason to start this rejudge';

ALTER TABLE `role`
  MODIFY `role` varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Role name',
  MODIFY `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Description for the web interface';

ALTER TABLE `submission`
  MODIFY `langid` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Language ID',
  MODIFY `judgehost` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Current/last judgehost judging this submission';

ALTER TABLE `submission_file`
  DROP KEY `filename`,
  MODIFY `filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Filename as submitted',
  ADD UNIQUE KEY `filename` (`submitid`,`filename`(190));

ALTER TABLE `team`
  DROP KEY `name`,
  DROP KEY `externalid`,
  MODIFY `externalid` varchar(255) CHARACTER SET utf8mb4 DEFAULT NULL COMMENT 'Team ID in an external system',
  MODIFY `name` varchar(255) CHARACTER SET utf8mb4 NOT NULL COMMENT 'Team name',
  MODIFY `members` longtext CHARACTER SET utf8mb4 COMMENT 'Team member names (freeform)',
  MODIFY `room` varchar(15) CHARACTER SET utf8mb4 DEFAULT NULL COMMENT 'Physical location of team',
  MODIFY `comments` longtext CHARACTER SET utf8mb4 COMMENT 'Comments about this team',
  MODIFY `hostname` varchar(255) CHARACTER SET utf8mb4 DEFAULT NULL COMMENT 'Teampage first visited from this address',
  ADD UNIQUE KEY `name` (`name`(190)),
  ADD UNIQUE KEY `externalid` (`externalid`(190));

ALTER TABLE `team_affiliation`
  MODIFY `shortname` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Short descriptive name',
  MODIFY `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Descriptive name';

ALTER TABLE `team_category`
  MODIFY `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Descriptive name',
  MODIFY `color` varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Background colour on the scoreboard';

ALTER TABLE `testcase`
  MODIFY `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Description of this testcase',
  MODIFY `image_type` varchar(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'File type of the image and thumbnail';

ALTER TABLE `user`
  DROP KEY `username`,
  MODIFY `username` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'User login name',
  MODIFY `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Name',
  MODIFY `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Email address',
  MODIFY `last_ip_address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Last IP address of successful login',
  MODIFY `password` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Password hash',
  MODIFY `ip_address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'IP Address used to autologin',
  ADD UNIQUE KEY `username` (`username`(190));

-- Now auto-update all tables.
ALTER TABLE `auditlog`              CONVERT TO CHARACTER SET DEFAULT;
ALTER TABLE `balloon`               CONVERT TO CHARACTER SET DEFAULT;
ALTER TABLE `clarification`         CONVERT TO CHARACTER SET DEFAULT;
ALTER TABLE `configuration`         CONVERT TO CHARACTER SET DEFAULT;
ALTER TABLE `contest`               CONVERT TO CHARACTER SET DEFAULT;
ALTER TABLE `contestproblem`        CONVERT TO CHARACTER SET DEFAULT;
ALTER TABLE `contestteam`           CONVERT TO CHARACTER SET DEFAULT;
ALTER TABLE `event`                 CONVERT TO CHARACTER SET DEFAULT;
ALTER TABLE `executable`            CONVERT TO CHARACTER SET DEFAULT;
ALTER TABLE `judgehost`             CONVERT TO CHARACTER SET DEFAULT;
ALTER TABLE `judgehost_restriction` CONVERT TO CHARACTER SET DEFAULT;
ALTER TABLE `judging`               CONVERT TO CHARACTER SET DEFAULT;
ALTER TABLE `judging_run`           CONVERT TO CHARACTER SET DEFAULT;
ALTER TABLE `language`              CONVERT TO CHARACTER SET DEFAULT;
ALTER TABLE `problem`               CONVERT TO CHARACTER SET DEFAULT;
ALTER TABLE `rankcache_jury`        CONVERT TO CHARACTER SET DEFAULT;
ALTER TABLE `rankcache_public`      CONVERT TO CHARACTER SET DEFAULT;
ALTER TABLE `rejudging`             CONVERT TO CHARACTER SET DEFAULT;
ALTER TABLE `role`                  CONVERT TO CHARACTER SET DEFAULT;
ALTER TABLE `scorecache_jury`       CONVERT TO CHARACTER SET DEFAULT;
ALTER TABLE `scorecache_public`     CONVERT TO CHARACTER SET DEFAULT;
ALTER TABLE `submission`            CONVERT TO CHARACTER SET DEFAULT;
ALTER TABLE `submission_file`       CONVERT TO CHARACTER SET DEFAULT;
ALTER TABLE `team`                  CONVERT TO CHARACTER SET DEFAULT;
ALTER TABLE `team_affiliation`      CONVERT TO CHARACTER SET DEFAULT;
ALTER TABLE `team_category`         CONVERT TO CHARACTER SET DEFAULT;
ALTER TABLE `team_unread`           CONVERT TO CHARACTER SET DEFAULT;
ALTER TABLE `testcase`              CONVERT TO CHARACTER SET DEFAULT;
ALTER TABLE `user`                  CONVERT TO CHARACTER SET DEFAULT;
ALTER TABLE `userrole`              CONVERT TO CHARACTER SET DEFAULT;

-- Special case table team, since team.name and team.externalid have a
-- unique key that need to be treated case-sensitive for collation.

ALTER TABLE `team`
  DROP KEY `name`,
  DROP KEY `externalid`,
  MODIFY `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Team name',
  MODIFY `externalid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Team ID in an external system',
  ADD UNIQUE KEY `name` (`name`(190)),
  ADD UNIQUE KEY `externalid` (`externalid`(190));

/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
